# Company FX Override & On-Demand Sync

**Date:** 2026-07-30
**Status:** Approved design, pending implementation

## Goal

Two related additions to the exchange-rate layer built in
`2026-07-28-currency-catalog-and-exchange-rates-design.md`:

**Manual override.** A company can hand-enter the rate for a currency, overriding the
globally synced value for that company's documents and roll-ups.

**On-demand sync.** Any company user can trigger `exchange-rates:sync` without waiting
for the 01:00 schedule, subject to a platform-wide cooldown.

The two interact deliberately: **a manual rate holds only until the next successful sync
of that currency pair, then the synced value takes back over.** An override is a
short-lived correction, not a permanent pin. This is the inverse of the usual
"manual always wins" rule and is the central decision of this design — a rate someone
typed once and forgot must not distort figures indefinitely.

## Current state

- `exchange_rates` — global, one row per `(base_code, quote_code)`, base is USD. Upserted
  in place by `exchange-rates:sync`; the table does not grow.
- `SyncExchangeRates` command holds all sync logic. Scheduled `dailyAt('01:00')`
  `->withoutOverlapping()` in `routes/console.php`. On failure it logs and leaves existing
  rows untouched, on the reasoning that stale rates beat an empty table.
- `ExchangeRateProvider` fetches from ExchangeRate-API (keyed v6 endpoint when
  `services.exchangerate.key` is set, keyless open endpoint otherwise).
- `CurrencyConverter::fromDatabase()` builds a converter from the global table. Returns
  `null` for unconvertible pairs rather than assuming 1.0.
- `CurrencyRollup::into($displayCode)` wraps it; called from `DashboardController` and
  `CompanyController`. Neither passes a company.
- `FreezesExchangeRate` stamps the global rate onto invoices and quotations at creation,
  so issued documents never change value when a rate moves.
- `HandleInertiaRequests` already shares `currencies.rates_as_of`.
- `currencies` catalog and `is_active` are **global**, not per-company.

There is no per-company rate concept anywhere today.

## Precedence rule

A company override for a pair is in force **iff**:

```
company_exchange_rates.set_at > exchange_rates.updated_at
```

`updated_at` is when *we* last wrote the row, bumped on every successful sync of that
pair. Three properties follow, all of them wanted:

- **Self-expiring.** The next sync retires the override with no cleanup job and no stored
  expiry that could drift out of step with the schedule.
- **Survives a failed sync.** If the 01:00 run fails, the row is untouched, so the
  override keeps applying — correct, since the alternative is falling back to a rate
  that is even older.
- **Survives a currency missing from the feed.** That pair's `updated_at` never moves, so
  the override persists. Also correct: there is no synced value to fall back to.

Comparing against `updated_at` rather than `fetched_at` matters. `fetched_at` is
upstream's own `time_last_update_unix`, which can predate our write by hours — an
override typed at 00:30 would wrongly outlive the 01:00 run if compared against it.

### Rejected alternatives

| Approach | Why not |
|---|---|
| Stored `expires_at` per override | Hardcodes the cron time into every row; changing the schedule silently mis-expires existing data. |
| Sync command deletes superseded rows | Couples the global sync to tenant tables, and destroys the record of what was set. |
| Manual wins until explicitly cleared | Rejected by product decision — a forgotten override must self-heal. |

## Data model

New table `company_exchange_rates`:

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `company_id` | foreignId, cascade delete | |
| `base_code` | string(3) | USD, matching `exchange_rates` |
| `quote_code` | string(3) | |
| `rate` | decimal(20,10) | Same precision as `exchange_rates` |
| `set_at` | timestamp | Drives precedence |
| `set_by` | foreignId nullable, null on delete | Who typed it |
| timestamps | | |

Unique on `(company_id, base_code, quote_code)`.

Superseded rows are **left in place**, not deleted. They are inert by the rule above, and
retaining them lets the UI say "superseded by this morning's sync" instead of a value
disappearing unexplained. Clearing an override deletes the row. Saving an existing
override updates `rate` and `set_at`, which puts it back in force.

## EffectiveRates service

`App\Services\EffectiveRates` is the **only** place that knows the precedence rule.

```php
public function tableFor(?int $companyId): array;   // ['ZMW' => 27.5, ...] merged
public function rateFor(?int $companyId, string $code): ?float;
```

A null company yields the plain global table, so callers without company context keep
working unchanged.

Three existing seams become company-aware through it:

- `CurrencyConverter::forCompany(?int $companyId)` replaces `fromDatabase()`.
- `CurrencyRollup::into(string $displayCode, ?int $companyId)` — update the two call
  sites in `DashboardController` and `CompanyController`.
- `FreezesExchangeRate` stamps the **effective** rate, not the global one. `company_id`
  is present in the `Invoice::create()` / `Quotation::create()` payloads, so it is set on
  the model before the `creating` hook fires.

Documents already issued are untouched. An override only affects documents created while
it is in force — the existing freeze contract is unchanged.

## ExchangeRateSynchronizer service

Sync logic moves out of `SyncExchangeRates::handle()` into
`App\Services\ExchangeRateSynchronizer`, because two callers now need it:

```php
public function sync(string $base = ExchangeRate::BASE): SyncResult;
```

`SyncResult` carries `succeeded`, `count`, `base`, `fetchedAt`, `error`. The command
becomes a thin wrapper that formats the result for the console. Failure behavior is
unchanged: log, leave existing rates in place, report.

## On-demand sync

- `POST /currencies/rates/sync`, inside the existing `auth + verified + subscribed` group.
- Available to **any company user**, not just admins.
- **Cooldown: one sync per 15 minutes, platform-wide,** enforced with a cache lock keyed
  `exchange-rates:sync`. On a held lock, redirect back with an error.
- The lock is a cooldown, not a mutex against the cron — the scheduled run must never be
  skipped because someone pressed the button, so it does not take the lock. A manual run
  overlapping the scheduled one is harmless: both upsert identical values fetched from the
  same upstream snapshot.
- **A failed attempt releases the lock immediately.** Burning a 15-minute cooldown on an
  attempt that reached nothing would defeat the button's main purpose, which is retrying
  after the provider was unreachable.
- Runs **inline**, not queued. `ExchangeRateProvider` already carries a short timeout and
  two retries, and immediate feedback is the point of the button.
- Upstream only refreshes once every 24 hours, so the button's real purpose is recovering
  from a failed 01:00 run rather than chasing fresher numbers.

### Accepted consequence: cross-tenant effect

`exchange_rates` is global, so **one company's sync retires every other company's in-force
overrides.** This was chosen knowingly over restricting the button to admins. It is
mitigated, not eliminated:

- The confirm dialog states both consequences — refreshes rates for all companies, and
  retires your own manual rates.
- The 15-minute cooldown bounds how often it can happen.

## UI

### Two timestamps, kept distinct

`CurrencyRollup::ratesAsOf()` returns `ExchangeRate::lastFetchedAt()` — the max
`fetched_at`, which is *upstream's* publication time, not when our job ran. The button
exists to recover from a run that did not happen, so the page needs both facts:

- **"Rates as of &lt;fetched_at&gt;"** — how current the numbers themselves are.
- **"Last checked &lt;updated_at&gt;"** — when we last successfully reached the provider.

Add `ExchangeRate::lastSyncedAt()` (max `updated_at`) beside the existing
`lastFetchedAt()`, and share it alongside `rates_as_of`. A gap between the two is exactly
the signal that the schedule has stopped running, and is the cue to press the button.

`updated_at` remains the precedence timestamp, per the rule above.

### Layout

A section on the existing `settings/currencies` page, one row per active currency:

```
Rates as of 00:00 today · last checked 01:00 today  [ Sync now ]

Currency   Synced rate       Your rate      Status
ZMW        26.8500  01:00    [ 27.5000 ]    In force
EUR         0.9210  01:00    [         ]    —
GBP         0.7840  01:00    [  0.7900 ]    Superseded by 01:00 sync   [Clear]
```

Routes, all company-scoped to the caller's active company:

- `POST /currencies/{code}/rate` — set or replace an override
- `DELETE /currencies/{code}/rate` — clear it
- `POST /currencies/rates/sync` — on-demand sync

Validation via Form Requests, per the project convention: rate required, numeric,
`gt:0`; currency must exist and be active.

## Testing

Unit — `EffectiveRates`:

- Override set after the last sync wins over the synced rate.
- Override set before the last sync is ignored.
- Override on a currency absent from the feed stays in force.
- Null company falls back to the global table.
- Cleared override falls back to the global table.

Feature:

- Store and clear an override; validation rejects zero, negative, non-numeric, unknown
  and inactive currencies.
- **Cross-tenant isolation** — company A's override does not move company B's figures.
- Invoice and quotation creation freezes the effective rate while an override is in force.
- Dashboard and companies roll-ups reflect the override.
- On-demand sync succeeds, and is refused while the cooldown lock is held.
- A failed on-demand sync leaves existing rates intact and reports the failure.
- A successful sync retires an in-force override; a failed one does not.

## Out of scope

- Per-document rate entry. Overrides are per-currency; a user cannot set the rate on one
  individual invoice.
- Any change to how issued documents freeze their rate.
- Admin-side FX screens.
