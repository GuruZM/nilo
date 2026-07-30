# Currency Catalog & Exchange Rates

**Date:** 2026-07-28
**Status:** Approved design, pending implementation

## Goal

Make the app's currency support real, in two sequenced phases:

**Phase 1 — Currency catalog.** Replace the 5 hardcoded seeded currencies with the
full ISO 4217 set, generated locally from ICU. Give the settings page a way to tick
which currencies the business actually uses.

**Phase 2 — Exchange rates.** Fetch daily FX rates, store them, and use them to
convert roll-up figures (dashboard, companies page) into the user's active currency.

Phase 1 comes first because Phase 2 is meaningless without it: the rate feed returns
~166 currencies but only 5 currency rows exist to reference them, and a user can only
pick a billing currency from what is active in the `currencies` table.

## Current state

- `currencies` table: `code`, `name`, `symbol`, `precision`, `is_active` (defaults to
  **true**), timestamps. Populated by `CurrencySeeder` with 5 rows.
- `invoices`, `quotations` already carry a per-document `currency_code`. Good — the
  document layer is already multi-currency.
- `users.current_currency_code` holds the active display currency.
- `HandleInertiaRequests` shares `currencies.all` (active only) and `currencies.current`.
- `Money` / `formatMoneyText` in `resources/js/components/money.tsx` stamp the active
  currency code onto whatever number they are given. **No conversion happens anywhere.**
- `DashboardController` sums `total` across invoices with no currency filter, so a
  company billing in two currencies has its raw amounts added together.

There is no FX infrastructure of any kind: no rates table, no rate columns, no provider.

## Phase 1 — Currency catalog

### CurrencyCatalog service

`App\Services\CurrencyCatalog` exposes `all(): array` returning rows shaped
`['code' => 'ZMW', 'name' => 'Zambian Kwacha', 'symbol' => 'K', 'precision' => 2]`.

Data comes from the `intl` extension, which is already loaded — no new dependency,
no network call:

- Names and symbols: `ResourceBundle::create('en', 'ICUDATA-curr')['Currencies']`,
  which yields `[symbol, displayName]` per code.
- Precision: `NumberFormatter` with `CURRENCY_CODE` set, reading `FRACTION_DIGITS`.
  This gets the minor units right — JPY 0, KWD 3, most others 2.

ICU carries 307 codes including dead ones (ZMK, DEM). ICU's `CurrencyMap` supplemental
data would filter these, but it is not reachable from this PHP build (verified —
`ResourceBundle::create('supplementalData', 'ICUDATA', false)->get('CurrencyMap')`
returns null). So the service holds an explicit `ISO_4217` constant listing current
codes, and intersects ICU's data against it. The list is data, not logic, and is
asserted against in tests.

Where ICU has no symbol it returns the code itself (e.g. `ZMW`); the service stores
`null` rather than duplicating the code into the symbol column.

### currencies:sync command

`php artisan currencies:sync`

Upserts every catalog row into `currencies` on `code`, writing `name`, `symbol`,
`precision`. **It never writes `is_active` for rows that already exist** — a sync must
not silently switch a business's currencies on or off. Rows it inserts get
`is_active = false` explicitly, because the column defaults to `true` and a sync that
activated 180 currencies would flood the switcher.

Idempotent: running twice produces no duplicates and no changes.

`CurrencySeeder` is rewritten to call the same catalog, then activate the original 5
(ZMW, USD, ZAR, EUR, GBP) so fresh installs and existing tests keep working.

### Bulk activate endpoint

`POST /currencies/active` with `codes: string[]`, handled by
`CurrencyController::updateActive()`. Sets `is_active = true` for the submitted codes
and `false` for all others, in one transaction.

Guards — a currency may not be deactivated when:

- it is the requesting user's `current_currency_code`, or
- any `company.currency_code` references it, or
- any invoice or quotation was issued in it.

Violations come back as a validation error naming the currencies, and nothing is
written. This mirrors the existing guards in `update()` and `destroy()`, which already
refuse to touch the active currency.

### Settings UI

`resources/js/pages/settings/currencies.tsx` keeps its existing CRUD table and gains a
"Browse all currencies" panel: a searchable, checkbox grid of every row in the table,
with the count of selected currencies and a single save. The per-row edit/delete flow
is untouched — the grid is about activation only.

The page now receives all currencies rather than only active ones (`index()` already
returns all).

## Phase 2 — Exchange rates

### Schema

`exchange_rates`: `base_code` (3), `quote_code` (3), `rate` decimal(20,10),
`fetched_at`, timestamps, unique on `(base_code, quote_code)`. Base is USD throughout,
matching what the free endpoint returns; cross-rates are derived at read time, not
stored. Roughly 166 rows, upserted in place — this table never grows.

`invoices` and `quotations` each gain nullable `exchange_rate_to_base` decimal(20,10)
and `exchange_rate_fetched_at`. The stored value is "units of the document's currency
per 1 USD" on the day the document was created. Existing rows stay null and fall back
to the latest rate at display time. Nothing is backfilled — inventing history would be
worse than admitting we lack it.

### ExchangeRateProvider

`App\Services\ExchangeRateProvider::fetch(): RateSnapshot` owns the HTTP call and
nothing else, so it can be faked with `Http::fake()`.

Endpoint: ExchangeRate-API. With a key (`services.exchangerate.key`, from
`EXCHANGERATE_API_KEY`) it calls the keyed v6 endpoint; without one it falls back to
the keyless open endpoint `https://open.er-api.com/v6/latest/USD`. The keyed free tier
is 1,500 requests/month against our ~30 and carries no attribution requirement; the
open endpoint requires visible attribution, so the keyless path is a development
convenience, not the intended production mode.

Throws on transport failure or a non-`success` payload. Never returns partial data.

### CurrencyConverter

`App\Services\CurrencyConverter` is pure math over a rate table:

```
convert(float $amount, string $from, string $to, ?float $frozenFromRate = null): ?float
```

Rates are quote-per-base, so converting X → Y is `amount / rate(X) * rate(Y)`, where
`rate(X)` is the frozen rate when one is supplied.

It returns **null** when a rate is missing. It never guesses and never falls back to
1.0 — a missing ZMW rate must not turn K18,700 into $18,700.

Same-currency conversion short-circuits to the input amount and needs no rate at all,
so a single-currency business is never blocked by a failed sync.

### Sync command and schedule

`php artisan exchange-rates:sync`, scheduled daily at 01:00 in `routes/console.php`
with `withoutOverlapping()`. The upstream data only refreshes every 24 hours, so
polling harder buys nothing.

On failure it logs and leaves the existing table intact. Stale rates beat no rates,
and a silently emptied rate table would turn every converted figure into a dash.

### Freezing on documents

`InvoiceController::store()` and `QuotationController::store()` stamp
`exchange_rate_to_base` and `exchange_rate_fetched_at` from the current rate table.
When no rate is available the columns stay null and the document saves normally —
invoicing must never be blocked by the FX feed being down.

Updates do not re-stamp. The rate belongs to the moment of issue.

### Read path

Roll-ups convert according to what kind of number they are:

- **Time-series** (the dashboard's monthly revenue chart) uses each document's
  **frozen** rate, falling back to the latest. Last month's bar must not move because
  the kwacha moved today.
- **Current-state** figures (paid / pending / overdue tiles, companies page stat tiles)
  use the **latest** rate, grouped by `currency_code` in SQL so each stays one query.

Amounts that cannot be converted are counted separately and surfaced as a small
"N invoices in currencies without a rate" note. They are never silently dropped from a
total — a revenue figure that quietly omits money is worse than one that admits a gap.

Invoice and quotation lists and individual documents are **not** converted. They show
the currency the document was issued in, which is what the client actually owes.

### Frontend

The shared `currencies` prop gains `rates_as_of`. Converted tiles carry a quiet
"converted at 28 Jul rates" line, because mid-market rates are indicative and will not
match what a bank or mobile money operator settles at — a gap that matters more for ZMW
than for most currencies.

## Testing

Pest, following existing conventions in `tests/Feature` and `tests/Unit`.

**Unit**
- `CurrencyCatalog`: returns known codes with correct precision (JPY 0, KWD 3, ZMW 2);
  excludes dead codes (ZMK, DEM); never returns a symbol equal to its own code.
- `CurrencyConverter`: identity, cross-rate math, frozen-rate override, missing rate
  returns null, precision at the JPY/KWD edges.

**Feature**
- `currencies:sync`: inserts inactive, re-run is idempotent, does not flip `is_active`
  on existing rows, refreshes a changed name.
- Bulk activate: activates and deactivates, rejects deactivating the user's active
  currency, rejects deactivating one used by a company or an issued document.
- `exchange-rates:sync` with `Http::fake()`: inserts, re-run does not duplicate, API
  failure preserves the existing table.
- Dashboard: mixed-currency invoices convert into the active currency; unconvertible
  amounts are reported rather than dropped.
- Invoice store: stamps the frozen rate; still succeeds when no rate exists.

## Out of scope

- Converted columns on invoice or quotation list tables.
- Historical rate back-fill (the free tier offers no back-history).
- Per-client or per-company rate overrides, and manual rate entry.
- Anything resembling trading-grade or streaming rates.
