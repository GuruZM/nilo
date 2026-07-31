# Nilo v1 Documents Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete Nilo's document layer by adding payments/receipts, credit notes, delivery notes and purchase orders alongside the existing invoices and quotations.

**Architecture:** A shared `DocumentType` enum drives the one printed sheet (`resources/views/invoices/templates/default.blade.php`) for every document type, replacing the current `$isQuotation` boolean. Payments and credit notes both settle against an invoice through one balance calculation, so `balance_due = total − payments − credits` and the invoice status is derived rather than hand-set. Delivery notes are generated from an invoice and render the same sheet with prices suppressed. Purchase orders point at a new `Supplier` entity so suppliers never contaminate client lists or revenue roll-ups.

**Tech Stack:** Laravel 12, PHP 8.3, Inertia v2 + React 19, Tailwind v4, Pest v3, dompdf (`barryvdh/laravel-dompdf`).

---

## Decisions already made

These were settled before planning. Do not revisit them mid-implementation.

| Decision | Choice |
|---|---|
| Purchase order counterparty | New `suppliers` table, mirroring `clients`. Not a `kind` column on clients. |
| Credit note effect | Reduces the invoice balance through the same ledger as payments. |
| Plan limits | Only purchase orders get a limit (`max_purchase_orders`). Receipts, credit notes and delivery notes derive from an invoice that already consumed an allowance. |
| Templates | New `type` values on `invoice_templates`, auto-provisioned on first use. No new template builder screens, no new prerequisite gates. |

## Conventions this codebase uses — read before writing code

These are established patterns in the existing invoice/quotation code. Follow them even where they differ from generic Laravel advice.

1. **Validation lives inline in controllers**, not in Form Requests. `app/Http/Requests/StoreQuotationRequest.php` exists but is an unused stub with `authorize(): false`. Real validation is `$request->validate([...])` inside the controller, because the rules close over a runtime `$companyId`. See `QuotationController::store()`. Match this. Do not create Form Request classes.
2. **Company scoping** is done with four private controller helpers copied per controller: `resolveCompanyId()`, `companyId()`, `guardPrerequisites()`, and `Rule::exists()` builders like `clientRule()` / `templateRule()`. See `app/Http/Controllers/QuotationController.php:29-100`.
3. **Casts** go in a `protected function casts(): array` method (as in `Quotation`), not the `$casts` property (as in the older `Invoice`).
4. **Money maths is tax-inclusive.** What you type on a line is what the customer pays. Discounts come off the gross, then tax is *carved out* by subtraction so subtotal + tax always reconciles exactly to total. Never multiply to get tax. See the docblock on `QuotationController::computeTotals()`.
5. **Inertia page paths are case-inconsistent and must be matched exactly**: `Quotations/Index`, `Quotations/Create`, but `Quotations/show` (lowercase). Mirror this for new modules.
6. **`FreezesExchangeRate`** trait pins the FX rate on create. Every new money-bearing document model uses it and needs `exchange_rate_to_base` + `exchange_rate_fetched_at` columns.
7. **Tests are Pest**, with shared setup helpers as global functions at the bottom of `tests/Pest.php`.
8. **Run `vendor/bin/pint --dirty`** before every commit.

## Naming contract

Every task below uses these exact names. If a later task references a name, it was defined in an earlier task.

**Tables:** `invoice_payments`, `credit_notes`, `credit_note_items`, `delivery_notes`, `delivery_note_items`, `suppliers`, `purchase_orders`, `purchase_order_items`

**Models:** `InvoicePayment`, `CreditNote`, `CreditNoteItem`, `DeliveryNote`, `DeliveryNoteItem`, `Supplier`, `PurchaseOrder`, `PurchaseOrderItem`

> `invoice_payments` is deliberately **not** `payments`. The `payments` table already exists and holds subscription billing (`app/Models/Payment.php`). Confusing the two would be a serious bug.

**Support classes:** `App\Enums\DocumentType`, `App\Contracts\RenderableDocument`, `App\Support\DocumentNumber`, `App\Services\TemplateProvisioner`, `App\Services\DocumentRenderer`, `App\Services\InvoiceSettlement`

**Number prefixes:** `INV`, `QUO`, `RCP`, `CRN`, `DN`, `PO` — all `PREFIX-000001`, six digits, sequential per company.

## File structure

### Phase 0 — Shared foundation
| File | Responsibility |
|---|---|
| `app/Enums/DocumentType.php` | Single source of truth for every per-type difference: wording, number prefix, second date, whether prices show, whether the counterparty is a supplier. |
| `app/Contracts/RenderableDocument.php` | The small interface the generic renderer needs from any document model. |
| `app/Support/DocumentNumber.php` | Per-company sequential numbering, shared by all six types. |
| `app/Services/TemplateProvisioner.php` | Returns a company's template for a type, creating a default instead of blocking. |
| `app/Services/DocumentRenderer.php` | Generic renderer for the four new types. Existing `InvoiceDocumentRenderer` / `QuotationDocumentRenderer` are left untouched. |
| `resources/views/invoices/templates/default.blade.php` | Modified: enum-driven instead of `$isQuotation`; suppresses price columns when the type says so. |

### Phase 1 — Payments & receipts
| File | Responsibility |
|---|---|
| `app/Models/InvoicePayment.php` | One payment against one invoice. Carries its own receipt number. |
| `app/Services/InvoiceSettlement.php` | Balance arithmetic and the derived invoice status. |
| `app/Http/Controllers/InvoicePaymentController.php` | Record, delete, and print a receipt. |
| `resources/js/pages/Invoices/show.tsx` | Modified: payment ledger, balance, record-payment form. |

### Phase 2 — Credit notes
| File | Responsibility |
|---|---|
| `app/Models/CreditNote.php`, `CreditNoteItem.php` | Credit against an invoice, with line items. |
| `app/Http/Controllers/CreditNoteController.php` | Full CRUD-ish flow mirroring `QuotationController`. |
| `resources/js/pages/CreditNotes/{Index,Create,show}.tsx` | Screens. |

### Phase 3 — Delivery notes
| File | Responsibility |
|---|---|
| `app/Models/DeliveryNote.php`, `DeliveryNoteItem.php` | Goods dispatched against an invoice. No money. |
| `app/Http/Controllers/DeliveryNoteController.php` | Generate from invoice, edit, print, sign-off. |
| `resources/js/pages/DeliveryNotes/{Index,show}.tsx` | Screens. |

### Phase 4 — Suppliers & purchase orders
| File | Responsibility |
|---|---|
| `app/Models/Supplier.php` | The counterparty for outbound orders. |
| `app/Models/PurchaseOrder.php`, `PurchaseOrderItem.php` | Standalone ordering document. |
| `app/Http/Controllers/SupplierController.php`, `PurchaseOrderController.php` | CRUD. |
| `resources/js/pages/Suppliers/Index.tsx`, `resources/js/pages/PurchaseOrders/{Index,Create,show}.tsx` | Screens. |

### Phase 5 — Wiring
Sidebar navigation, dashboard outstanding-balance correction, full-suite verification.

---

# Phase 0 — Shared foundation

Nothing user-visible ships in this phase, but every later phase depends on it. Do it first and do it completely.

## Task 0.1: The `DocumentType` enum

**Files:**
- Create: `app/Enums/DocumentType.php`
- Test: `tests/Unit/DocumentTypeTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/DocumentTypeTest.php`:

```php
<?php

use App\Enums\DocumentType;

it('gives every type a distinct number prefix', function () {
    $prefixes = array_map(
        fn (DocumentType $type) => $type->numberPrefix(),
        DocumentType::cases(),
    );

    expect($prefixes)->toBe(['INV', 'QUO', 'RCP', 'CRN', 'DN', 'PO'])
        ->and(array_unique($prefixes))->toHaveCount(6);
});

it('titles the printed sheet from the label', function () {
    expect(DocumentType::CreditNote->documentTitle())->toBe('CREDIT NOTE')
        ->and(DocumentType::Invoice->documentTitle())->toBe('INVOICE');
});

it('hides prices on delivery notes only', function () {
    $priceless = array_values(array_filter(
        DocumentType::cases(),
        fn (DocumentType $type) => ! $type->showsPrices(),
    ));

    expect($priceless)->toBe([DocumentType::DeliveryNote])
        ->and(DocumentType::Invoice->showsPrices())->toBeTrue();
});

it('routes only purchase orders to a supplier', function () {
    $supplierTypes = array_values(array_filter(
        DocumentType::cases(),
        fn (DocumentType $type) => $type->usesSupplier(),
    ));

    expect($supplierTypes)->toBe([DocumentType::PurchaseOrder]);
});

it('knows which column carries the second date', function () {
    expect(DocumentType::Invoice->secondDateField())->toBe('due_date')
        ->and(DocumentType::Quotation->secondDateField())->toBe('valid_until')
        ->and(DocumentType::DeliveryNote->secondDateField())->toBe('delivery_date')
        ->and(DocumentType::PurchaseOrder->secondDateField())->toBe('expected_date')
        ->and(DocumentType::Receipt->secondDateField())->toBeNull()
        ->and(DocumentType::CreditNote->secondDateField())->toBeNull();
});

it('pairs every second-date column with a label', function (DocumentType $type) {
    expect($type->secondDateLabel() === null)->toBe($type->secondDateField() === null);
})->with(DocumentType::cases());

it('labels the second date in the type\'s own words', function () {
    expect(DocumentType::Invoice->secondDateLabel())->toBe('Due')
        ->and(DocumentType::DeliveryNote->secondDateLabel())->toBe('Delivered');
});

it('addresses a delivery note to where the goods go', function () {
    expect(DocumentType::DeliveryNote->counterpartyLabel())->toBe('Deliver to:')
        ->and(DocumentType::Invoice->counterpartyLabel())->toBe('To:');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/DocumentTypeTest.php`
Expected: FAIL — `Class "App\Enums\DocumentType" not found`

- [ ] **Step 3: Write the enum**

Create `app/Enums/DocumentType.php`:

```php
<?php

namespace App\Enums;

/**
 * Every difference between the six document types Nilo issues.
 *
 * The printed sheet, the numbering and the prerequisite wording all read from
 * here, so adding a seventh type is a matter of adding a case rather than
 * hunting down conditionals across the controllers and the blade.
 */
enum DocumentType: string
{
    case Invoice = 'invoice';
    case Quotation = 'quotation';
    case Receipt = 'receipt';
    case CreditNote = 'credit_note';
    case DeliveryNote = 'delivery_note';
    case PurchaseOrder = 'purchase_order';

    /**
     * Lowercase wording for prose: "A {label} has to be addressed to someone."
     */
    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'invoice',
            self::Quotation => 'quotation',
            self::Receipt => 'receipt',
            self::CreditNote => 'credit note',
            self::DeliveryNote => 'delivery note',
            self::PurchaseOrder => 'purchase order',
        };
    }

    public function pluralLabel(): string
    {
        return $this->label().'s';
    }

    /**
     * The banner printed across the top of the sheet.
     */
    public function documentTitle(): string
    {
        return strtoupper($this->label());
    }

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Invoice => 'INV',
            self::Quotation => 'QUO',
            self::Receipt => 'RCP',
            self::CreditNote => 'CRN',
            self::DeliveryNote => 'DN',
            self::PurchaseOrder => 'PO',
        };
    }

    /**
     * The column holding this type's second date, or null when it only has an
     * issue date. Receipts are dated the day the money landed and credit notes
     * the day they were raised, so neither carries a second one.
     */
    public function secondDateField(): ?string
    {
        return match ($this) {
            self::Invoice => 'due_date',
            self::Quotation => 'valid_until',
            self::DeliveryNote => 'delivery_date',
            self::PurchaseOrder => 'expected_date',
            self::Receipt, self::CreditNote => null,
        };
    }

    public function secondDateLabel(): ?string
    {
        return match ($this) {
            self::Invoice => 'Due',
            self::Quotation => 'Valid until',
            self::DeliveryNote => 'Delivered',
            self::PurchaseOrder => 'Expected',
            self::Receipt, self::CreditNote => null,
        };
    }

    /**
     * A delivery note proves what arrived, not what it cost — showing money on
     * one leaks your margins to whoever signs for the goods.
     */
    public function showsPrices(): bool
    {
        return $this !== self::DeliveryNote;
    }

    /**
     * Whether the counterparty is a supplier rather than a client.
     */
    public function usesSupplier(): bool
    {
        return $this === self::PurchaseOrder;
    }

    public function counterpartyLabel(): string
    {
        return $this === self::DeliveryNote ? 'Deliver to:' : 'To:';
    }

    public function closingLine(): string
    {
        return match ($this) {
            self::Invoice => 'Thank you for your business. Kindly settle within due date.',
            self::Quotation => 'Thank you for the opportunity. This quotation is valid until the date shown above.',
            self::Receipt => 'Payment received with thanks.',
            self::CreditNote => 'This credit note has been applied to the invoice shown above.',
            self::DeliveryNote => 'Please check the goods on arrival and sign below to confirm receipt.',
            self::PurchaseOrder => 'Please confirm acceptance and quote this order number on your invoice.',
        };
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Unit/DocumentTypeTest.php`
Expected: PASS — 8 test blocks, 13 tests once the six-case dataset expands

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty
git add app/Enums/DocumentType.php tests/Unit/DocumentTypeTest.php
git commit -m "feat: add DocumentType enum describing all six document types"
```

---

## Task 0.2: Per-company document numbering

**Files:**
- Create: `app/Support/DocumentNumber.php`
- Test: `tests/Feature/DocumentNumberTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DocumentNumberTest.php`:

```php
<?php

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\Quotation;
use App\Support\DocumentNumber;

it('starts each company at one', function () {
    $company = Company::factory()->create();

    expect(DocumentNumber::nextFor(Quotation::class, $company->id, DocumentType::Quotation))
        ->toBe('QUO-000001');
});

it('numbers sequentially within a company', function () {
    $company = Company::factory()->create();

    Quotation::query()->create([
        'company_id' => $company->id,
        'client_id' => App\Models\Client::factory()->create(['company_id' => $company->id])->id,
        'number' => 'QUO-000001',
        'issue_date' => '2026-07-01',
        'currency_code' => 'ZMW',
        'status' => 'draft',
    ]);

    expect(DocumentNumber::nextFor(Quotation::class, $company->id, DocumentType::Quotation))
        ->toBe('QUO-000002');
});

it('keeps sequences independent per company', function () {
    $first = Company::factory()->create();
    $second = Company::factory()->create();

    Quotation::query()->create([
        'company_id' => $first->id,
        'client_id' => App\Models\Client::factory()->create(['company_id' => $first->id])->id,
        'number' => 'QUO-000001',
        'issue_date' => '2026-07-01',
        'currency_code' => 'ZMW',
        'status' => 'draft',
    ]);

    expect(DocumentNumber::nextFor(Quotation::class, $second->id, DocumentType::Quotation))
        ->toBe('QUO-000001');
});

it('uses the prefix of the type it is given', function () {
    $company = Company::factory()->create();

    expect(DocumentNumber::nextFor(Quotation::class, $company->id, DocumentType::PurchaseOrder))
        ->toBe('PO-000001');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/DocumentNumberTest.php`
Expected: FAIL — `Class "App\Support\DocumentNumber" not found`

- [ ] **Step 3: Write the helper**

Create `app/Support/DocumentNumber.php`:

```php
<?php

namespace App\Support;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Sequential per-company document numbers.
 *
 * This counts existing rows rather than holding a sequence table, matching how
 * invoices and quotations have always numbered themselves. Callers must run it
 * inside the same transaction as the insert.
 */
class DocumentNumber
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function nextFor(string $modelClass, int $companyId, DocumentType $type): string
    {
        $sequence = $modelClass::query()
            ->where('company_id', $companyId)
            ->count() + 1;

        return $type->numberPrefix().'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/DocumentNumberTest.php`
Expected: PASS, 4 tests

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty
git add app/Support/DocumentNumber.php tests/Feature/DocumentNumberTest.php
git commit -m "feat: add shared per-company document numbering"
```

---

## Task 0.3: Template auto-provisioning

`DocumentPrerequisites` currently refuses to let a company create a document until a template of that exact type exists. Applying that to four more types would mean a user has to build four more templates before issuing a single receipt. Instead, provision a default on demand.

**Files:**
- Create: `app/Services/TemplateProvisioner.php`
- Test: `tests/Feature/TemplateProvisioningTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TemplateProvisioningTest.php`:

```php
<?php

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Services\TemplateProvisioner;

it('creates a default template the first time a type is used', function () {
    $company = Company::factory()->create();

    $template = app(TemplateProvisioner::class)->forCompany($company->id, DocumentType::Receipt);

    expect($template->exists)->toBeTrue()
        ->and($template->company_id)->toBe($company->id)
        ->and($template->type)->toBe('receipt')
        ->and($template->is_default)->toBeTrue()
        ->and($template->name)->toBe('Default receipt');
});

it('reuses the provisioned template on the next call', function () {
    $company = Company::factory()->create();
    $provisioner = app(TemplateProvisioner::class);

    $first = $provisioner->forCompany($company->id, DocumentType::Receipt);
    $second = $provisioner->forCompany($company->id, DocumentType::Receipt);

    expect($second->id)->toBe($first->id)
        ->and(InvoiceTemplate::query()->where('company_id', $company->id)->count())->toBe(1);
});

it('prefers a template the company already marked default', function () {
    $company = Company::factory()->create();

    InvoiceTemplate::create([
        'company_id' => $company->id,
        'name' => 'Plain',
        'type' => 'credit_note',
        'is_default' => false,
        'settings' => [],
    ]);

    $chosen = InvoiceTemplate::create([
        'company_id' => $company->id,
        'name' => 'Branded',
        'type' => 'credit_note',
        'is_default' => true,
        'settings' => [],
    ]);

    expect(app(TemplateProvisioner::class)->forCompany($company->id, DocumentType::CreditNote)->id)
        ->toBe($chosen->id);
});

it('honours an explicitly chosen template of the right type', function () {
    $company = Company::factory()->create();

    InvoiceTemplate::create([
        'company_id' => $company->id,
        'name' => 'Default',
        'type' => 'purchase_order',
        'is_default' => true,
        'settings' => [],
    ]);

    $picked = InvoiceTemplate::create([
        'company_id' => $company->id,
        'name' => 'Alternate',
        'type' => 'purchase_order',
        'is_default' => false,
        'settings' => [],
    ]);

    expect(app(TemplateProvisioner::class)
        ->forCompany($company->id, DocumentType::PurchaseOrder, $picked->id)->id)
        ->toBe($picked->id);
});

it('ignores a chosen template belonging to another company', function () {
    $company = Company::factory()->create();
    $other = Company::factory()->create();

    $foreign = InvoiceTemplate::create([
        'company_id' => $other->id,
        'name' => 'Theirs',
        'type' => 'purchase_order',
        'is_default' => true,
        'settings' => [],
    ]);

    $resolved = app(TemplateProvisioner::class)
        ->forCompany($company->id, DocumentType::PurchaseOrder, $foreign->id);

    expect($resolved->company_id)->toBe($company->id)
        ->and($resolved->id)->not->toBe($foreign->id);
});

it('ignores a chosen template of a different document type', function () {
    $company = Company::factory()->create();

    $invoiceTemplate = InvoiceTemplate::create([
        'company_id' => $company->id,
        'name' => 'Invoice one',
        'type' => 'invoice',
        'is_default' => true,
        'settings' => [],
    ]);

    $resolved = app(TemplateProvisioner::class)
        ->forCompany($company->id, DocumentType::DeliveryNote, $invoiceTemplate->id);

    expect($resolved->type)->toBe('delivery_note')
        ->and($resolved->id)->not->toBe($invoiceTemplate->id);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/TemplateProvisioningTest.php`
Expected: FAIL — `Target class [App\Services\TemplateProvisioner] does not exist.`

- [ ] **Step 3: Write the service**

Create `app/Services/TemplateProvisioner.php`:

```php
<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\InvoiceTemplate;

/**
 * Resolves the template a document should print through, creating a default
 * when the company has none.
 *
 * Invoices and quotations gate on a template existing, because designing one is
 * part of setting those up. The derived documents do not: making somebody build
 * a delivery note template before they can dispatch goods is a wall, not a
 * feature. They start on the house default and can be styled later through the
 * existing builder.
 */
class TemplateProvisioner
{
    public function forCompany(int $companyId, DocumentType $type, ?int $templateId = null): InvoiceTemplate
    {
        if ($templateId) {
            $chosen = InvoiceTemplate::query()
                ->where('id', $templateId)
                ->where('company_id', $companyId)
                ->where('type', $type->value)
                ->first();

            if ($chosen) {
                return $chosen;
            }
        }

        $existing = InvoiceTemplate::query()
            ->where('company_id', $companyId)
            ->where('type', $type->value)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return InvoiceTemplate::query()->create([
            'company_id' => $companyId,
            'name' => 'Default '.$type->label(),
            'type' => $type->value,
            'is_default' => true,
            'settings' => [],
        ]);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/TemplateProvisioningTest.php`
Expected: PASS, 6 tests

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty
git add app/Services/TemplateProvisioner.php tests/Feature/TemplateProvisioningTest.php
git commit -m "feat: auto-provision document templates instead of blocking"
```

---

## Task 0.4: Drive the printed sheet from `DocumentType`

The blade branches on a hardcoded `$isQuotation` boolean in five places. Replace that with the enum so four more types do not mean four more booleans, and add price suppression.

**Files:**
- Modify: `resources/views/invoices/templates/default.blade.php:62-76`, `:628-631`, `:640-660`, `:684-700`
- Test: `tests/Feature/DocumentSheetRenderingTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DocumentSheetRenderingTest.php`:

```php
<?php

use App\Enums\DocumentType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\InvoiceTemplate;
use Illuminate\Support\Facades\View;

/**
 * Renders the shared sheet directly, without going through a controller, so the
 * blade's own type handling is what is under test.
 */
function renderSheet(DocumentType $type, array $overrides = []): string
{
    return renderSheetWithRawType($type, $overrides);
}

/**
 * Same, but accepts whatever the caller wants to put in `documentType` — an
 * enum, a legacy string, or nothing at all.
 */
function renderSheetWithRawType(mixed $type, array $overrides = []): string
{
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'Acme Ltd']);

    return View::make('invoices.templates.default', [
        'company' => $company,
        'client' => $client,
        'template' => new InvoiceTemplate(['settings' => []]),
        'currency' => Currency::query()->where('code', 'ZMW')->first(),
        'invoice' => array_merge([
            'number' => 'TEST-000001',
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-31',
            'valid_until' => '2026-07-31',
            'delivery_date' => '2026-07-05',
            'expected_date' => '2026-08-15',
            'subtotal' => 4310.34,
            'tax_total' => 689.66,
            'total' => 5000.00,
            'notes' => null,
            'terms' => null,
        ], $overrides),
        'items' => [
            ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 2500, 'line_total' => 5000],
        ],
        'settings' => app(App\Services\InvoiceDocumentRenderer::class)->normalizedSettings(null),
        'documentType' => $type,
        'mode' => 'preview',
    ])->render();
}

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

it('titles the sheet for every document type', function (DocumentType $type, string $title) {
    expect(renderSheet($type))->toContain('>'.$title.'<');
})->with([
    'invoice' => [DocumentType::Invoice, 'INVOICE'],
    'quotation' => [DocumentType::Quotation, 'QUOTATION'],
    'receipt' => [DocumentType::Receipt, 'RECEIPT'],
    'credit note' => [DocumentType::CreditNote, 'CREDIT NOTE'],
    'delivery note' => [DocumentType::DeliveryNote, 'DELIVERY NOTE'],
    'purchase order' => [DocumentType::PurchaseOrder, 'PURCHASE ORDER'],
]);

it('labels the second date from the type', function () {
    expect(renderSheet(DocumentType::Invoice))->toContain('Due: 2026-07-31')
        ->and(renderSheet(DocumentType::Quotation))->toContain('Valid until: 2026-07-31')
        ->and(renderSheet(DocumentType::DeliveryNote))->toContain('Delivered: 2026-07-05')
        ->and(renderSheet(DocumentType::PurchaseOrder))->toContain('Expected: 2026-08-15');
});

it('omits the second date row for types that have none', function () {
    expect(renderSheet(DocumentType::Receipt))
        ->not->toContain('Due:')
        ->not->toContain('Valid until:');
});

it('hides every money column on a delivery note', function () {
    $html = renderSheet(DocumentType::DeliveryNote);

    expect($html)
        ->not->toContain('GRAND TOTAL')
        ->not->toContain('Sub Total')
        ->not->toContain('2,500.00')
        ->not->toContain('5,000.00')
        ->and($html)->toContain('Consulting');
});

it('still shows money on every other type', function () {
    expect(renderSheet(DocumentType::Invoice))->toContain('GRAND TOTAL')
        ->and(renderSheet(DocumentType::PurchaseOrder))->toContain('GRAND TOTAL')
        ->and(renderSheet(DocumentType::CreditNote))->toContain('GRAND TOTAL');
});

it('addresses a delivery note to where the goods go', function () {
    expect(renderSheet(DocumentType::DeliveryNote))->toContain('Deliver to:')
        ->and(renderSheet(DocumentType::Invoice))->toContain('To:');
});

it('closes with wording matched to the type', function () {
    expect(renderSheet(DocumentType::Receipt))->toContain('Payment received with thanks.')
        ->and(renderSheet(DocumentType::PurchaseOrder))->toContain('quote this order number');
});

it('still accepts the legacy string document type', function () {
    /**
     * InvoiceDocumentRenderer and QuotationDocumentRenderer are untouched by
     * this plan and still pass 'quotation' as a plain string. The sheet must
     * keep understanding that, or every existing quotation breaks.
     */
    $html = renderSheetWithRawType('quotation');

    expect($html)->toContain('>QUOTATION<')->toContain('Valid until:');
});

it('falls back to an invoice for an unrecognised type', function () {
    expect(renderSheetWithRawType('nonsense'))->toContain('>INVOICE<');
});

it('falls back to an invoice when no type is given', function () {
    expect(renderSheetWithRawType(null))->toContain('>INVOICE<');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/DocumentSheetRenderingTest.php`
Expected: FAIL — the sheet renders `INVOICE` for the new types and shows totals on the delivery note.

- [ ] **Step 3: Replace the type-handling block in the blade**

In `resources/views/invoices/templates/default.blade.php`, replace lines 62-76 (the block starting `$documentType  = (string)($documentType ?? 'invoice');` and ending with the `$closingLine` assignment) with:

```php
        /**
         * Every document Nilo issues renders through this one sheet. The type
         * supplies the wording, the second date and whether money is shown, so
         * a new type never means a new copy of the whole layout.
         */
        $type = $documentType instanceof \App\Enums\DocumentType
            ? $documentType
            : (\App\Enums\DocumentType::tryFrom((string)($documentType ?? 'invoice'))
                ?? \App\Enums\DocumentType::Invoice);

        $documentNoun    = $type->label();
        $documentTitle   = $type->documentTitle();
        $showPrices      = $type->showsPrices();
        $closingLine     = $type->closingLine();
        $secondDateLabel = $type->secondDateLabel();

        $issueDate = $isArray ? ($invoice['issue_date'] ?? null) : ($invoice->issue_date ?? null);

        $secondDateField = $type->secondDateField();
        $secondDate = $secondDateField === null
            ? null
            : ($isArray ? ($invoice[$secondDateField] ?? null) : ($invoice->{$secondDateField} ?? null));
```

`$isQuotation` is now gone. The remaining references are fixed in the next steps.

- [ ] **Step 4: Update the header meta block**

Replace lines 628-631 (the `doc-meta` div contents) with:

```blade
                            <div>{{ ucfirst($documentNoun) }} No: {{ $invoiceNumber }}</div>
                            <div>Date: {{ $issueDate ?? '—' }}</div>
                            @if($secondDateLabel !== null)
                                <div>{{ $secondDateLabel }}: {{ $secondDate ?? '—' }}</div>
                            @endif
```

- [ ] **Step 5: Gate the summary block and relabel the counterparty**

In the `row-top` block, change the `label-accent` line from `<div class="label-accent">To:</div>` to:

```blade
                    <div class="label-accent">{{ $type->counterpartyLabel() }}</div>
```

Then wrap the whole `<div class="summary">…</div>` element (the Sub Total / VAT / GRAND TOTAL block) in a price guard:

```blade
                @if($showPrices)
                    <div class="summary">
                        <div class="summary-row" style="margin-top:0;">
                            <span>Sub Total</span>
                            <strong>{{ $money($subtotalFinal) }}</strong>
                        </div>

                        <div class="summary-row">
                            <span>VAT</span>
                            <strong>{{ $money($vatFinal) }}</strong>
                        </div>

                        <div class="grand">
                            <span>GRAND TOTAL</span>
                            <span>{{ $money($grandFinal) }}</span>
                        </div>
                    </div>
                @endif
```

- [ ] **Step 6: Gate the money columns in the items table**

Replace the `thead` block and the `trow` money cells so the Price and Total columns disappear when prices are hidden:

```blade
                <div class="thead">
                    <div>Item Description</div>
                    @if($showPrices)
                        <div class="num">Price</div>
                    @endif
                    <div class="num">Qty</div>
                    @if($showPrices)
                        <div class="num">Total</div>
                    @endif
                </div>

                @foreach($itemsComputed as $it)
                    <div class="trow">
                        <div>
                            <div class="desc">{{ $it['desc'] ?: '—' }}</div>
                        </div>
                        @if($showPrices)
                            <div class="num">{{ number_format((float)$it['price'], $precision, '.', ',') }}</div>
                        @endif
                        <div class="num">{{ number_format((float)$it['qty'], 2, '.', ',') }}</div>
                        @if($showPrices)
                            <div class="num" style="font-weight:800;">{{ number_format((float)$it['total'], $precision, '.', ',') }}</div>
                        @endif
                    </div>
```

Note this also removes the placeholder `<div class="desc-sub">Contrary to popular belief Lorem ipsum simply random.</div>` line that is currently printed under every line item on every real document. That is lorem ipsum shipping to customers — it should not survive v1.

- [ ] **Step 7: Fix the grid columns for the priceless layout**

The `.thead` and `.trow` CSS use a four-column grid. Find the `.thead{` rule near line 448 and add a two-column variant immediately after the existing rules, then apply it. In the CSS block add:

```css
        .table.no-prices .thead,
        .table.no-prices .trow{ grid-template-columns: 1fr 120px; }
```

And on the items table wrapper, add the class:

```blade
            <div class="table {{ $showPrices ? '' : 'no-prices' }} {{ $tableStyle === 'striped' ? 'striped' : ($tableStyle === 'lined' ? 'lined' : 'clean') }}">
```

- [ ] **Step 8: Fix the page title**

Replace line 6 with:

```blade
    <title>{{ ($documentType instanceof \App\Enums\DocumentType ? $documentType : (\App\Enums\DocumentType::tryFrom((string)($documentType ?? 'invoice')) ?? \App\Enums\DocumentType::Invoice))->documentTitle() }} {{ data_get($invoice, 'number', '') }}</title>
```

- [ ] **Step 9: Run the new test plus every existing document test**

Run: `php artisan test tests/Feature/DocumentSheetRenderingTest.php tests/Feature/QuotationCreationTest.php tests/Feature/InvoiceTaxTotalsTest.php tests/Feature/InvoicePreviewEmbedTest.php tests/Feature/InvoiceTemplateHeaderLayoutTest.php tests/Unit/InvoicePrintControlsTest.php`
Expected: PASS. The existing quotation tests assert `>QUOTATION<` and `Valid until:`, which the enum still produces — that is the regression guard for this refactor.

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty
git add resources/views/invoices/templates/default.blade.php tests/Feature/DocumentSheetRenderingTest.php
git commit -m "refactor: drive the printed sheet from DocumentType"
```

---

## Task 0.5: Generic document renderer

**Files:**
- Create: `app/Contracts/RenderableDocument.php`
- Create: `app/Services/DocumentRenderer.php`
- Test: `tests/Feature/DocumentRendererTest.php` (written in Task 1.5, once a model implements the interface)

`InvoiceDocumentRenderer` and `QuotationDocumentRenderer` stay exactly as they are. They work, they are covered by tests, and rewriting them during a v1 push buys risk instead of value. The four new types share one generic renderer instead of getting four more near-copies. Collapsing all six into `DocumentRenderer` is a worthwhile follow-up after v1 ships.

- [ ] **Step 1: Write the contract**

Create `app/Contracts/RenderableDocument.php`:

```php
<?php

namespace App\Contracts;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the shared renderer needs from a document model. Keeping it this small
 * means a new document type only has to say what it is and who it is for.
 */
interface RenderableDocument
{
    public function documentType(): DocumentType;

    /**
     * The client or supplier the document is addressed to.
     */
    public function counterparty(): ?Model;

    /**
     * The line items to print, in display order.
     *
     * @return Collection<int, Model>
     */
    public function printableItems(): Collection;

    /**
     * The company template this document should print through, if one was chosen.
     */
    public function chosenTemplateId(): ?int;
}
```

- [ ] **Step 2: Write the renderer**

Create `app/Services/DocumentRenderer.php`:

```php
<?php

namespace App\Services;

use App\Contracts\RenderableDocument;
use App\Models\Currency;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;

/**
 * Renders any document implementing {@see RenderableDocument} through the
 * shared sheet, so the preview, the print view and the attached PDF cannot
 * drift apart.
 */
class DocumentRenderer
{
    private const TEMPLATE_VIEW = 'invoices.templates.default';

    public function __construct(
        private TemplateProvisioner $templates,
        private InvoiceDocumentRenderer $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(RenderableDocument&Model $document, string $mode): array
    {
        $type = $document->documentType();

        $template = $this->templates->forCompany(
            (int) $document->company_id,
            $type,
            $document->chosenTemplateId(),
        );

        return [
            'company' => $document->company,
            'client' => $document->counterparty(),
            'template' => $template,
            'currency' => Currency::query()
                ->where('code', $document->currency_code)
                ->first(),
            'invoice' => $document,
            'items' => $document->printableItems(),
            'settings' => $this->settings->normalizedSettings($template),
            'documentType' => $type,
            'mode' => $mode,
        ];
    }

    public function html(RenderableDocument&Model $document, string $mode = 'preview'): string
    {
        return View::make(self::TEMPLATE_VIEW, $this->viewData($document, $mode))->render();
    }

    /**
     * Raw PDF bytes, rendered in `pdf` mode so the on-screen toolbar is omitted.
     */
    public function pdf(RenderableDocument&Model $document): string
    {
        return Pdf::loadHTML($this->html($document, 'pdf'))
            ->setPaper('a4')
            ->output();
    }

    public function filename(RenderableDocument&Model $document): string
    {
        $reference = $document->number
            ?: $document->documentType()->label().'-'.$document->getKey();

        return preg_replace('/[^A-Za-z0-9_\-]+/', '-', $reference).'.pdf';
    }
}
```

Note the blade reads `$client->name`, `$client->address`, `$client->email` and `$client->contact_person`. `Supplier` (Task 4.1) carries all four columns with the same names, so a purchase order renders through the same `client` view key without the blade knowing the difference.

- [ ] **Step 3: Confirm nothing broke**

There is no behaviour to test yet — the first consumer arrives in Task 1.5, which is where `tests/Feature/DocumentRendererTest.php` gets written. Just check the container can build it.

Run: `php artisan test tests/Feature/TemplateProvisioningTest.php`
Expected: PASS, 6 tests

- [ ] **Step 4: Format and commit**

```bash
vendor/bin/pint --dirty
git add app/Contracts/RenderableDocument.php app/Services/DocumentRenderer.php
git commit -m "feat: add generic renderer for the new document types"
```

---

# Phase 1 — Payments and receipts

The invoice currently has a `paid` status and nothing behind it. This phase adds the ledger: who paid, how much, when, by what method — and derives the invoice status from the arithmetic instead of trusting a hand-set field. A receipt is the printed form of a payment, not a separate entity.

## Task 1.1: The `invoice_payments` table

**Files:**
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_create_invoice_payments_table.php`

- [ ] **Step 1: Generate the migration**

```bash
php artisan make:migration create_invoice_payments_table --no-interaction
```

- [ ] **Step 2: Write the schema**

Replace the generated file's contents:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money received against an invoice.
     *
     * Deliberately not called `payments` — that table already holds Nilo's own
     * subscription billing. This one is the customer-facing ledger.
     */
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('receipt_number')->nullable();

            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3);

            $table->date('paid_on');
            $table->string('method'); // cash|bank_transfer|mobile_money|cheque|card|other
            $table->string('reference')->nullable(); // cheque no, transaction id

            /**
             * No `notes` column by design. The printed receipt's notes line is
             * computed from the remaining balance (see InvoicePayment), and a
             * stored column would let a caller shadow that figure.
             */

            $table->decimal('exchange_rate_to_base', 18, 8)->nullable();
            $table->timestamp('exchange_rate_fetched_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'paid_on']);
            $table->index(['invoice_id', 'paid_on']);
            $table->unique(['company_id', 'receipt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
```

- [ ] **Step 3: Run the migration**

Run: `php artisan migrate`
Expected: `INFO  Running migrations.` followed by the `create_invoice_payments_table` line marked `DONE`

- [ ] **Step 4: Commit**

```bash
vendor/bin/pint --dirty
git add database/migrations
git commit -m "feat: add invoice_payments table"
```

---

## Task 1.2: Add `partially_paid` to the invoice lifecycle

An invoice with 3,000 of 5,000 paid is neither `pending` nor `paid`. Without a third state the dashboard either double-counts it as fully outstanding or drops it entirely.

**Files:**
- Modify: `app/Models/Invoice.php:16-27`
- Test: `tests/Feature/InvoiceOutstandingStatusTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/InvoiceOutstandingStatusTest.php`:

```php
<?php

use App\Models\Invoice;

it('counts a partially paid invoice as outstanding', function () {
    expect(Invoice::OUTSTANDING_STATUSES)->toContain('partially_paid');
});

it('treats every unsettled status as money still owed', function () {
    $invoice = new Invoice(['status' => 'partially_paid']);

    expect($invoice->isOutstanding())->toBeTrue();
});

it('does not treat a paid or void invoice as outstanding', function () {
    expect((new Invoice(['status' => 'paid']))->isOutstanding())->toBeFalse()
        ->and((new Invoice(['status' => 'void']))->isOutstanding())->toBeFalse();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/InvoiceOutstandingStatusTest.php`
Expected: FAIL — `Failed asserting that array contains 'partially_paid'`

- [ ] **Step 3: Extend the status constants**

In `app/Models/Invoice.php`, replace the `STATUS_PAID` constant and `OUTSTANDING_STATUSES` block with:

```php
    public const STATUS_PAID = 'paid';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_VOID = 'void';

    /**
     * Statuses that represent money the company is still owed.
     *
     * An invoice leaves `pending` the moment it is emailed to the client, so
     * outstanding money cannot be identified by a single status — doing that
     * drops the amount out of every roll-up as soon as the invoice is sent.
     * A partly settled invoice is still owed for whatever remains.
     *
     * @var list<string>
     */
    public const OUTSTANDING_STATUSES = ['pending', 'sent', 'partially_paid', 'overdue'];
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/InvoiceOutstandingStatusTest.php`
Expected: PASS, 3 tests

- [ ] **Step 5: Check nothing downstream regressed**

Run: `php artisan test tests/Feature/DashboardTest.php tests/Feature/DashboardSentInvoiceTest.php tests/Feature/CurrencyRollupTest.php tests/Feature/DashboardCurrencyConversionTest.php`
Expected: PASS

- [ ] **Step 6: Allow the new status through the invoice status endpoint**

In `app/Http/Controllers/InvoiceController.php`, find the `updateStatus` method's `Rule::in([...])` list and add `'partially_paid'` to it, matching whatever statuses are already listed.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/Invoice.php app/Http/Controllers/InvoiceController.php tests/Feature/InvoiceOutstandingStatusTest.php
git commit -m "feat: add partially_paid to the invoice lifecycle"
```

---

## Task 1.3: The `InvoicePayment` model

**Files:**
- Create: `app/Models/InvoicePayment.php`
- Create: `database/factories/InvoicePaymentFactory.php`
- Test: `tests/Feature/InvoicePaymentModelTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/InvoicePaymentModelTest.php`:

```php
<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;

it('belongs to an invoice and a company', function () {
    $payment = InvoicePayment::factory()->create();

    expect($payment->invoice)->toBeInstanceOf(Invoice::class)
        ->and($payment->company)->toBeInstanceOf(Company::class);
});

it('casts money and dates', function () {
    $payment = InvoicePayment::factory()->create([
        'amount' => 1234.5,
        'paid_on' => '2026-07-15',
    ]);

    expect((float) $payment->fresh()->amount)->toBe(1234.50)
        ->and($payment->fresh()->paid_on->toDateString())->toBe('2026-07-15');
});

it('freezes the exchange rate when it is created', function () {
    $payment = InvoicePayment::factory()->create();

    /** The trait leaves the columns null when the FX feed has nothing. */
    expect($payment->getAttributes())->toHaveKey('exchange_rate_to_base');
});

it('lists the payment methods it accepts', function () {
    expect(InvoicePayment::METHODS)->toBe([
        'cash', 'bank_transfer', 'mobile_money', 'cheque', 'card', 'other',
    ]);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/InvoicePaymentModelTest.php`
Expected: FAIL — `Class "App\Models\InvoicePayment" not found`

- [ ] **Step 3: Create the model**

```bash
php artisan make:model InvoicePayment --no-interaction
```

Replace `app/Models/InvoicePayment.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\FreezesExchangeRate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment received against one invoice.
 *
 * The receipt a client is given is this row printed through the shared sheet,
 * so there is no separate receipt entity to keep in step.
 */
class InvoicePayment extends Model
{
    /** @use HasFactory<\Database\Factories\InvoicePaymentFactory> */
    use FreezesExchangeRate, HasFactory;

    /**
     * @var list<string>
     */
    public const METHODS = ['cash', 'bank_transfer', 'mobile_money', 'cheque', 'card', 'other'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'invoice_id',
        'recorded_by',
        'receipt_number',
        'amount',
        'currency_code',
        'paid_on',
        'method',
        'reference',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'exchange_rate_to_base' => 'float',
            'exchange_rate_fetched_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Human wording for the printed receipt.
     */
    public function methodLabel(): string
    {
        return match ($this->method) {
            'bank_transfer' => 'Bank transfer',
            'mobile_money' => 'Mobile money',
            default => ucfirst((string) $this->method),
        };
    }
}
```

- [ ] **Step 4: Create the factory**

Create `database/factories/InvoicePaymentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoicePayment>
 */
class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoice = Invoice::factory();

        return [
            'company_id' => Company::factory(),
            'invoice_id' => $invoice,
            'recorded_by' => null,
            'receipt_number' => 'RCP-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'currency_code' => 'ZMW',
            'paid_on' => fake()->date(),
            'method' => fake()->randomElement(InvoicePayment::METHODS),
            'reference' => null,
            'notes' => null,
        ];
    }

    /**
     * Ties the payment to an existing invoice, matching its company and currency.
     */
    public function forInvoice(Invoice $invoice, ?float $amount = null): static
    {
        return $this->state(fn () => [
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'currency_code' => $invoice->currency_code,
            'amount' => $amount ?? (float) $invoice->total,
        ]);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/InvoicePaymentModelTest.php`
Expected: PASS, 4 tests

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/InvoicePayment.php database/factories/InvoicePaymentFactory.php tests/Feature/InvoicePaymentModelTest.php
git commit -m "feat: add InvoicePayment model and factory"
```

---

## Task 1.4: The settlement calculation

This is the heart of the phase. Everything else in Phases 1 and 2 hangs off it.

**Files:**
- Create: `app/Services/InvoiceSettlement.php`
- Modify: `app/Models/Invoice.php` (add the `payments` relation)
- Test: `tests/Feature/InvoiceSettlementTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/InvoiceSettlementTest.php`:

```php
<?php

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\InvoiceSettlement;

/**
 * An invoice for exactly 5,000 in the active company, so every assertion below
 * reads as plain arithmetic.
 */
function invoiceForSettlement(float $total = 5000.0, string $status = 'sent'): Invoice
{
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    return Invoice::query()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'invoice_template_id' => $template->id,
        'number' => 'INV-000001',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-31',
        'currency_code' => 'ZMW',
        'subtotal' => $total,
        'total' => $total,
        'status' => $status,
    ]);
}

it('reports nothing paid on a fresh invoice', function () {
    $invoice = invoiceForSettlement();
    $settlement = app(InvoiceSettlement::class);

    expect($settlement->amountPaid($invoice))->toBe(0.0)
        ->and($settlement->balanceDue($invoice))->toBe(5000.0);
});

it('subtracts a part payment from the balance', function () {
    $invoice = invoiceForSettlement();
    InvoicePayment::factory()->forInvoice($invoice, 2000)->create();

    $settlement = app(InvoiceSettlement::class);

    expect($settlement->amountPaid($invoice->fresh()))->toBe(2000.0)
        ->and($settlement->balanceDue($invoice->fresh()))->toBe(3000.0);
});

it('adds multiple payments together', function () {
    $invoice = invoiceForSettlement();
    InvoicePayment::factory()->forInvoice($invoice, 2000)->create();
    InvoicePayment::factory()->forInvoice($invoice, 1500)->create();

    expect(app(InvoiceSettlement::class)->amountPaid($invoice->fresh()))->toBe(3500.0);
});

it('never reports a negative balance', function () {
    $invoice = invoiceForSettlement();
    InvoicePayment::factory()->forInvoice($invoice, 6000)->create();

    expect(app(InvoiceSettlement::class)->balanceDue($invoice->fresh()))->toBe(0.0);
});

it('moves an invoice to partially paid on a part payment', function () {
    $invoice = invoiceForSettlement();
    InvoicePayment::factory()->forInvoice($invoice, 2000)->create();

    app(InvoiceSettlement::class)->sync($invoice);

    expect($invoice->fresh()->status)->toBe('partially_paid');
});

it('moves an invoice to paid when the balance clears', function () {
    $invoice = invoiceForSettlement();
    InvoicePayment::factory()->forInvoice($invoice, 5000)->create();

    app(InvoiceSettlement::class)->sync($invoice);

    expect($invoice->fresh()->status)->toBe('paid');
});

it('returns an invoice to sent when its only payment is removed', function () {
    $invoice = invoiceForSettlement();
    $payment = InvoicePayment::factory()->forInvoice($invoice, 5000)->create();

    $settlement = app(InvoiceSettlement::class);
    $settlement->sync($invoice);
    expect($invoice->fresh()->status)->toBe('paid');

    $payment->delete();
    $settlement->sync($invoice->fresh());

    expect($invoice->fresh()->status)->toBe('sent');
});

it('leaves a voided invoice alone', function () {
    $invoice = invoiceForSettlement(status: 'void');
    InvoicePayment::factory()->forInvoice($invoice, 5000)->create();

    app(InvoiceSettlement::class)->sync($invoice);

    expect($invoice->fresh()->status)->toBe('void');
});

it('rounds to two places so a cent never blocks settlement', function () {
    $invoice = invoiceForSettlement(333.33);
    InvoicePayment::factory()->forInvoice($invoice, 333.33)->create();

    app(InvoiceSettlement::class)->sync($invoice);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(app(InvoiceSettlement::class)->balanceDue($invoice->fresh()))->toBe(0.0);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/InvoiceSettlementTest.php`
Expected: FAIL — `Target class [App\Services\InvoiceSettlement] does not exist.`

- [ ] **Step 3: Add the payments relation to `Invoice`**

In `app/Models/Invoice.php`, add alongside the existing `items()` relation:

```php
    /**
     * Money received against this invoice, newest first.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderByDesc('paid_on');
    }
```

Add `use Illuminate\Database\Eloquent\Relations\HasMany;` to the imports.

- [ ] **Step 4: Write the settlement service**

Create `app/Services/InvoiceSettlement.php`:

```php
<?php

namespace App\Services;

use App\Models\Invoice;

/**
 * What an invoice is still owed, and the status that follows from it.
 *
 * Status is derived here rather than set by hand anywhere a payment is touched,
 * so cash and credit can never disagree about whether an invoice is settled.
 * Credit notes join this calculation in Phase 2 through {@see amountCredited()}.
 */
class InvoiceSettlement
{
    /**
     * Statuses this service refuses to overwrite. A voided invoice is a closed
     * book — recording money against it must not quietly reopen it.
     *
     * @var list<string>
     */
    private const TERMINAL_STATUSES = [Invoice::STATUS_VOID];

    public function amountPaid(Invoice $invoice): float
    {
        return round((float) $invoice->payments()->sum('amount'), 2);
    }

    /**
     * Credits raised against this invoice. Always zero until Phase 2 lands the
     * credit_notes table.
     */
    public function amountCredited(Invoice $invoice): float
    {
        return 0.0;
    }

    public function balanceDue(Invoice $invoice): float
    {
        $settled = $this->amountPaid($invoice) + $this->amountCredited($invoice);

        return round(max(0, (float) $invoice->total - $settled), 2);
    }

    /**
     * Rewrites the invoice status from the ledger.
     *
     * `sent` rather than `pending` is the resting state for an unsettled
     * invoice that has had money on it, because anything with a payment
     * against it has demonstrably reached the client.
     */
    public function sync(Invoice $invoice): void
    {
        if (in_array($invoice->status, self::TERMINAL_STATUSES, true)) {
            return;
        }

        $balance = $this->balanceDue($invoice);
        $settled = $this->amountPaid($invoice) + $this->amountCredited($invoice);

        $status = match (true) {
            $balance <= 0.0 => Invoice::STATUS_PAID,
            $settled > 0.0 => Invoice::STATUS_PARTIALLY_PAID,
            default => 'sent',
        };

        if ($invoice->status !== $status) {
            $invoice->update(['status' => $status]);
        }
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/InvoiceSettlementTest.php`
Expected: PASS, 9 tests

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/InvoiceSettlement.php app/Models/Invoice.php tests/Feature/InvoiceSettlementTest.php
git commit -m "feat: derive invoice settlement status from the payment ledger"
```

---

## Task 1.5: Make a payment printable as a receipt

**Files:**
- Modify: `app/Models/InvoicePayment.php` (implement `RenderableDocument`)
- Test: `tests/Feature/DocumentRendererTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DocumentRendererTest.php`:

```php
<?php

use App\Enums\DocumentType;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\DocumentRenderer;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

function paymentForRendering(float $total = 5000.0, float $amount = 2000.0): InvoicePayment
{
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $invoice = Invoice::query()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'invoice_template_id' => $template->id,
        'number' => 'INV-000042',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-31',
        'currency_code' => 'ZMW',
        'subtotal' => $total,
        'total' => $total,
        'status' => 'sent',
    ]);

    return InvoicePayment::factory()->forInvoice($invoice, $amount)->create([
        'receipt_number' => 'RCP-000001',
        'method' => 'mobile_money',
        'paid_on' => '2026-07-15',
    ]);
}

it('renders a payment as a receipt', function () {
    $html = app(DocumentRenderer::class)->html(paymentForRendering());

    expect($html)->toContain('>RECEIPT<')
        ->toContain('RCP-000001')
        ->toContain('Payment received with thanks.');
});

it('names the invoice the money was received against', function () {
    expect(app(DocumentRenderer::class)->html(paymentForRendering()))
        ->toContain('INV-000042');
});

it('prints the amount received rather than the invoice total', function () {
    $html = app(DocumentRenderer::class)->html(paymentForRendering(5000, 2000));

    expect($html)->toContain('2,000.00');
});

it('shows the balance still outstanding on a part payment', function () {
    $html = app(DocumentRenderer::class)->html(paymentForRendering(5000, 2000));

    expect($html)->toContain('3,000.00');
});

it('provisions a receipt template on first render', function () {
    $payment = paymentForRendering();

    app(DocumentRenderer::class)->html($payment);

    expect(App\Models\InvoiceTemplate::query()
        ->where('company_id', $payment->company_id)
        ->where('type', 'receipt')
        ->exists())->toBeTrue();
});

it('names the downloaded file after the receipt number', function () {
    expect(app(DocumentRenderer::class)->filename(paymentForRendering()))
        ->toBe('RCP-000001.pdf');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/DocumentRendererTest.php`
Expected: FAIL — `App\Models\InvoicePayment` does not implement `App\Contracts\RenderableDocument`

- [ ] **Step 3: Implement the contract on `InvoicePayment`**

A receipt has no line items of its own, so it prints one synthetic line naming the invoice being settled. That reuses the whole sheet rather than forking a second layout for a one-line document.

Add to `app/Models/InvoicePayment.php` — change the class declaration to `class InvoicePayment extends Model implements RenderableDocument` and add these imports and methods:

```php
use App\Contracts\RenderableDocument;
use App\Enums\DocumentType;
use App\Services\InvoiceSettlement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
```

```php
    public function documentType(): DocumentType
    {
        return DocumentType::Receipt;
    }

    public function counterparty(): ?Model
    {
        return $this->invoice?->client;
    }

    /**
     * A receipt has no lines of its own — it prints one row naming the invoice
     * the money settled, which keeps it on the shared sheet instead of needing
     * a second layout for a single figure.
     *
     * @return Collection<int, Model>
     */
    public function printableItems(): Collection
    {
        $this->loadMissing('invoice');

        return new Collection([
            new InvoiceItem([
                'description' => 'Payment for invoice '.($this->invoice?->number ?? '—')
                    .' ('.$this->methodLabel().')',
                'quantity' => 1,
                'unit_price' => (float) $this->amount,
                'line_total' => (float) $this->amount,
            ]),
        ]);
    }

    public function chosenTemplateId(): ?int
    {
        return null;
    }

    /**
     * The sheet reads these four keys off whatever it is handed. A receipt is
     * worth the amount received, not the invoice total.
     */
    public function getNumberAttribute(): ?string
    {
        return $this->receipt_number;
    }

    public function getIssueDateAttribute(): mixed
    {
        return $this->paid_on;
    }

    public function getSubtotalAttribute(): float
    {
        return (float) $this->amount;
    }

    public function getTaxTotalAttribute(): float
    {
        return 0.0;
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->amount;
    }

    /**
     * Printed under the line so the client can see what is left to pay.
     */
    public function getNotesAttribute(): ?string
    {
        $this->loadMissing('invoice');

        if (! $this->invoice) {
            return null;
        }

        $balance = app(InvoiceSettlement::class)->balanceDue($this->invoice);

        return $balance > 0
            ? 'Balance remaining on invoice '.$this->invoice->number.': '
                .number_format($balance, 2, '.', ',').' '.$this->currency_code
            : 'Invoice '.$this->invoice->number.' is settled in full.';
    }

    public function getTermsAttribute(): ?string
    {
        return null;
    }
```

Because `paid_on` and `amount` are real columns while `issue_date`, `total`, `number`, `notes` and `terms` are not, none of these accessors collides with a column or a cast. This is why Task 1.1 deliberately omits a `notes` column and Task 1.3 keeps it out of `$fillable`.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/DocumentRendererTest.php`
Expected: PASS, 6 tests

- [ ] **Step 5: Re-run the model test, which the accessors could have broken**

Run: `php artisan test tests/Feature/InvoicePaymentModelTest.php tests/Feature/InvoiceSettlementTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/InvoicePayment.php tests/Feature/DocumentRendererTest.php
git commit -m "feat: render an invoice payment as a printable receipt"
```

---

## Task 1.6: Recording and deleting payments

**Files:**
- Create: `app/Http/Controllers/InvoicePaymentController.php`
- Modify: `routes/web.php` (inside the `invoices` prefix group)
- Test: `tests/Feature/InvoicePaymentRecordingTest.php`

- [ ] **Step 1: Add the Pest helper**

At the bottom of `tests/Pest.php`, add:

```php
/**
 * An invoice worth `$total` in the acting user's active company, ready to be
 * paid against.
 *
 * @return array{0: \App\Models\User, 1: \App\Models\Invoice}
 */
function payableInvoiceContext(float $total = 5000.0): array
{
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $invoice = App\Models\Invoice::query()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'invoice_template_id' => $template->id,
        'number' => 'INV-000001',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-31',
        'currency_code' => 'ZMW',
        'subtotal' => $total,
        'total' => $total,
        'status' => 'sent',
    ]);

    return [$user, $invoice];
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/InvoicePaymentRecordingTest.php`:

```php
<?php

use App\Models\Currency;
use App\Models\InvoicePayment;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

it('records a payment against an invoice', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post("/invoices/{$invoice->id}/payments", [
            'amount' => 2000,
            'paid_on' => '2026-07-15',
            'method' => 'mobile_money',
            'reference' => 'TXN-991',
        ])
        ->assertSessionHasNoErrors();

    $payment = InvoicePayment::query()->latest('id')->first();

    expect((float) $payment->amount)->toBe(2000.0)
        ->and($payment->invoice_id)->toBe($invoice->id)
        ->and($payment->company_id)->toBe($invoice->company_id)
        ->and($payment->currency_code)->toBe('ZMW')
        ->and($payment->recorded_by)->toBe($user->id);
});

it('numbers receipts sequentially within a company', function () {
    [$user, $invoice] = payableInvoiceContext();

    foreach ([1000, 1000] as $amount) {
        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
            'amount' => $amount,
            'paid_on' => '2026-07-15',
            'method' => 'cash',
        ]);
    }

    expect(InvoicePayment::query()->orderBy('id')->pluck('receipt_number')->all())
        ->toBe(['RCP-000001', 'RCP-000002']);
});

it('moves the invoice to partially paid', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 2000,
        'paid_on' => '2026-07-15',
        'method' => 'cash',
    ]);

    expect($invoice->fresh()->status)->toBe('partially_paid');
});

it('moves the invoice to paid when the balance clears', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 5000,
        'paid_on' => '2026-07-15',
        'method' => 'bank_transfer',
    ]);

    expect($invoice->fresh()->status)->toBe('paid');
});

it('refuses a payment larger than the balance due', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post("/invoices/{$invoice->id}/payments", [
            'amount' => 6000,
            'paid_on' => '2026-07-15',
            'method' => 'cash',
        ])
        ->assertSessionHasErrors('amount');

    expect(InvoicePayment::query()->count())->toBe(0);
});

it('refuses a payment that would overshoot after an earlier one', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 4000, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ]);

    $this->actingAs($user)
        ->post("/invoices/{$invoice->id}/payments", [
            'amount' => 1500, 'paid_on' => '2026-07-16', 'method' => 'cash',
        ])
        ->assertSessionHasErrors('amount');

    expect(InvoicePayment::query()->count())->toBe(1);
});

it('rejects a payment method it does not know', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post("/invoices/{$invoice->id}/payments", [
            'amount' => 100, 'paid_on' => '2026-07-15', 'method' => 'barter',
        ])
        ->assertSessionHasErrors('method');
});

it('rejects a zero or negative payment', function (float $amount) {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post("/invoices/{$invoice->id}/payments", [
            'amount' => $amount, 'paid_on' => '2026-07-15', 'method' => 'cash',
        ])
        ->assertSessionHasErrors('amount');
})->with([0, -50]);

it('refuses to pay an invoice in another company', function () {
    [$user] = payableInvoiceContext();
    [, $foreignInvoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post("/invoices/{$foreignInvoice->id}/payments", [
            'amount' => 100, 'paid_on' => '2026-07-15', 'method' => 'cash',
        ])
        ->assertForbidden();
});

it('reverses the status when a payment is deleted', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 5000, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ]);

    expect($invoice->fresh()->status)->toBe('paid');

    $payment = InvoicePayment::query()->latest('id')->first();

    $this->actingAs($user)
        ->delete("/invoices/{$invoice->id}/payments/{$payment->id}")
        ->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe('sent')
        ->and(InvoicePayment::query()->count())->toBe(0);
});

it('prints a receipt for a recorded payment', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 2000, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ]);

    $payment = InvoicePayment::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/invoices/{$invoice->id}/payments/{$payment->id}/print")
        ->assertSuccessful()
        ->assertSee('RECEIPT', false);
});

it('will not print a receipt belonging to another company', function () {
    [$user] = payableInvoiceContext();
    [$otherUser, $foreignInvoice] = payableInvoiceContext();

    $this->actingAs($otherUser)->post("/invoices/{$foreignInvoice->id}/payments", [
        'amount' => 100, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ]);

    $foreignPayment = InvoicePayment::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/invoices/{$foreignInvoice->id}/payments/{$foreignPayment->id}/print")
        ->assertForbidden();
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test tests/Feature/InvoicePaymentRecordingTest.php`
Expected: FAIL — 404, the route does not exist

- [ ] **Step 4: Write the controller**

```bash
php artisan make:controller InvoicePaymentController --no-interaction
```

Replace `app/Http/Controllers/InvoicePaymentController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\DocumentRenderer;
use App\Services\InvoiceSettlement;
use App\Support\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoicePaymentController extends Controller
{
    public function __construct(
        private InvoiceSettlement $settlement,
        private DocumentRenderer $documents,
    ) {}

    private function resolveCompanyId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $companyId = (int) ($user->current_company_id ?? 0);

        if ($companyId && $user->isMemberOfCompany($companyId)) {
            return $companyId;
        }

        $fallbackCompanyId = (int) ($user->companies()
            ->orderBy('companies.name')
            ->value('companies.id') ?? 0);

        if ($fallbackCompanyId) {
            $user->forceFill(['current_company_id' => $fallbackCompanyId])->save();
        }

        return $fallbackCompanyId ?: null;
    }

    private function companyId(Request $request): int
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No active company selected.',
            ]);
        }

        return $companyId;
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $invoice->company_id === $companyId, 403);

        $balance = $this->settlement->balanceDue($invoice);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$balance],
            'paid_on' => ['required', 'date'],
            'method' => ['required', Rule::in(InvoicePayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:190'],
        ], [
            'amount.max' => 'That is more than the '.number_format($balance, 2)
                .' still outstanding on this invoice.',
        ]);

        DB::transaction(function () use ($request, $invoice, $companyId, $data): void {
            InvoicePayment::query()->create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'recorded_by' => $request->user()?->id,
                'receipt_number' => DocumentNumber::nextFor(
                    InvoicePayment::class,
                    $companyId,
                    DocumentType::Receipt,
                ),
                'amount' => (float) $data['amount'],
                'currency_code' => $invoice->currency_code,
                'paid_on' => $data['paid_on'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
            ]);

            $this->settlement->sync($invoice->fresh());
        });

        return back()->with('success', 'Payment recorded.');
    }

    public function destroy(Request $request, Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $invoice->company_id === $companyId, 403);
        abort_unless((int) $payment->invoice_id === (int) $invoice->id, 403);

        DB::transaction(function () use ($invoice, $payment): void {
            $payment->delete();
            $this->settlement->sync($invoice->fresh());
        });

        return back()->with('success', 'Payment removed.');
    }

    public function print(Request $request, Invoice $invoice, InvoicePayment $payment)
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $invoice->company_id === $companyId, 403);
        abort_unless((int) $payment->invoice_id === (int) $invoice->id, 403);

        return response()->view(
            'invoices.templates.default',
            $this->documents->viewData($payment, 'print') + [
                'autoPrint' => $request->boolean('download'),
            ]
        );
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, inside the existing `Route::prefix('invoices')->name('invoices.')->group(...)`, add after the `print` route:

```php
        Route::post('/{invoice}/payments', [\App\Http\Controllers\InvoicePaymentController::class, 'store'])
            ->whereNumber('invoice')
            ->name('payments.store');

        Route::delete('/{invoice}/payments/{payment}', [\App\Http\Controllers\InvoicePaymentController::class, 'destroy'])
            ->whereNumber(['invoice', 'payment'])
            ->name('payments.destroy');

        Route::get('/{invoice}/payments/{payment}/print', [\App\Http\Controllers\InvoicePaymentController::class, 'print'])
            ->whereNumber(['invoice', 'payment'])
            ->name('payments.print');
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test tests/Feature/InvoicePaymentRecordingTest.php`
Expected: PASS — 12 test blocks, 13 tests with the dataset expanded

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/InvoicePaymentController.php routes/web.php tests/Pest.php tests/Feature/InvoicePaymentRecordingTest.php
git commit -m "feat: record, remove and print invoice payments"
```

---

## Task 1.7: Show the ledger on the invoice page

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php` (the `show` method)
- Modify: `resources/js/pages/Invoices/show.tsx`
- Test: `tests/Feature/InvoicePaymentPagePropsTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/InvoicePaymentPagePropsTest.php`:

```php
<?php

use App\Models\Currency;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

it('sends the balance and an empty ledger for an unpaid invoice', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->get("/invoices/{$invoice->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Invoices/show')
            ->where('invoice.amount_paid', 0)
            ->where('invoice.balance_due', 5000)
            ->has('invoice.payments', 0)
        );
});

it('sends each recorded payment with its receipt number', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 2000, 'paid_on' => '2026-07-15', 'method' => 'mobile_money',
    ]);

    $this->actingAs($user)
        ->get("/invoices/{$invoice->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('invoice.amount_paid', 2000)
            ->where('invoice.balance_due', 3000)
            ->has('invoice.payments', 1)
            ->where('invoice.payments.0.receipt_number', 'RCP-000001')
            ->where('invoice.payments.0.amount', 2000)
            ->where('invoice.payments.0.method', 'mobile_money')
        );
});

it('sends the payment methods the form offers', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->get("/invoices/{$invoice->id}")
        ->assertInertia(fn ($page) => $page->has('paymentMethods', 6));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/InvoicePaymentPagePropsTest.php`
Expected: FAIL — `Property [invoice.amount_paid] does not exist.`

- [ ] **Step 3: Extend the controller's `show` payload**

In `app/Http/Controllers/InvoiceController.php`, inject the settlement service into the constructor alongside the existing renderer:

```php
    public function __construct(
        private InvoiceDocumentRenderer $documents,
        private InvoiceSettlement $settlement,
    ) {}
```

Add the imports `use App\Models\InvoicePayment;` and `use App\Services\InvoiceSettlement;`.

In `show()`, add `'payments.recorder:id,name'` to the `$invoice->load([...])` call, and add these keys to the `invoice` array in the Inertia payload:

```php
                'amount_paid' => $this->settlement->amountPaid($invoice),
                'balance_due' => $this->settlement->balanceDue($invoice),

                'payments' => $invoice->payments->map(fn (InvoicePayment $payment) => [
                    'id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'amount' => (float) $payment->amount,
                    'paid_on' => $payment->paid_on?->toDateString(),
                    'method' => $payment->method,
                    'method_label' => $payment->methodLabel(),
                    'reference' => $payment->reference,
                    'recorded_by' => $payment->recorder?->name,
                ]),
```

And add a sibling prop next to `invoice`:

```php
            'paymentMethods' => collect(InvoicePayment::METHODS)
                ->map(fn (string $method) => [
                    'value' => $method,
                    'label' => (new InvoicePayment(['method' => $method]))->methodLabel(),
                ])
                ->all(),
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/InvoicePaymentPagePropsTest.php`
Expected: PASS, 3 tests

- [ ] **Step 5: Add the ledger UI**

In `resources/js/pages/Invoices/show.tsx`, extend the `Invoice` type with the new fields and add a payments panel. Add to the type definition:

```tsx
type InvoicePaymentRow = {
    id: number;
    receipt_number: string | null;
    amount: number;
    paid_on: string | null;
    method: string;
    method_label: string;
    reference?: string | null;
    recorded_by?: string | null;
};

type PaymentMethod = { value: string; label: string };
```

Add to the `Invoice` type:

```tsx
    amount_paid: number;
    balance_due: number;
    payments: InvoicePaymentRow[];
```

Change the component signature to accept the new prop:

```tsx
export default function InvoiceShow({
    invoice,
    paymentMethods,
    justCreated = false,
}: {
    invoice: Invoice;
    paymentMethods: PaymentMethod[];
    justCreated?: boolean;
}) {
```

Add this panel below the existing totals panel, using the same `Panel` / `PanelHeader` / `SoftTile` / `PillButton` primitives the page already imports from `@/components/dashboard/primitives`:

```tsx
            <Panel>
                <PanelHeader
                    title="Payments"
                    subtitle={
                        invoice.balance_due > 0
                            ? 'Outstanding balance on this invoice'
                            : 'This invoice is settled in full'
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <SoftTile label="Paid to date">
                        <Money amount={invoice.amount_paid} currency={invoice.currency_code} />
                    </SoftTile>
                    <SoftTile label="Balance due">
                        <Money amount={invoice.balance_due} currency={invoice.currency_code} />
                    </SoftTile>
                </div>

                {invoice.payments.length === 0 ? (
                    <p className="mt-4 text-sm text-muted-foreground">
                        No payments recorded yet.
                    </p>
                ) : (
                    <ul className="mt-4 flex flex-col gap-3">
                        {invoice.payments.map((payment) => (
                            <li
                                key={payment.id}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-muted/40 px-4 py-3"
                            >
                                <div className="flex flex-col">
                                    <span className="font-medium">
                                        {payment.receipt_number ?? 'Receipt'}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {payment.paid_on} · {payment.method_label}
                                        {payment.reference ? ` · ${payment.reference}` : ''}
                                    </span>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Money
                                        amount={payment.amount}
                                        currency={invoice.currency_code}
                                    />
                                    <PillButton
                                        onClick={() =>
                                            window.open(
                                                `/invoices/${invoice.id}/payments/${payment.id}/print`,
                                                '_blank',
                                            )
                                        }
                                    >
                                        <Printer className="size-4" />
                                        Receipt
                                    </PillButton>
                                    <PillButton
                                        onClick={() => {
                                            if (
                                                !window.confirm(
                                                    'Remove this payment? The invoice balance will go back up.',
                                                )
                                            ) {
                                                return;
                                            }
                                            router.delete(
                                                `/invoices/${invoice.id}/payments/${payment.id}`,
                                                { preserveScroll: true },
                                            );
                                        }}
                                    >
                                        Remove
                                    </PillButton>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {invoice.balance_due > 0 && (
                    <RecordPaymentForm
                        invoiceId={invoice.id}
                        balanceDue={invoice.balance_due}
                        currency={invoice.currency_code}
                        methods={paymentMethods}
                    />
                )}
            </Panel>
```

Add the form component in the same file, above the default export:

```tsx
function RecordPaymentForm({
    invoiceId,
    balanceDue,
    currency,
    methods,
}: {
    invoiceId: number;
    balanceDue: number;
    currency: string;
    methods: PaymentMethod[];
}) {
    return (
        <Form
            action={`/invoices/${invoiceId}/payments`}
            method="post"
            resetOnSuccess
            options={{ preserveScroll: true }}
            className="mt-6 grid gap-3 sm:grid-cols-4"
        >
            {({ errors, processing }) => (
                <>
                    <label className="flex flex-col gap-1 text-sm">
                        <span className="text-muted-foreground">Amount ({currency})</span>
                        <input
                            type="number"
                            name="amount"
                            step="0.01"
                            min="0.01"
                            max={balanceDue}
                            defaultValue={balanceDue}
                            className="rounded-lg border px-3 py-2"
                        />
                        {errors.amount && (
                            <span className="text-xs text-destructive">{errors.amount}</span>
                        )}
                    </label>

                    <label className="flex flex-col gap-1 text-sm">
                        <span className="text-muted-foreground">Date received</span>
                        <input
                            type="date"
                            name="paid_on"
                            defaultValue={new Date().toISOString().slice(0, 10)}
                            className="rounded-lg border px-3 py-2"
                        />
                        {errors.paid_on && (
                            <span className="text-xs text-destructive">{errors.paid_on}</span>
                        )}
                    </label>

                    <label className="flex flex-col gap-1 text-sm">
                        <span className="text-muted-foreground">Method</span>
                        <select name="method" className="rounded-lg border px-3 py-2">
                            {methods.map((method) => (
                                <option key={method.value} value={method.value}>
                                    {method.label}
                                </option>
                            ))}
                        </select>
                        {errors.method && (
                            <span className="text-xs text-destructive">{errors.method}</span>
                        )}
                    </label>

                    <label className="flex flex-col gap-1 text-sm">
                        <span className="text-muted-foreground">Reference</span>
                        <input
                            type="text"
                            name="reference"
                            placeholder="Txn or cheque no."
                            className="rounded-lg border px-3 py-2"
                        />
                    </label>

                    <div className="sm:col-span-4">
                        <PillButton type="submit" disabled={processing}>
                            {processing ? 'Recording…' : 'Record payment'}
                        </PillButton>
                    </div>
                </>
            )}
        </Form>
    );
}
```

Add `Form` to the `@inertiajs/react` import at the top of the file.

- [ ] **Step 6: Build the frontend and check it compiles**

Run: `npm run build`
Expected: build completes with no TypeScript errors

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/InvoiceController.php resources/js/pages/Invoices/show.tsx tests/Feature/InvoicePaymentPagePropsTest.php
git commit -m "feat: show the payment ledger and balance on the invoice page"
```

---

## Task 1.8: Correct the dashboard's outstanding figure

The dashboard sums invoice **totals** for outstanding statuses. With part payments in play that overstates what is owed — a 5,000 invoice with 4,000 paid must contribute 1,000, not 5,000.

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Test: `tests/Feature/DashboardOutstandingBalanceTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DashboardOutstandingBalanceTest.php`:

```php
<?php

use App\Models\Currency;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

it('counts only the unpaid part of a partly settled invoice', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 4000, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('stats.outstanding_total', 1000.0));
});

it('drops a fully settled invoice out of the outstanding figure', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 5000, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('stats.outstanding_total', 0.0));
});

it('counts the whole total when nothing has been paid', function () {
    [$user] = payableInvoiceContext();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('stats.outstanding_total', 5000.0));
});
```

Before implementing, open `app/Http/Controllers/DashboardController.php` and find the actual prop name used for the outstanding roll-up. If it is not `stats.outstanding_total`, change the three assertions above to the real key rather than renaming the prop — the dashboard's existing tests depend on it.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/DashboardOutstandingBalanceTest.php`
Expected: FAIL — outstanding total reports 5000.0 where 1000.0 was expected

- [ ] **Step 3: Subtract settled money from the roll-up**

In `app/Http/Controllers/DashboardController.php`, find the query that sums `total` over `Invoice::outstanding()` and subtract the payments each invoice has received. Use a `withSum` so this stays one query:

```php
$outstandingTotal = Invoice::query()
    ->where('company_id', $companyId)
    ->outstanding()
    ->withSum('payments as paid_sum', 'amount')
    ->get(['id', 'total', 'currency_code'])
    ->sum(fn (Invoice $invoice) => max(
        0,
        (float) $invoice->total - (float) ($invoice->paid_sum ?? 0),
    ));
```

If the dashboard converts currencies through `CurrencyRollup` or `CurrencyConverter`, apply the same conversion to the per-invoice remainder rather than to the raw total — the conversion must happen after the subtraction, not before.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/DashboardOutstandingBalanceTest.php`
Expected: PASS, 3 tests

- [ ] **Step 5: Confirm the existing dashboard tests still pass**

Run: `php artisan test tests/Feature/DashboardTest.php tests/Feature/DashboardSentInvoiceTest.php tests/Feature/DashboardFreshnessTest.php tests/Feature/DashboardCurrencyConversionTest.php tests/Feature/CurrencyRollupTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/DashboardController.php tests/Feature/DashboardOutstandingBalanceTest.php
git commit -m "fix: exclude settled money from the dashboard outstanding total"
```

---

# Phase 2 — Credit notes

Correcting a sent invoice currently means voiding it, which destroys the audit trail. A credit note references the original invoice, carries its own line items, and reduces the balance through the same `InvoiceSettlement` calculation that payments use.

## Task 2.1: The credit note tables

**Files:**
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_create_credit_notes_table.php`
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_create_credit_note_items_table.php`

- [ ] **Step 1: Generate both migrations**

```bash
php artisan make:migration create_credit_notes_table --no-interaction
php artisan make:migration create_credit_note_items_table --no-interaction
```

- [ ] **Step 2: Write the credit_notes schema**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A credit raised against an invoice.
     *
     * `invoice_id` is required: a credit note that floats free of the invoice
     * it corrects cannot be reconciled, which is the whole reason it exists.
     */
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('credit_note_template_id')->nullable()
                ->constrained('invoice_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('number')->nullable();
            $table->string('reference')->nullable();
            $table->string('title')->nullable();
            $table->string('reason')->nullable();

            $table->date('issue_date');
            $table->string('currency_code', 3);

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('credit_note_discount', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('tax_percent', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->string('status')->default('draft'); // draft|issued|void

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->decimal('exchange_rate_to_base', 18, 8)->nullable();
            $table->timestamp('exchange_rate_fetched_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'issue_date']);
            $table->index(['invoice_id', 'status']);
            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
```

- [ ] **Step 3: Write the credit_note_items schema**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();

            $table->string('description');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 14, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['credit_note_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
    }
};
```

- [ ] **Step 4: Run the migrations**

Run: `php artisan migrate`
Expected: both migrations marked `DONE`

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add database/migrations
git commit -m "feat: add credit_notes and credit_note_items tables"
```

---

## Task 2.2: The credit note models

**Files:**
- Create: `app/Models/CreditNote.php`, `app/Models/CreditNoteItem.php`
- Create: `database/factories/CreditNoteFactory.php`
- Test: `tests/Feature/CreditNoteModelTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CreditNoteModelTest.php`:

```php
<?php

use App\Enums\DocumentType;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;

it('belongs to the invoice it credits', function () {
    $note = CreditNote::factory()->create();

    expect($note->invoice)->toBeInstanceOf(Invoice::class)
        ->and($note->client)->toBeInstanceOf(Client::class);
});

it('renders as a credit note', function () {
    expect(CreditNote::factory()->create()->documentType())->toBe(DocumentType::CreditNote);
});

it('addresses itself to the client', function () {
    $note = CreditNote::factory()->create();

    expect($note->counterparty()->id)->toBe($note->client_id);
});

it('only counts toward the invoice once it is issued', function () {
    expect(CreditNote::APPLIED_STATUSES)->toBe(['issued']);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/CreditNoteModelTest.php`
Expected: FAIL — `Class "App\Models\CreditNote" not found`

- [ ] **Step 3: Create the models**

```bash
php artisan make:model CreditNote --no-interaction
php artisan make:model CreditNoteItem --no-interaction
```

Replace `app/Models/CreditNote.php`:

```php
<?php

namespace App\Models;

use App\Contracts\RenderableDocument;
use App\Enums\DocumentType;
use App\Models\Concerns\FreezesExchangeRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A credit raised against an invoice.
 *
 * Correcting an invoice by voiding it destroys the record of what was billed.
 * A credit note leaves the original standing and books the correction against
 * it, which is both what auditors expect and what keeps the balance honest.
 */
class CreditNote extends Model implements RenderableDocument
{
    /** @use HasFactory<\Database\Factories\CreditNoteFactory> */
    use FreezesExchangeRate, HasFactory;

    /**
     * Statuses whose value actually comes off the invoice. A draft is still
     * being written and must not move anybody's balance.
     *
     * @var list<string>
     */
    public const APPLIED_STATUSES = ['issued'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'client_id',
        'invoice_id',
        'credit_note_template_id',
        'created_by',
        'number',
        'reference',
        'title',
        'reason',
        'issue_date',
        'currency_code',
        'subtotal',
        'discount_total',
        'credit_note_discount',
        'tax_total',
        'tax_percent',
        'total',
        'status',
        'notes',
        'terms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'credit_note_discount' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'total' => 'decimal:2',
            'exchange_rate_to_base' => 'float',
            'exchange_rate_fetched_at' => 'datetime',
        ];
    }

    /**
     * Credit notes whose value has been applied to an invoice.
     */
    public function scopeApplied(Builder $query): Builder
    {
        return $query->whereIn('status', self::APPLIED_STATUSES);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InvoiceTemplate::class, 'credit_note_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class)->orderBy('sort_order');
    }

    public function documentType(): DocumentType
    {
        return DocumentType::CreditNote;
    }

    public function counterparty(): ?Model
    {
        return $this->client;
    }

    /**
     * @return Collection<int, Model>
     */
    public function printableItems(): Collection
    {
        return $this->items()->get();
    }

    public function chosenTemplateId(): ?int
    {
        return $this->credit_note_template_id;
    }
}
```

Replace `app/Models/CreditNoteItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'credit_note_id',
        'description',
        'unit',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'line_total',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }
}
```

- [ ] **Step 4: Create the factory**

Create `database/factories/CreditNoteFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'client_id' => Client::factory(),
            'invoice_id' => Invoice::factory(),
            'number' => 'CRN-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'issue_date' => fake()->date(),
            'currency_code' => 'ZMW',
            'subtotal' => 1000,
            'total' => 1000,
            'status' => 'draft',
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => ['status' => 'issued']);
    }

    /**
     * A credit against a specific invoice, matching its company and client.
     */
    public function forInvoice(Invoice $invoice, float $total): static
    {
        return $this->state(fn () => [
            'company_id' => $invoice->company_id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'currency_code' => $invoice->currency_code,
            'subtotal' => $total,
            'total' => $total,
        ]);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/CreditNoteModelTest.php`
Expected: PASS, 4 tests

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/CreditNote.php app/Models/CreditNoteItem.php database/factories/CreditNoteFactory.php tests/Feature/CreditNoteModelTest.php
git commit -m "feat: add CreditNote and CreditNoteItem models"
```

---

## Task 2.3: Apply credits to the invoice balance

This is where the Phase 1 stub `amountCredited()` becomes real.

**Files:**
- Modify: `app/Services/InvoiceSettlement.php`
- Modify: `app/Models/Invoice.php` (add the `creditNotes` relation)
- Test: `tests/Feature/CreditNoteSettlementTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CreditNoteSettlementTest.php`:

```php
<?php

use App\Models\CreditNote;
use App\Models\Currency;
use App\Models\InvoicePayment;
use App\Services\InvoiceSettlement;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

it('reduces the balance by an issued credit note', function () {
    [, $invoice] = payableInvoiceContext();
    CreditNote::factory()->forInvoice($invoice, 2000)->issued()->create();

    $settlement = app(InvoiceSettlement::class);

    expect($settlement->amountCredited($invoice))->toBe(2000.0)
        ->and($settlement->balanceDue($invoice))->toBe(3000.0);
});

it('ignores a draft credit note', function () {
    [, $invoice] = payableInvoiceContext();
    CreditNote::factory()->forInvoice($invoice, 2000)->create();

    expect(app(InvoiceSettlement::class)->amountCredited($invoice))->toBe(0.0)
        ->and(app(InvoiceSettlement::class)->balanceDue($invoice))->toBe(5000.0);
});

it('settles an invoice with cash and credit together', function () {
    [, $invoice] = payableInvoiceContext();

    InvoicePayment::factory()->forInvoice($invoice, 3000)->create();
    CreditNote::factory()->forInvoice($invoice, 2000)->issued()->create();

    $settlement = app(InvoiceSettlement::class);
    $settlement->sync($invoice);

    expect($settlement->balanceDue($invoice->fresh()))->toBe(0.0)
        ->and($invoice->fresh()->status)->toBe('paid');
});

it('marks an invoice partially paid when only a credit has landed', function () {
    [, $invoice] = payableInvoiceContext();
    CreditNote::factory()->forInvoice($invoice, 1000)->issued()->create();

    app(InvoiceSettlement::class)->sync($invoice);

    expect($invoice->fresh()->status)->toBe('partially_paid');
});

it('adds several credit notes together', function () {
    [, $invoice] = payableInvoiceContext();

    CreditNote::factory()->forInvoice($invoice, 1000)->issued()->create();
    CreditNote::factory()->forInvoice($invoice, 1500)->issued()->create();

    expect(app(InvoiceSettlement::class)->amountCredited($invoice))->toBe(2500.0);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/CreditNoteSettlementTest.php`
Expected: FAIL — `amountCredited()` returns 0.0 where 2000.0 was expected

- [ ] **Step 3: Add the relation to `Invoice`**

In `app/Models/Invoice.php`, add next to `payments()`:

```php
    /**
     * Credits raised against this invoice, newest first.
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->orderByDesc('issue_date');
    }
```

- [ ] **Step 4: Implement `amountCredited`**

In `app/Services/InvoiceSettlement.php`, replace the stub with:

```php
    /**
     * Credits raised against this invoice. Drafts are excluded — a credit note
     * that is still being written has not been given to anyone and must not
     * move the balance.
     */
    public function amountCredited(Invoice $invoice): float
    {
        return round((float) $invoice->creditNotes()->applied()->sum('total'), 2);
    }
```

Add `use App\Models\CreditNote;` to the imports if static analysis wants it; the query goes through the relation so no direct reference is required.

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/CreditNoteSettlementTest.php tests/Feature/InvoiceSettlementTest.php`
Expected: PASS — the Phase 1 settlement tests still pass because they create no credit notes

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/InvoiceSettlement.php app/Models/Invoice.php tests/Feature/CreditNoteSettlementTest.php
git commit -m "feat: apply issued credit notes to the invoice balance"
```

---

## Task 2.4: The credit note controller

Mirrors `QuotationController` closely: the same four private helpers, the same tax-inclusive `computeTotals`, the same index/create/store/show/preview/print surface. The one addition is a guard that credits cannot exceed what the invoice is still owed.

**Files:**
- Create: `app/Http/Controllers/CreditNoteController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CreditNoteCreationTest.php`

- [ ] **Step 1: Add the Pest helper**

At the bottom of `tests/Pest.php`, add:

```php
/**
 * @return array<string, mixed>
 */
function creditNotePayload(App\Models\Invoice $invoice, float $unitPrice = 1000, string $status = 'issued'): array
{
    return [
        'invoice_id' => $invoice->id,
        'issue_date' => '2026-07-15',
        'currency_code' => 'ZMW',
        'status' => $status,
        'reason' => 'Goods returned',
        'credit_note_discount' => 0,
        'tax_percent' => 0,
        'items' => [
            [
                'description' => 'Returned consulting hours',
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'discount' => 0,
            ],
        ],
    ];
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/CreditNoteCreationTest.php`:

```php
<?php

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Currency;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

/* ---------------------------- Creation ---------------------------- */

it('creates a credit note against an invoice', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($invoice, 2000))
        ->assertSessionHasNoErrors();

    $note = CreditNote::query()->latest('id')->first();

    expect($note->invoice_id)->toBe($invoice->id)
        ->and($note->client_id)->toBe($invoice->client_id)
        ->and($note->company_id)->toBe($invoice->company_id)
        ->and((float) $note->total)->toBe(2000.0)
        ->and($note->number)->toBe('CRN-000001');
});

it('numbers credit notes sequentially per company', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 1000));
    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 1000));

    expect(CreditNote::query()->orderBy('id')->pluck('number')->all())
        ->toBe(['CRN-000001', 'CRN-000002']);
});

it('copies the invoice currency rather than trusting the form', function () {
    [$user, $invoice] = payableInvoiceContext();

    $payload = creditNotePayload($invoice, 1000);
    $payload['currency_code'] = 'USD';

    $this->actingAs($user)->post('/credit-notes', $payload);

    expect(CreditNote::query()->latest('id')->first()->currency_code)->toBe('ZMW');
});

it('stores the line items', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 2000));
    $note = CreditNote::query()->latest('id')->first();

    $item = CreditNoteItem::query()->where('credit_note_id', $note->id)->first();

    expect($item->description)->toBe('Returned consulting hours')
        ->and((float) $item->line_total)->toBe(2000.0);
});

/* ---------------------------- Money ---------------------------- */

it('carves tax out of the credited amount the same way an invoice does', function () {
    [$user, $invoice] = payableInvoiceContext();

    $payload = creditNotePayload($invoice, 5000);
    $payload['tax_percent'] = 16;

    $this->actingAs($user)->post('/credit-notes', $payload);

    $note = CreditNote::query()->latest('id')->first();

    expect((float) $note->total)->toBe(5000.0)
        ->and((float) $note->subtotal)->toBe(4310.34)
        ->and((float) $note->tax_total)->toBe(689.66);
});

/* ---------------------------- Guards ---------------------------- */

it('refuses to credit more than the invoice still owes', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($invoice, 6000))
        ->assertSessionHasErrors('items');

    expect(CreditNote::query()->count())->toBe(0);
});

it('accounts for money already paid when capping the credit', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 4000, 'paid_on' => '2026-07-10', 'method' => 'cash',
    ]);

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($invoice, 1500))
        ->assertSessionHasErrors('items');

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($invoice, 1000))
        ->assertSessionHasNoErrors();
});

it('lets a draft credit note exceed the balance, since it is not applied yet', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($invoice, 6000, 'draft'))
        ->assertSessionHasNoErrors();

    expect(CreditNote::query()->count())->toBe(1);
});

it('refuses an invoice belonging to another company', function () {
    [$user] = payableInvoiceContext();
    [, $foreignInvoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($foreignInvoice, 100))
        ->assertSessionHasErrors('invoice_id');
});

/* ---------------------------- Effect ---------------------------- */

it('settles the invoice when the credit clears the balance', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 5000));

    expect($invoice->fresh()->status)->toBe('paid');
});

it('leaves the invoice alone for a draft credit note', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 5000, 'draft'));

    expect($invoice->fresh()->status)->toBe('sent');
});

it('re-settles the invoice when a draft is later issued', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 5000, 'draft'));
    $note = CreditNote::query()->latest('id')->first();

    $this->actingAs($user)
        ->post("/credit-notes/{$note->id}/status", ['status' => 'issued'])
        ->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe('paid');
});

it('re-opens the invoice when an issued credit note is voided', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 5000));
    $note = CreditNote::query()->latest('id')->first();

    expect($invoice->fresh()->status)->toBe('paid');

    $this->actingAs($user)->post("/credit-notes/{$note->id}/status", ['status' => 'void']);

    expect($invoice->fresh()->status)->toBe('sent');
});

/* ---------------------------- Rendering ---------------------------- */

it('renders a saved credit note through the shared sheet', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 2000));
    $note = CreditNote::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/credit-notes/{$note->id}/preview")
        ->assertSuccessful()
        ->assertSee('CREDIT NOTE', false);
});

it('will not preview a credit note from another company', function () {
    [$user] = payableInvoiceContext();
    [$otherUser, $foreignInvoice] = payableInvoiceContext();

    $this->actingAs($otherUser)->post('/credit-notes', creditNotePayload($foreignInvoice, 100));
    $foreignNote = CreditNote::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/credit-notes/{$foreignNote->id}/preview")
        ->assertForbidden();
});

/* ---------------------------- Index ---------------------------- */

it('lists credit notes for the active company', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 1000));

    $this->actingAs($user)
        ->get('/credit-notes')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('CreditNotes/Index')
            ->has('creditNotes', 1)
            ->where('creditNotes.0.number', 'CRN-000001')
        );
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test tests/Feature/CreditNoteCreationTest.php`
Expected: FAIL — 404, no `/credit-notes` route

- [ ] **Step 4: Write the controller**

```bash
php artisan make:controller CreditNoteController --no-interaction
```

Replace `app/Http/Controllers/CreditNoteController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\DocumentRenderer;
use App\Services\InvoiceSettlement;
use App\Services\TemplateProvisioner;
use App\Support\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CreditNoteController extends Controller
{
    public function __construct(
        private DocumentRenderer $documents,
        private InvoiceSettlement $settlement,
        private TemplateProvisioner $templates,
    ) {}

    private function resolveCompanyId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $companyId = (int) ($user->current_company_id ?? 0);

        if ($companyId && $user->isMemberOfCompany($companyId)) {
            return $companyId;
        }

        $fallbackCompanyId = (int) ($user->companies()
            ->orderBy('companies.name')
            ->value('companies.id') ?? 0);

        if ($fallbackCompanyId) {
            $user->forceFill(['current_company_id' => $fallbackCompanyId])->save();
        }

        return $fallbackCompanyId ?: null;
    }

    private function companyId(Request $request): int
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No active company selected.',
            ]);
        }

        return $companyId;
    }

    private function invoiceRule(int $companyId): Exists
    {
        return Rule::exists('invoices', 'id')->where('company_id', $companyId);
    }

    /**
     * Totals for a credit note.
     *
     * Identical arithmetic to {@see QuotationController::computeTotals()} —
     * prices are tax-inclusive, discounts come off the gross, and tax is carved
     * back out by subtraction so subtotal and tax always reconcile to the
     * total. The three must not drift apart.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, array<string, mixed>>, subtotal: float, credit_note_discount: float, discount_total: float, tax_percent: float, tax_total: float, total: float}
     */
    private function computeTotals(array $items, float $creditNoteDiscount, float $taxPercent): array
    {
        $itemsGross = 0.0;
        $lineDiscountTotal = 0.0;

        foreach ($items as $i => $row) {
            $qty = (float) $row['quantity'];
            $price = (float) $row['unit_price'];
            $discount = (float) ($row['discount'] ?? 0);

            $lineBase = $qty * $price;

            $items[$i]['discount'] = $discount;
            $items[$i]['tax'] = 0;
            $items[$i]['line_total'] = max(0, $lineBase - $discount);
            $items[$i]['sort_order'] = $i;

            $itemsGross += $lineBase;
            $lineDiscountTotal += $discount;
        }

        $discountTotal = $lineDiscountTotal + $creditNoteDiscount;

        $total = round(max(0, $itemsGross - $discountTotal), 2);
        $subtotal = round($total / (1 + ($taxPercent / 100)), 2);
        $taxTotal = round($total - $subtotal, 2);

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'credit_note_discount' => $creditNoteDiscount,
            'discount_total' => $discountTotal,
            'tax_percent' => $taxPercent,
            'tax_total' => $taxTotal,
            'total' => $total,
        ];
    }

    public function index(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            return Inertia::render('CreditNotes/Index', [
                'creditNotes' => [],
                'hasActiveCompany' => false,
            ]);
        }

        $creditNotes = CreditNote::query()
            ->where('company_id', $companyId)
            ->with(['client:id,name', 'invoice:id,number'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CreditNote $note) => [
                'id' => $note->id,
                'number' => $note->number,
                'client_name' => $note->client?->name,
                'invoice_number' => $note->invoice?->number,
                'issue_date' => $note->issue_date,
                'currency_code' => $note->currency_code,
                'total' => (float) $note->total,
                'status' => $note->status,
                'reason' => $note->reason,
            ]);

        return Inertia::render('CreditNotes/Index', [
            'creditNotes' => $creditNotes,
            'hasActiveCompany' => true,
        ]);
    }

    public function create(Request $request): Response
    {
        $companyId = $this->companyId($request);

        $invoices = Invoice::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', [Invoice::STATUS_VOID])
            ->with(['client:id,name'])
            ->orderByDesc('issue_date')
            ->get(['id', 'number', 'client_id', 'currency_code', 'total', 'status'])
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'client_name' => $invoice->client?->name,
                'currency_code' => $invoice->currency_code,
                'total' => (float) $invoice->total,
                'balance_due' => $this->settlement->balanceDue($invoice),
            ]);

        return Inertia::render('CreditNotes/Create', [
            'invoices' => $invoices,
            'defaultCurrencyCode' => Company::defaultCurrencyCodeFor(
                $companyId,
                $request->user()?->current_currency_code,
            ),
            'hasActiveCompany' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $user = $request->user();

        $data = $request->validate([
            'invoice_id' => ['required', 'integer', $this->invoiceRule($companyId)],
            'issue_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['draft', 'issued', 'void'])],
            'reason' => ['nullable', 'string', 'max:190'],
            'title' => ['nullable', 'string', 'max:190'],
            'reference' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'credit_note_discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoice = Invoice::query()
            ->where('id', (int) $data['invoice_id'])
            ->where('company_id', $companyId)
            ->firstOrFail();

        $totals = $this->computeTotals(
            $data['items'],
            (float) ($data['credit_note_discount'] ?? 0),
            (float) ($data['tax_percent'] ?? 0),
        );

        $this->guardCreditFits($invoice, $totals['total'], $data['status']);

        $note = DB::transaction(function () use ($companyId, $user, $data, $totals, $invoice): CreditNote {
            $note = CreditNote::query()->create([
                'company_id' => $companyId,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'credit_note_template_id' => $this->templates
                    ->forCompany($companyId, DocumentType::CreditNote)->id,
                'created_by' => $user?->id,
                'number' => DocumentNumber::nextFor(CreditNote::class, $companyId, DocumentType::CreditNote),
                'reference' => $data['reference'] ?? null,
                'title' => $data['title'] ?? null,
                'reason' => $data['reason'] ?? null,
                'issue_date' => $data['issue_date'],

                /** Never the submitted currency — a credit must match what it credits. */
                'currency_code' => $invoice->currency_code,

                'subtotal' => $totals['subtotal'],
                'credit_note_discount' => $totals['credit_note_discount'],
                'discount_total' => $totals['discount_total'],
                'tax_percent' => $totals['tax_percent'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],

                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $note->items()->createMany($totals['items']);

            $this->settlement->sync($invoice->fresh());

            return $note;
        });

        return redirect()
            ->route('credit-notes.show', $note)
            ->with('success', 'Credit note created.');
    }

    /**
     * A credit that is about to be applied may not exceed what the invoice is
     * still owed, or the ledger would show a negative balance. Drafts are let
     * through because they move nothing until they are issued.
     */
    private function guardCreditFits(Invoice $invoice, float $total, string $status, ?int $ignoreNoteId = null): void
    {
        if (! in_array($status, CreditNote::APPLIED_STATUSES, true)) {
            return;
        }

        $available = $this->settlement->balanceDue($invoice);

        if ($ignoreNoteId) {
            $existing = (float) $invoice->creditNotes()
                ->applied()
                ->where('id', $ignoreNoteId)
                ->sum('total');

            $available = round($available + $existing, 2);
        }

        if ($total > $available + 0.001) {
            throw ValidationException::withMessages([
                'items' => 'This credit of '.number_format($total, 2).' is more than the '
                    .number_format($available, 2).' still outstanding on invoice '
                    .$invoice->number.'.',
            ]);
        }
    }

    public function show(Request $request, CreditNote $creditNote): Response
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $creditNote->company_id === $companyId, 403);

        $creditNote->load([
            'client:id,company_id,name,email,contact_person',
            'invoice:id,number,total,status',
            'items',
        ]);

        return Inertia::render('CreditNotes/show', [
            'creditNote' => [
                'id' => $creditNote->id,
                'number' => $creditNote->number,
                'title' => $creditNote->title,
                'reference' => $creditNote->reference,
                'reason' => $creditNote->reason,
                'status' => $creditNote->status,
                'issue_date' => $creditNote->issue_date,
                'currency_code' => $creditNote->currency_code,

                'subtotal' => (float) $creditNote->subtotal,
                'discount_total' => (float) $creditNote->discount_total,
                'credit_note_discount' => (float) ($creditNote->credit_note_discount ?? 0),
                'tax_percent' => (float) ($creditNote->tax_percent ?? 0),
                'tax_total' => (float) $creditNote->tax_total,
                'total' => (float) $creditNote->total,

                'notes' => $creditNote->notes,
                'terms' => $creditNote->terms,

                'client' => $creditNote->client,
                'invoice' => $creditNote->invoice,
                'items' => $creditNote->items,
            ],
        ]);
    }

    public function updateStatus(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $creditNote->company_id === $companyId, 403);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['draft', 'issued', 'void'])],
        ]);

        if ($creditNote->status === $data['status']) {
            return back()->with('info', 'Credit note is already '.$data['status'].'.');
        }

        $invoice = $creditNote->invoice;

        $this->guardCreditFits($invoice, (float) $creditNote->total, $data['status'], $creditNote->id);

        DB::transaction(function () use ($creditNote, $data, $invoice): void {
            $creditNote->update(['status' => $data['status']]);
            $this->settlement->sync($invoice->fresh());
        });

        return back()->with('success', 'Credit note is now '.$data['status'].'.');
    }

    public function preview(Request $request, CreditNote $creditNote)
    {
        abort_unless((int) $creditNote->company_id === $this->companyId($request), 403);

        return response($this->documents->html($creditNote, 'preview'));
    }

    public function print(Request $request, CreditNote $creditNote)
    {
        abort_unless((int) $creditNote->company_id === $this->companyId($request), 403);

        return response()->view(
            'invoices.templates.default',
            $this->documents->viewData($creditNote, 'print') + [
                'autoPrint' => $request->boolean('download'),
            ]
        );
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, inside the `['auth', 'verified', 'subscribed']` group, after the quotations block:

```php
    // Credit notes
    Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CreditNoteController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\CreditNoteController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\CreditNoteController::class, 'store'])->name('store');

        Route::get('/{creditNote}', [\App\Http\Controllers\CreditNoteController::class, 'show'])
            ->whereNumber('creditNote')
            ->name('show');

        Route::post('/{creditNote}/status', [\App\Http\Controllers\CreditNoteController::class, 'updateStatus'])
            ->whereNumber('creditNote')
            ->name('status');

        Route::get('/{creditNote}/preview', [\App\Http\Controllers\CreditNoteController::class, 'preview'])
            ->whereNumber('creditNote')
            ->name('preview');

        Route::get('/{creditNote}/print', [\App\Http\Controllers\CreditNoteController::class, 'print'])
            ->whereNumber('creditNote')
            ->name('print');
    });
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test tests/Feature/CreditNoteCreationTest.php`
Expected: PASS, 17 tests

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/CreditNoteController.php routes/web.php tests/Pest.php tests/Feature/CreditNoteCreationTest.php
git commit -m "feat: add credit note creation, status changes and printing"
```

---

## Task 2.5: Credit note screens

**Files:**
- Create: `resources/js/pages/CreditNotes/Index.tsx`, `Create.tsx`, `show.tsx`

- [ ] **Step 1: Build the three pages**

Copy `resources/js/pages/Quotations/Index.tsx` to `resources/js/pages/CreditNotes/Index.tsx` and adapt it:
- Props become `creditNotes` (fields: `id`, `number`, `client_name`, `invoice_number`, `issue_date`, `currency_code`, `total`, `status`, `reason`).
- Add an "Against invoice" column showing `invoice_number`.
- Status pill values are `draft` / `issued` / `void` instead of the quotation set.
- Row links go to `/credit-notes/{id}`.
- Drop the `prerequisites` blocker UI entirely — credit notes have no prerequisite gate, because the invoice they credit already proves the company, client, currency and template all exist.

Copy `resources/js/pages/Quotations/Create.tsx` to `resources/js/pages/CreditNotes/Create.tsx` and adapt it:
- Replace the client picker with an invoice picker driven by the `invoices` prop; the selected invoice fixes the client and currency, so both become read-only display fields rather than inputs.
- Show the selected invoice's `balance_due` next to the running total, and disable submit when the running total exceeds it and status is `issued`.
- Add a `reason` text field and a status select of `draft` / `issued`.
- Remove the template picker — the controller provisions the template.
- Remove the `valid_until` field and the send-to-client toggle.
- Post to `/credit-notes`.

Copy `resources/js/pages/Quotations/show.tsx` to `resources/js/pages/CreditNotes/show.tsx` and adapt it:
- Prop is `creditNote`.
- Add a line linking back to the credited invoice (`/invoices/{invoice.id}`).
- Status actions post to `/credit-notes/{id}/status` with `draft` / `issued` / `void`.
- Print and preview buttons point at `/credit-notes/{id}/print` and `/credit-notes/{id}/preview`.

- [ ] **Step 2: Build the frontend**

Run: `npm run build`
Expected: build completes with no TypeScript errors

- [ ] **Step 3: Confirm the index test still passes**

Run: `php artisan test tests/Feature/CreditNoteCreationTest.php`
Expected: PASS, 17 tests

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/CreditNotes
git commit -m "feat: add credit note screens"
```

---

# Phase 3 — Delivery notes

`Invoice::$has_delivery_note` already exists and does nothing. This phase puts a document behind it. A delivery note is generated from an invoice, carries the same lines with prices suppressed, and records who signed for the goods.

## Task 3.1: The delivery note tables

**Files:**
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_create_delivery_notes_table.php`
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_create_delivery_note_items_table.php`

- [ ] **Step 1: Generate both migrations**

```bash
php artisan make:migration create_delivery_notes_table --no-interaction
php artisan make:migration create_delivery_note_items_table --no-interaction
```

- [ ] **Step 2: Write the delivery_notes schema**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Goods dispatched against an invoice.
     *
     * Carries a currency_code only so the shared sheet can resolve a currency
     * without a special case — no money is ever printed on this document.
     */
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('delivery_note_template_id')->nullable()
                ->constrained('invoice_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('number')->nullable();
            $table->string('reference')->nullable();

            $table->date('issue_date');
            $table->date('delivery_date')->nullable();
            $table->string('currency_code', 3);

            $table->string('deliver_to')->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('received_by')->nullable();
            $table->date('received_on')->nullable();

            $table->string('status')->default('draft'); // draft|dispatched|delivered

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'delivery_date']);
            $table->index(['invoice_id']);
            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
    }
};
```

- [ ] **Step 3: Write the delivery_note_items schema**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No prices here by design. A delivery note proves what arrived; putting
     * money on it hands your margins to whoever signs for the goods.
     */
    public function up(): void
    {
        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_note_id')->constrained('delivery_notes')->cascadeOnDelete();

            $table->string('description');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 14, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['delivery_note_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
    }
};
```

- [ ] **Step 4: Run the migrations**

Run: `php artisan migrate`
Expected: both migrations marked `DONE`

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add database/migrations
git commit -m "feat: add delivery_notes and delivery_note_items tables"
```

---

## Task 3.2: The delivery note models

**Files:**
- Create: `app/Models/DeliveryNote.php`, `app/Models/DeliveryNoteItem.php`
- Test: `tests/Feature/DeliveryNoteModelTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DeliveryNoteModelTest.php`:

```php
<?php

use App\Enums\DocumentType;
use App\Models\DeliveryNote;

it('renders as a delivery note', function () {
    expect((new DeliveryNote)->documentType())->toBe(DocumentType::DeliveryNote);
});

it('never shows prices', function () {
    expect((new DeliveryNote)->documentType()->showsPrices())->toBeFalse();
});

it('lists the statuses a delivery moves through', function () {
    expect(DeliveryNote::STATUSES)->toBe(['draft', 'dispatched', 'delivered']);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/DeliveryNoteModelTest.php`
Expected: FAIL — `Class "App\Models\DeliveryNote" not found`

- [ ] **Step 3: Create the models**

```bash
php artisan make:model DeliveryNote --no-interaction
php artisan make:model DeliveryNoteItem --no-interaction
```

Replace `app/Models/DeliveryNote.php`:

```php
<?php

namespace App\Models;

use App\Contracts\RenderableDocument;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Goods dispatched against an invoice.
 *
 * There is deliberately no money on this model. The shared sheet suppresses
 * every price column for this type, so a delivery note cannot leak what the
 * goods cost even if a caller passes totals in.
 */
class DeliveryNote extends Model implements RenderableDocument
{
    /** @use HasFactory<\Database\Factories\DeliveryNoteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    public const STATUSES = ['draft', 'dispatched', 'delivered'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'client_id',
        'invoice_id',
        'delivery_note_template_id',
        'created_by',
        'number',
        'reference',
        'issue_date',
        'delivery_date',
        'currency_code',
        'deliver_to',
        'delivery_address',
        'received_by',
        'received_on',
        'status',
        'notes',
        'terms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'delivery_date' => 'date',
            'received_on' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class)->orderBy('sort_order');
    }

    public function documentType(): DocumentType
    {
        return DocumentType::DeliveryNote;
    }

    public function counterparty(): ?Model
    {
        return $this->client;
    }

    /**
     * @return Collection<int, Model>
     */
    public function printableItems(): Collection
    {
        return $this->items()->get();
    }

    public function chosenTemplateId(): ?int
    {
        return $this->delivery_note_template_id;
    }
}
```

Replace `app/Models/DeliveryNoteItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNoteItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'delivery_note_id',
        'description',
        'unit',
        'quantity',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }
}
```

- [ ] **Step 4: Create the factory**

`DeliveryNote` declares `HasFactory`, so the factory must exist even though the tests in this phase create delivery notes through the controller.

Create `database/factories/DeliveryNoteFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryNote>
 */
class DeliveryNoteFactory extends Factory
{
    protected $model = DeliveryNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'client_id' => Client::factory(),
            'invoice_id' => Invoice::factory(),
            'number' => 'DN-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'issue_date' => fake()->date(),
            'currency_code' => 'ZMW',
            'status' => 'draft',
        ];
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/DeliveryNoteModelTest.php`
Expected: PASS, 3 tests

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/DeliveryNote.php app/Models/DeliveryNoteItem.php database/factories/DeliveryNoteFactory.php tests/Feature/DeliveryNoteModelTest.php
git commit -m "feat: add DeliveryNote and DeliveryNoteItem models"
```

---

## Task 3.3: Generate a delivery note from an invoice

**Files:**
- Create: `app/Http/Controllers/DeliveryNoteController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DeliveryNoteCreationTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DeliveryNoteCreationTest.php`:

```php
<?php

use App\Models\Currency;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Invoice;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

/**
 * An invoice with two priced lines, so the copy-without-prices behaviour has
 * something to prove.
 */
function invoiceWithLines(): array
{
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $invoice = Invoice::query()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'invoice_template_id' => $template->id,
        'number' => 'INV-000007',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-31',
        'currency_code' => 'ZMW',
        'subtotal' => 5000,
        'total' => 5000,
        'status' => 'sent',
    ]);

    $invoice->items()->createMany([
        ['description' => 'Steel bolts', 'unit' => 'box', 'quantity' => 4, 'unit_price' => 750, 'line_total' => 3000, 'sort_order' => 0],
        ['description' => 'Washers', 'unit' => 'box', 'quantity' => 2, 'unit_price' => 1000, 'line_total' => 2000, 'sort_order' => 1],
    ]);

    return [$user, $invoice];
}

it('generates a delivery note from an invoice', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)
        ->post("/invoices/{$invoice->id}/delivery-note")
        ->assertSessionHasNoErrors();

    $note = DeliveryNote::query()->latest('id')->first();

    expect($note->invoice_id)->toBe($invoice->id)
        ->and($note->client_id)->toBe($invoice->client_id)
        ->and($note->company_id)->toBe($invoice->company_id)
        ->and($note->number)->toBe('DN-000001')
        ->and($note->status)->toBe('draft');
});

it('copies the invoice lines without any prices', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = DeliveryNote::query()->latest('id')->first();

    $items = DeliveryNoteItem::query()->where('delivery_note_id', $note->id)->orderBy('sort_order')->get();

    expect($items)->toHaveCount(2)
        ->and($items[0]->description)->toBe('Steel bolts')
        ->and((float) $items[0]->quantity)->toBe(4.0)
        ->and($items[0]->getAttributes())->not->toHaveKey('unit_price')
        ->and($items[1]->description)->toBe('Washers');
});

it('flags the invoice as having a delivery note', function () {
    [$user, $invoice] = invoiceWithLines();

    expect($invoice->has_delivery_note)->toBeFalse();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");

    expect($invoice->fresh()->has_delivery_note)->toBeTrue();
});

it('addresses the delivery to the client by default', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");

    expect(DeliveryNote::query()->latest('id')->first()->deliver_to)
        ->toBe($invoice->client->name);
});

it('numbers delivery notes sequentially per company', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");

    expect(DeliveryNote::query()->orderBy('id')->pluck('number')->all())
        ->toBe(['DN-000001', 'DN-000002']);
});

it('refuses an invoice from another company', function () {
    [$user] = invoiceWithLines();
    [, $foreignInvoice] = invoiceWithLines();

    $this->actingAs($user)
        ->post("/invoices/{$foreignInvoice->id}/delivery-note")
        ->assertForbidden();

    expect(DeliveryNote::query()->count())->toBe(0);
});

/* ---------------------------- Rendering ---------------------------- */

it('prints without a single price on it', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = DeliveryNote::query()->latest('id')->first();

    $response = $this->actingAs($user)->get("/delivery-notes/{$note->id}/preview");
    $response->assertSuccessful();

    expect($response->getContent())
        ->toContain('DELIVERY NOTE')
        ->toContain('Steel bolts')
        ->not->toContain('750.00')
        ->not->toContain('3,000.00')
        ->not->toContain('GRAND TOTAL');
});

it('addresses the printed sheet to where the goods go', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = DeliveryNote::query()->latest('id')->first();

    expect($this->actingAs($user)->get("/delivery-notes/{$note->id}/preview")->getContent())
        ->toContain('Deliver to:');
});

/* ---------------------------- Sign-off ---------------------------- */

it('records who signed for the goods', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = DeliveryNote::query()->latest('id')->first();

    $this->actingAs($user)
        ->put("/delivery-notes/{$note->id}", [
            'status' => 'delivered',
            'delivery_date' => '2026-07-05',
            'received_by' => 'J. Banda',
            'received_on' => '2026-07-05',
            'deliver_to' => 'Acme Warehouse',
            'delivery_address' => '12 Cairo Road, Lusaka',
        ])
        ->assertSessionHasNoErrors();

    $note = $note->fresh();

    expect($note->status)->toBe('delivered')
        ->and($note->received_by)->toBe('J. Banda')
        ->and($note->received_on->toDateString())->toBe('2026-07-05')
        ->and($note->deliver_to)->toBe('Acme Warehouse');
});

it('rejects a delivery status it does not use', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = DeliveryNote::query()->latest('id')->first();

    $this->actingAs($user)
        ->put("/delivery-notes/{$note->id}", ['status' => 'paid'])
        ->assertSessionHasErrors('status');
});

/* ---------------------------- Index ---------------------------- */

it('lists delivery notes for the active company', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");

    $this->actingAs($user)
        ->get('/delivery-notes')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('DeliveryNotes/Index')
            ->has('deliveryNotes', 1)
            ->where('deliveryNotes.0.number', 'DN-000001')
        );
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/DeliveryNoteCreationTest.php`
Expected: FAIL — 404, no delivery note routes

- [ ] **Step 3: Write the controller**

```bash
php artisan make:controller DeliveryNoteController --no-interaction
```

Replace `app/Http/Controllers/DeliveryNoteController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\DocumentRenderer;
use App\Services\TemplateProvisioner;
use App\Support\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryNoteController extends Controller
{
    public function __construct(
        private DocumentRenderer $documents,
        private TemplateProvisioner $templates,
    ) {}

    private function resolveCompanyId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $companyId = (int) ($user->current_company_id ?? 0);

        if ($companyId && $user->isMemberOfCompany($companyId)) {
            return $companyId;
        }

        $fallbackCompanyId = (int) ($user->companies()
            ->orderBy('companies.name')
            ->value('companies.id') ?? 0);

        if ($fallbackCompanyId) {
            $user->forceFill(['current_company_id' => $fallbackCompanyId])->save();
        }

        return $fallbackCompanyId ?: null;
    }

    private function companyId(Request $request): int
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No active company selected.',
            ]);
        }

        return $companyId;
    }

    public function index(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            return Inertia::render('DeliveryNotes/Index', [
                'deliveryNotes' => [],
                'hasActiveCompany' => false,
            ]);
        }

        $deliveryNotes = DeliveryNote::query()
            ->where('company_id', $companyId)
            ->with(['client:id,name', 'invoice:id,number'])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DeliveryNote $note) => [
                'id' => $note->id,
                'number' => $note->number,
                'client_name' => $note->client?->name,
                'invoice_number' => $note->invoice?->number,
                'issue_date' => $note->issue_date,
                'delivery_date' => $note->delivery_date,
                'deliver_to' => $note->deliver_to,
                'received_by' => $note->received_by,
                'status' => $note->status,
                'item_count' => $note->items_count,
            ]);

        return Inertia::render('DeliveryNotes/Index', [
            'deliveryNotes' => $deliveryNotes,
            'hasActiveCompany' => true,
        ]);
    }

    /**
     * Generates a delivery note from an invoice.
     *
     * The lines are copied rather than referenced, because what was dispatched
     * is a fact about a moment in time — editing the invoice afterwards must
     * not rewrite what somebody already signed for.
     */
    public function storeForInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $invoice->company_id === $companyId, 403);

        $invoice->loadMissing(['items', 'client']);

        $note = DB::transaction(function () use ($request, $invoice, $companyId): DeliveryNote {
            $note = DeliveryNote::query()->create([
                'company_id' => $companyId,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'delivery_note_template_id' => $this->templates
                    ->forCompany($companyId, DocumentType::DeliveryNote)->id,
                'created_by' => $request->user()?->id,
                'number' => DocumentNumber::nextFor(DeliveryNote::class, $companyId, DocumentType::DeliveryNote),
                'reference' => $invoice->number,
                'issue_date' => now()->toDateString(),
                'currency_code' => $invoice->currency_code,
                'deliver_to' => $invoice->client?->name,
                'delivery_address' => $invoice->client?->address,
                'status' => 'draft',
            ]);

            $note->items()->createMany(
                $invoice->items->map(fn (InvoiceItem $item, int $index) => [
                    'description' => $item->description,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'sort_order' => $index,
                ])->all()
            );

            $invoice->update(['has_delivery_note' => true]);

            return $note;
        });

        return redirect()
            ->route('delivery-notes.show', $note)
            ->with('success', 'Delivery note '.$note->number.' created.');
    }

    public function show(Request $request, DeliveryNote $deliveryNote): Response
    {
        abort_unless((int) $deliveryNote->company_id === $this->companyId($request), 403);

        $deliveryNote->load([
            'client:id,company_id,name,email,address,contact_person',
            'invoice:id,number',
            'items',
        ]);

        return Inertia::render('DeliveryNotes/show', [
            'deliveryNote' => [
                'id' => $deliveryNote->id,
                'number' => $deliveryNote->number,
                'reference' => $deliveryNote->reference,
                'status' => $deliveryNote->status,
                'issue_date' => $deliveryNote->issue_date,
                'delivery_date' => $deliveryNote->delivery_date,
                'deliver_to' => $deliveryNote->deliver_to,
                'delivery_address' => $deliveryNote->delivery_address,
                'received_by' => $deliveryNote->received_by,
                'received_on' => $deliveryNote->received_on,
                'notes' => $deliveryNote->notes,
                'client' => $deliveryNote->client,
                'invoice' => $deliveryNote->invoice,
                'items' => $deliveryNote->items,
            ],
            'statuses' => DeliveryNote::STATUSES,
        ]);
    }

    public function update(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        abort_unless((int) $deliveryNote->company_id === $this->companyId($request), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(DeliveryNote::STATUSES)],
            'delivery_date' => ['nullable', 'date'],
            'deliver_to' => ['nullable', 'string', 'max:190'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'received_by' => ['nullable', 'string', 'max:190'],
            'received_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $deliveryNote->update($data);

        return back()->with('success', 'Delivery note updated.');
    }

    public function preview(Request $request, DeliveryNote $deliveryNote)
    {
        abort_unless((int) $deliveryNote->company_id === $this->companyId($request), 403);

        return response($this->documents->html($deliveryNote, 'preview'));
    }

    public function print(Request $request, DeliveryNote $deliveryNote)
    {
        abort_unless((int) $deliveryNote->company_id === $this->companyId($request), 403);

        return response()->view(
            'invoices.templates.default',
            $this->documents->viewData($deliveryNote, 'print') + [
                'autoPrint' => $request->boolean('download'),
            ]
        );
    }
}
```

- [ ] **Step 4: Register the routes**

In `routes/web.php`, add inside the existing `invoices` prefix group:

```php
        Route::post('/{invoice}/delivery-note', [\App\Http\Controllers\DeliveryNoteController::class, 'storeForInvoice'])
            ->whereNumber('invoice')
            ->name('delivery-note.store');
```

And add a new group after the credit notes block:

```php
    // Delivery notes
    Route::prefix('delivery-notes')->name('delivery-notes.')->group(function () {
        Route::get('/', [\App\Http\Controllers\DeliveryNoteController::class, 'index'])->name('index');

        Route::get('/{deliveryNote}', [\App\Http\Controllers\DeliveryNoteController::class, 'show'])
            ->whereNumber('deliveryNote')
            ->name('show');

        Route::put('/{deliveryNote}', [\App\Http\Controllers\DeliveryNoteController::class, 'update'])
            ->whereNumber('deliveryNote')
            ->name('update');

        Route::get('/{deliveryNote}/preview', [\App\Http\Controllers\DeliveryNoteController::class, 'preview'])
            ->whereNumber('deliveryNote')
            ->name('preview');

        Route::get('/{deliveryNote}/print', [\App\Http\Controllers\DeliveryNoteController::class, 'print'])
            ->whereNumber('deliveryNote')
            ->name('print');
    });
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/DeliveryNoteCreationTest.php`
Expected: PASS, 11 tests

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/DeliveryNoteController.php routes/web.php tests/Feature/DeliveryNoteCreationTest.php
git commit -m "feat: generate delivery notes from invoices"
```

---

## Task 3.4: Add a signature block to the printed sheet

A delivery note that cannot be signed is not a delivery note.

**Files:**
- Modify: `resources/views/invoices/templates/default.blade.php`
- Test: `tests/Feature/DeliveryNoteCreationTest.php` (add one test)

- [ ] **Step 1: Add the failing test**

Append to `tests/Feature/DeliveryNoteCreationTest.php`:

```php
it('prints a receipt-of-goods signature block', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = App\Models\DeliveryNote::query()->latest('id')->first();

    expect($this->actingAs($user)->get("/delivery-notes/{$note->id}/preview")->getContent())
        ->toContain('Received by')
        ->toContain('Signature')
        ->toContain('Date');
});

it('keeps the signature block off every other document type', function () {
    [$user, $invoice] = invoiceWithLines();

    expect($this->actingAs($user)->get("/invoices/{$invoice->id}/preview")->getContent())
        ->not->toContain('Received by');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/DeliveryNoteCreationTest.php --filter="signature"`
Expected: FAIL — the sheet contains no signature block

- [ ] **Step 3: Add the block to the blade**

In `resources/views/invoices/templates/default.blade.php`, immediately after the closing-line paragraph that renders `{{ $closingLine }}`, add:

```blade
                @if($type === \App\Enums\DocumentType::DeliveryNote)
                    <div style="margin-top:36px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:24px;">
                        <div>
                            <div style="border-bottom:1px solid #9ca3af; height:32px;">{{ $invoice->received_by ?? '' }}</div>
                            <div style="font-size:11px; color:#6b7280; margin-top:6px;">Received by</div>
                        </div>
                        <div>
                            <div style="border-bottom:1px solid #9ca3af; height:32px;"></div>
                            <div style="font-size:11px; color:#6b7280; margin-top:6px;">Signature</div>
                        </div>
                        <div>
                            <div style="border-bottom:1px solid #9ca3af; height:32px;">{{ $invoice->received_on ?? '' }}</div>
                            <div style="font-size:11px; color:#6b7280; margin-top:6px;">Date</div>
                        </div>
                    </div>
                @endif
```

The invoice sheet already prints the word `Date:` in its header meta block, so the `not->toContain('Received by')` assertion is what distinguishes the two — that is why the negative test checks `Received by` rather than `Date`.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/DeliveryNoteCreationTest.php tests/Feature/DocumentSheetRenderingTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add resources/views/invoices/templates/default.blade.php tests/Feature/DeliveryNoteCreationTest.php
git commit -m "feat: print a signature block on delivery notes"
```

---

## Task 3.5: Delivery note screens

**Files:**
- Create: `resources/js/pages/DeliveryNotes/Index.tsx`, `show.tsx`
- Modify: `resources/js/pages/Invoices/show.tsx`

- [ ] **Step 1: Build the index page**

Copy `resources/js/pages/Quotations/Index.tsx` to `resources/js/pages/DeliveryNotes/Index.tsx` and adapt:
- Props become `deliveryNotes` (`id`, `number`, `client_name`, `invoice_number`, `issue_date`, `delivery_date`, `deliver_to`, `received_by`, `status`, `item_count`).
- Replace the currency/total column with an item-count column — there is no money on this document, and the list must not imply otherwise.
- Status pills are `draft` / `dispatched` / `delivered`.
- Rows link to `/delivery-notes/{id}`.
- Drop the prerequisites blocker UI and the "create" button; delivery notes are generated from an invoice, never from a blank form.

- [ ] **Step 2: Build the show page**

Create `resources/js/pages/DeliveryNotes/show.tsx` modelled on `resources/js/pages/Quotations/show.tsx`:
- Items table shows description, unit and quantity only — no price or total columns.
- A dispatch/sign-off form `PUT`s to `/delivery-notes/{id}` with `status`, `delivery_date`, `deliver_to`, `delivery_address`, `received_by`, `received_on`, `notes`.
- Print and preview buttons point at `/delivery-notes/{id}/print` and `/delivery-notes/{id}/preview`.
- A link back to the source invoice.

- [ ] **Step 3: Add the generate button to the invoice page**

In `resources/js/pages/Invoices/show.tsx`, add a `PillButton` in the header actions row:

```tsx
                        <PillButton
                            onClick={() =>
                                router.post(
                                    `/invoices/${invoice.id}/delivery-note`,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Truck className="size-4" />
                            Delivery note
                        </PillButton>
```

Add `Truck` to the `lucide-react` import.

- [ ] **Step 4: Build the frontend**

Run: `npm run build`
Expected: build completes with no TypeScript errors

- [ ] **Step 5: Confirm the index test passes**

Run: `php artisan test tests/Feature/DeliveryNoteCreationTest.php`
Expected: PASS, 13 tests

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/DeliveryNotes resources/js/pages/Invoices/show.tsx
git commit -m "feat: add delivery note screens"
```

---

# Phase 4 — Suppliers and purchase orders

The only phase that adds a new counterparty. Everything before this points at a `Client`; a purchase order points the other way. Keeping suppliers in their own table is what stops a vendor appearing in your customer list and your revenue roll-ups.

## Task 4.1: The suppliers table and model

**Files:**
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_create_suppliers_table.php`
- Create: `app/Models/Supplier.php`
- Create: `database/factories/SupplierFactory.php`
- Test: `tests/Feature/SupplierTest.php`

- [ ] **Step 1: Generate the migration**

```bash
php artisan make:migration create_suppliers_table --no-interaction
```

- [ ] **Step 2: Write the schema**

Column names deliberately match `clients` — the shared sheet reads `name`, `address`, `email` and `contact_person` off whatever counterparty it is handed, so matching names means a purchase order renders with no special case in the blade.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('tpin')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('contact_person')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
```

- [ ] **Step 3: Write the failing test**

Create `tests/Feature/SupplierTest.php`:

```php
<?php

use App\Models\Supplier;

it('creates a supplier in the active company', function () {
    [$user] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/suppliers', [
            'name' => 'Zambezi Steel',
            'email' => 'sales@zambezisteel.example',
            'phone' => '+260 971 000 000',
            'contact_person' => 'M. Phiri',
        ])
        ->assertSessionHasNoErrors();

    $supplier = Supplier::query()->latest('id')->first();

    expect($supplier->name)->toBe('Zambezi Steel')
        ->and($supplier->company_id)->toBe($user->current_company_id);
});

it('requires a name', function () {
    [$user] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/suppliers', ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('lists only the active company suppliers', function () {
    [$user] = invoiceCreationContext('client@example.com');
    [$otherUser] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/suppliers', ['name' => 'Ours']);
    $this->actingAs($otherUser)->post('/suppliers', ['name' => 'Theirs']);

    $this->actingAs($user)
        ->get('/suppliers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Suppliers/Index')
            ->has('suppliers', 1)
            ->where('suppliers.0.name', 'Ours')
        );
});

it('updates a supplier', function () {
    [$user] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/suppliers', ['name' => 'Old name']);
    $supplier = Supplier::query()->latest('id')->first();

    $this->actingAs($user)
        ->put("/suppliers/{$supplier->id}", ['name' => 'New name'])
        ->assertSessionHasNoErrors();

    expect($supplier->fresh()->name)->toBe('New name');
});

it('will not touch a supplier in another company', function () {
    [$user] = invoiceCreationContext('client@example.com');
    [$otherUser] = invoiceCreationContext('client@example.com');

    $this->actingAs($otherUser)->post('/suppliers', ['name' => 'Theirs']);
    $foreign = Supplier::query()->latest('id')->first();

    $this->actingAs($user)
        ->put("/suppliers/{$foreign->id}", ['name' => 'Hijacked'])
        ->assertForbidden();

    expect($foreign->fresh()->name)->toBe('Theirs');
});

it('deletes a supplier', function () {
    [$user] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/suppliers', ['name' => 'Temporary']);
    $supplier = Supplier::query()->latest('id')->first();

    $this->actingAs($user)
        ->delete("/suppliers/{$supplier->id}")
        ->assertSessionHasNoErrors();

    expect(Supplier::query()->count())->toBe(0);
});

it('keeps suppliers out of the client list', function () {
    [$user] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/suppliers', ['name' => 'Zambezi Steel']);

    $this->actingAs($user)
        ->get('/clients')
        ->assertInertia(fn ($page) => $page
            ->where('clients', fn ($clients) => collect($clients)
                ->pluck('name')
                ->doesntContain('Zambezi Steel'))
        );
});
```

If `/clients` uses a different prop shape, adjust the last assertion to match `ClientController::index()` rather than changing the controller.

- [ ] **Step 4: Run the test to verify it fails**

Run: `php artisan test tests/Feature/SupplierTest.php`
Expected: FAIL — 404, no `/suppliers` route

- [ ] **Step 5: Create the model and factory**

```bash
php artisan make:model Supplier --no-interaction
```

Replace `app/Models/Supplier.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company Nilo buys from.
 *
 * Structurally a mirror of {@see Client}, kept as its own table so a vendor can
 * never surface in a customer list or a revenue roll-up. The column names match
 * `clients` on purpose: the shared document sheet reads the same four fields off
 * whichever counterparty it is handed.
 */
class Supplier extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'tpin',
        'address',
        'city',
        'country',
        'contact_person',
        'notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
```

Create `database/factories/SupplierFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'contact_person' => fake()->name(),
        ];
    }
}
```

- [ ] **Step 6: Write the controller**

```bash
php artisan make:controller SupplierController --no-interaction
```

Replace `app/Http/Controllers/SupplierController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    private function resolveCompanyId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $companyId = (int) ($user->current_company_id ?? 0);

        if ($companyId && $user->isMemberOfCompany($companyId)) {
            return $companyId;
        }

        $fallbackCompanyId = (int) ($user->companies()
            ->orderBy('companies.name')
            ->value('companies.id') ?? 0);

        if ($fallbackCompanyId) {
            $user->forceFill(['current_company_id' => $fallbackCompanyId])->save();
        }

        return $fallbackCompanyId ?: null;
    }

    private function companyId(Request $request): int
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No active company selected.',
            ]);
        }

        return $companyId;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'tpin' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'contact_person' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function index(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $companyId
                ? Supplier::query()
                    ->where('company_id', $companyId)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'phone', 'tpin', 'address', 'city', 'country', 'contact_person', 'notes'])
                : [],
            'hasActiveCompany' => (bool) $companyId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);

        Supplier::query()->create(
            $request->validate($this->rules()) + ['company_id' => $companyId]
        );

        return back()->with('success', 'Supplier added.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless((int) $supplier->company_id === $this->companyId($request), 403);

        $supplier->update($request->validate($this->rules()));

        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless((int) $supplier->company_id === $this->companyId($request), 403);

        $supplier->delete();

        return back()->with('success', 'Supplier removed.');
    }
}
```

- [ ] **Step 7: Register the routes**

In `routes/web.php`, inside the `['auth', 'verified', 'subscribed']` group, next to the client routes:

```php
    // Supplier management
    Route::get('suppliers', [\App\Http\Controllers\SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/suppliers', [\App\Http\Controllers\SupplierController::class, 'store'])->name('suppliers.store');
    Route::put('/suppliers/{supplier}', [\App\Http\Controllers\SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [\App\Http\Controllers\SupplierController::class, 'destroy'])->name('suppliers.destroy');
```

- [ ] **Step 8: Run the migration and the test**

Run: `php artisan migrate && php artisan test tests/Feature/SupplierTest.php`
Expected: PASS, 7 tests

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty
git add database/migrations app/Models/Supplier.php database/factories/SupplierFactory.php app/Http/Controllers/SupplierController.php routes/web.php tests/Feature/SupplierTest.php
git commit -m "feat: add suppliers"
```

---

## Task 4.2: The supplier screen

**Files:**
- Create: `resources/js/pages/Suppliers/Index.tsx`

- [ ] **Step 1: Build the page**

Copy `resources/js/pages/Clients/Index.tsx` to `resources/js/pages/Suppliers/Index.tsx` and adapt it wholesale:
- Prop is `suppliers`, not `clients`.
- Create posts to `/suppliers`, edit `PUT`s to `/suppliers/{id}`, delete `DELETE`s to `/suppliers/{id}`.
- Page heading and empty state say supplier: "No suppliers yet", "Add your first supplier".
- Breadcrumb is `Suppliers` → `/suppliers`.
- Keep the same panel, table, search and modal structure so the two pages stay visually identical.

- [ ] **Step 2: Build the frontend**

Run: `npm run build`
Expected: build completes with no TypeScript errors

- [ ] **Step 3: Confirm the index test passes**

Run: `php artisan test tests/Feature/SupplierTest.php`
Expected: PASS, 7 tests

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/Suppliers
git commit -m "feat: add the suppliers screen"
```

---

## Task 4.3: The purchase order tables

**Files:**
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_create_purchase_orders_table.php`
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_create_purchase_order_items_table.php`

- [ ] **Step 1: Generate both migrations**

```bash
php artisan make:migration create_purchase_orders_table --no-interaction
php artisan make:migration create_purchase_order_items_table --no-interaction
```

- [ ] **Step 2: Write the purchase_orders schema**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An order placed with a supplier. Standalone — unlike every other document
     * added in v1, a purchase order does not hang off an invoice.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('purchase_order_template_id')->nullable()
                ->constrained('invoice_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('number')->nullable();
            $table->string('reference')->nullable();
            $table->string('title')->nullable();

            $table->date('issue_date');
            $table->date('expected_date')->nullable();
            $table->string('currency_code', 3);

            $table->string('delivery_address')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('purchase_order_discount', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('tax_percent', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->string('status')->default('draft'); // draft|sent|approved|received|cancelled

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->decimal('exchange_rate_to_base', 18, 8)->nullable();
            $table->timestamp('exchange_rate_fetched_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'issue_date']);
            $table->index(['company_id', 'supplier_id']);
            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
```

- [ ] **Step 3: Write the purchase_order_items schema**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

            $table->string('description');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 14, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['purchase_order_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
```

- [ ] **Step 4: Run the migrations**

Run: `php artisan migrate`
Expected: both migrations marked `DONE`

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add database/migrations
git commit -m "feat: add purchase_orders and purchase_order_items tables"
```

---

## Task 4.4: The purchase order models

**Files:**
- Create: `app/Models/PurchaseOrder.php`, `app/Models/PurchaseOrderItem.php`
- Create: `database/factories/PurchaseOrderFactory.php`
- Test: `tests/Feature/PurchaseOrderModelTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PurchaseOrderModelTest.php`:

```php
<?php

use App\Enums\DocumentType;
use App\Models\PurchaseOrder;
use App\Models\Supplier;

it('renders as a purchase order', function () {
    expect((new PurchaseOrder)->documentType())->toBe(DocumentType::PurchaseOrder);
});

it('addresses itself to a supplier, not a client', function () {
    $order = PurchaseOrder::factory()->create();

    expect($order->counterparty())->toBeInstanceOf(Supplier::class)
        ->and($order->counterparty()->id)->toBe($order->supplier_id);
});

it('lists the statuses an order moves through', function () {
    expect(PurchaseOrder::STATUSES)->toBe([
        'draft', 'sent', 'approved', 'received', 'cancelled',
    ]);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/PurchaseOrderModelTest.php`
Expected: FAIL — `Class "App\Models\PurchaseOrder" not found`

- [ ] **Step 3: Create the models**

```bash
php artisan make:model PurchaseOrder --no-interaction
php artisan make:model PurchaseOrderItem --no-interaction
```

Replace `app/Models/PurchaseOrder.php`:

```php
<?php

namespace App\Models;

use App\Contracts\RenderableDocument;
use App\Enums\DocumentType;
use App\Models\Concerns\FreezesExchangeRate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order placed with a supplier.
 *
 * The only document Nilo issues that points away from the customer, which is
 * why it is the only one addressed to a {@see Supplier}.
 */
class PurchaseOrder extends Model implements RenderableDocument
{
    /** @use HasFactory<\Database\Factories\PurchaseOrderFactory> */
    use FreezesExchangeRate, HasFactory;

    /**
     * @var list<string>
     */
    public const STATUSES = ['draft', 'sent', 'approved', 'received', 'cancelled'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'supplier_id',
        'purchase_order_template_id',
        'created_by',
        'number',
        'reference',
        'title',
        'issue_date',
        'expected_date',
        'currency_code',
        'delivery_address',
        'subtotal',
        'discount_total',
        'purchase_order_discount',
        'tax_total',
        'tax_percent',
        'total',
        'status',
        'notes',
        'terms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expected_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'purchase_order_discount' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'total' => 'decimal:2',
            'exchange_rate_to_base' => 'float',
            'exchange_rate_fetched_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
    }

    public function documentType(): DocumentType
    {
        return DocumentType::PurchaseOrder;
    }

    public function counterparty(): ?Model
    {
        return $this->supplier;
    }

    /**
     * @return Collection<int, Model>
     */
    public function printableItems(): Collection
    {
        return $this->items()->get();
    }

    public function chosenTemplateId(): ?int
    {
        return $this->purchase_order_template_id;
    }
}
```

Replace `app/Models/PurchaseOrderItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_order_id',
        'description',
        'unit',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'line_total',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
```

Create `database/factories/PurchaseOrderFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'supplier_id' => Supplier::factory(),
            'number' => 'PO-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'issue_date' => fake()->date(),
            'expected_date' => fake()->date(),
            'currency_code' => 'ZMW',
            'subtotal' => 1000,
            'total' => 1000,
            'status' => 'draft',
        ];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/PurchaseOrderModelTest.php`
Expected: PASS, 3 tests

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/PurchaseOrder.php app/Models/PurchaseOrderItem.php database/factories/PurchaseOrderFactory.php tests/Feature/PurchaseOrderModelTest.php
git commit -m "feat: add PurchaseOrder and PurchaseOrderItem models"
```

---

## Task 4.5: The purchase order plan limit

**Files:**
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_add_max_purchase_orders_to_plans_table.php`
- Modify: `app/Models/Plan.php`, `app/Services/SubscriptionLimitService.php`
- Modify: `database/seeders/` (whichever seeder defines the plans)
- Modify: `resources/js/pages/admin/` plan create/edit forms
- Test: `tests/Feature/PurchaseOrderLimitTest.php`

- [ ] **Step 1: Generate and write the migration**

```bash
php artisan make:migration add_max_purchase_orders_to_plans_table --no-interaction
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Defaults to -1 (unlimited) so existing plans are not silently capped the
     * moment this ships.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('max_purchase_orders')->default(-1)->after('max_quotations');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_purchase_orders');
        });
    }
};
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/PurchaseOrderLimitTest.php`:

```php
<?php

use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Services\SubscriptionLimitService;

it('allows creation while under the plan limit', function () {
    [$user] = invoiceCreationContext('client@example.com');
    $user->activePlan()->update(['max_purchase_orders' => 2]);

    expect((new SubscriptionLimitService($user->fresh()))
        ->canCreatePurchaseOrder($user->current_company_id))->toBeTrue();
});

it('refuses creation once the limit is reached', function () {
    [$user] = invoiceCreationContext('client@example.com');
    $user->activePlan()->update(['max_purchase_orders' => 1]);

    PurchaseOrder::factory()->create([
        'company_id' => $user->current_company_id,
        'supplier_id' => App\Models\Supplier::factory()->create([
            'company_id' => $user->current_company_id,
        ])->id,
    ]);

    expect((new SubscriptionLimitService($user->fresh()))
        ->canCreatePurchaseOrder($user->current_company_id))->toBeFalse();
});

it('treats -1 as unlimited', function () {
    [$user] = invoiceCreationContext('client@example.com');
    $user->activePlan()->update(['max_purchase_orders' => -1]);

    PurchaseOrder::factory()->count(3)->create([
        'company_id' => $user->current_company_id,
        'supplier_id' => App\Models\Supplier::factory()->create([
            'company_id' => $user->current_company_id,
        ])->id,
    ]);

    expect((new SubscriptionLimitService($user->fresh()))
        ->canCreatePurchaseOrder($user->current_company_id))->toBeTrue();
});

it('reports purchase order usage alongside the other allowances', function () {
    [$user] = invoiceCreationContext('client@example.com');

    expect((new SubscriptionLimitService($user))->usage())
        ->toHaveKey('purchase_orders');
});

it('counts per company, not per account', function () {
    [$user] = invoiceCreationContext('client@example.com');
    [$otherUser] = invoiceCreationContext('client@example.com');

    $user->activePlan()->update(['max_purchase_orders' => 1]);

    PurchaseOrder::factory()->create([
        'company_id' => $otherUser->current_company_id,
        'supplier_id' => App\Models\Supplier::factory()->create([
            'company_id' => $otherUser->current_company_id,
        ])->id,
    ]);

    expect((new SubscriptionLimitService($user->fresh()))
        ->canCreatePurchaseOrder($user->current_company_id))->toBeTrue();
});
```

`UserFactory::withSubscription()` calls `Plan::firstOrCreate(['slug' => 'free'])`, so **every user created in a single test shares one plan row**. The last test above relies on that: both users are on the same plan with `max_purchase_orders => 1`, and it still passes because the count is scoped per company — which is exactly the behaviour being asserted. Do not "fix" this by giving each user its own plan; that would make the test prove nothing.

The migration in Step 1 defaults the column to `-1`, and `withSubscription()` does not set it, so every existing test keeps unlimited purchase orders unless it opts in.

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test tests/Feature/PurchaseOrderLimitTest.php`
Expected: FAIL — `Call to undefined method App\Services\SubscriptionLimitService::canCreatePurchaseOrder()`

- [ ] **Step 4: Extend the model and the limit service**

In `app/Models/Plan.php`, add `'max_purchase_orders'` to `$fillable` next to `'max_quotations'`, and add it to the `casts()` method with the same cast the other limit columns use.

In `app/Services/SubscriptionLimitService.php`, add `use App\Models\PurchaseOrder;` and this method next to `canCreateQuotation()`:

```php
    public function canCreatePurchaseOrder(int $companyId): bool
    {
        $plan = $this->activePlan();

        if (! $plan) {
            return false;
        }

        if ($plan->max_purchase_orders === -1) {
            return true;
        }

        return PurchaseOrder::where('company_id', $companyId)->count() < $plan->max_purchase_orders;
    }
```

Add to the `usage()` return array and extend its docblock array shape with `purchase_orders: array{used: int, limit: int}`:

```php
            'purchase_orders' => [
                'used' => $companyId ? PurchaseOrder::where('company_id', $companyId)->count() : 0,
                'limit' => $plan?->max_purchase_orders ?? 0,
            ],
```

- [ ] **Step 5: Update the plan seeder and the admin forms**

Find the seeder that defines the plans (check `database/seeders/` for the one covered by `tests/Feature/PlanSeederTest.php`) and give each plan a sensible `max_purchase_orders`. Then add the field to the admin plan create and edit forms under `resources/js/pages/admin/`, matching how `max_quotations` is rendered, and add `'max_purchase_orders' => ['required', 'integer', 'min:-1']` to both `app/Http/Requests/StorePlanRequest.php` and `app/Http/Requests/UpdatePlanRequest.php` alongside the existing limit rules.

- [ ] **Step 6: Run the migration and the tests**

Run: `php artisan migrate && php artisan test tests/Feature/PurchaseOrderLimitTest.php tests/Feature/PlanSeederTest.php tests/Feature/Admin/PlanManagementTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add database/migrations app/Models/Plan.php app/Services/SubscriptionLimitService.php app/Http/Requests database/seeders resources/js/pages/admin tests/Feature/PurchaseOrderLimitTest.php
git commit -m "feat: meter purchase orders against the plan allowance"
```

---

## Task 4.6: The purchase order controller

**Files:**
- Create: `app/Http/Controllers/PurchaseOrderController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/PurchaseOrderCreationTest.php`

- [ ] **Step 1: Add the Pest helper**

At the bottom of `tests/Pest.php`:

```php
/**
 * A user with an active company and one supplier — the minimum state the
 * purchase order endpoint accepts.
 *
 * @return array{0: \App\Models\User, 1: \App\Models\Supplier}
 */
function purchaseOrderContext(): array
{
    [$user] = invoiceCreationContext('client@example.com');

    $supplier = App\Models\Supplier::factory()->create([
        'company_id' => $user->current_company_id,
    ]);

    return [$user, $supplier];
}

/**
 * @return array<string, mixed>
 */
function purchaseOrderPayload(App\Models\Supplier $supplier, float $unitPrice = 2500): array
{
    return [
        'supplier_id' => $supplier->id,
        'issue_date' => '2026-07-01',
        'expected_date' => '2026-08-15',
        'currency_code' => 'ZMW',
        'status' => 'draft',
        'purchase_order_discount' => 0,
        'tax_percent' => 0,
        'items' => [
            [
                'description' => 'Steel bolts',
                'unit' => 'box',
                'quantity' => 2,
                'unit_price' => $unitPrice,
                'discount' => 0,
            ],
        ],
    ];
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/PurchaseOrderCreationTest.php`:

```php
<?php

use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

/* ---------------------------- Creation ---------------------------- */

it('creates a purchase order for a supplier', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)
        ->post('/purchase-orders', purchaseOrderPayload($supplier))
        ->assertSessionHasNoErrors();

    $order = PurchaseOrder::query()->latest('id')->first();

    expect($order->supplier_id)->toBe($supplier->id)
        ->and($order->company_id)->toBe($user->current_company_id)
        ->and($order->number)->toBe('PO-000001')
        ->and((float) $order->total)->toBe(5000.0);
});

it('numbers purchase orders sequentially per company', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));
    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));

    expect(PurchaseOrder::query()->orderBy('id')->pluck('number')->all())
        ->toBe(['PO-000001', 'PO-000002']);
});

it('stores the ordered lines', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));
    $order = PurchaseOrder::query()->latest('id')->first();

    $item = PurchaseOrderItem::query()->where('purchase_order_id', $order->id)->first();

    expect($item->description)->toBe('Steel bolts')
        ->and($item->unit)->toBe('box')
        ->and((float) $item->line_total)->toBe(5000.0);
});

/* ---------------------------- Money ---------------------------- */

it('carves tax out of the ordered price the same way an invoice does', function () {
    [$user, $supplier] = purchaseOrderContext();

    $payload = purchaseOrderPayload($supplier);
    $payload['tax_percent'] = 16;

    $this->actingAs($user)->post('/purchase-orders', $payload);
    $order = PurchaseOrder::query()->latest('id')->first();

    expect((float) $order->total)->toBe(5000.0)
        ->and((float) $order->subtotal)->toBe(4310.34)
        ->and((float) $order->tax_total)->toBe(689.66);
});

it('rejects a tax rate outside 0 to 100', function () {
    [$user, $supplier] = purchaseOrderContext();

    $payload = purchaseOrderPayload($supplier);
    $payload['tax_percent'] = 140;

    $this->actingAs($user)
        ->post('/purchase-orders', $payload)
        ->assertSessionHasErrors('tax_percent');
});

/* ---------------------------- Guards ---------------------------- */

it('refuses a supplier from another company', function () {
    [$user] = purchaseOrderContext();
    [, $foreignSupplier] = purchaseOrderContext();

    $this->actingAs($user)
        ->post('/purchase-orders', purchaseOrderPayload($foreignSupplier))
        ->assertSessionHasErrors('supplier_id');
});

it('refuses to exceed the plan allowance', function () {
    [$user, $supplier] = purchaseOrderContext();
    $user->activePlan()->update(['max_purchase_orders' => 1]);

    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));

    $this->actingAs($user)
        ->post('/purchase-orders', purchaseOrderPayload($supplier))
        ->assertSessionHas('limit_notice');

    expect(PurchaseOrder::query()->count())->toBe(1);
});

it('rejects a status the order workflow does not use', function () {
    [$user, $supplier] = purchaseOrderContext();

    $payload = purchaseOrderPayload($supplier);
    $payload['status'] = 'paid';

    $this->actingAs($user)
        ->post('/purchase-orders', $payload)
        ->assertSessionHasErrors('status');
});

/* ---------------------------- Rendering ---------------------------- */

it('renders addressed to the supplier', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));
    $order = PurchaseOrder::query()->latest('id')->first();

    $response = $this->actingAs($user)->get("/purchase-orders/{$order->id}/preview");
    $response->assertSuccessful();

    expect($response->getContent())
        ->toContain('PURCHASE ORDER')
        ->toContain($supplier->name)
        ->toContain('Expected: 2026-08-15');
});

it('will not preview an order from another company', function () {
    [$user] = purchaseOrderContext();
    [$otherUser, $foreignSupplier] = purchaseOrderContext();

    $this->actingAs($otherUser)->post('/purchase-orders', purchaseOrderPayload($foreignSupplier));
    $foreignOrder = PurchaseOrder::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/purchase-orders/{$foreignOrder->id}/preview")
        ->assertForbidden();
});

/* ---------------------------- Status and index ---------------------------- */

it('updates the order status', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));
    $order = PurchaseOrder::query()->latest('id')->first();

    $this->actingAs($user)
        ->post("/purchase-orders/{$order->id}/status", ['status' => 'received'])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe('received');
});

it('lists purchase orders for the active company', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));

    $this->actingAs($user)
        ->get('/purchase-orders')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('PurchaseOrders/Index')
            ->has('purchaseOrders', 1)
            ->where('purchaseOrders.0.number', 'PO-000001')
            ->where('purchaseOrders.0.supplier_name', $supplier->name)
        );
});

it('offers the company suppliers on the create page', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)
        ->get('/purchase-orders/create')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('PurchaseOrders/Create')
            ->has('suppliers', 1)
            ->where('suppliers.0.name', $supplier->name)
        );
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test tests/Feature/PurchaseOrderCreationTest.php`
Expected: FAIL — 404, no `/purchase-orders` route

- [ ] **Step 4: Write the controller**

```bash
php artisan make:controller PurchaseOrderController --no-interaction
```

Replace `app/Http/Controllers/PurchaseOrderController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\DocumentRenderer;
use App\Services\SubscriptionLimitService;
use App\Services\TemplateProvisioner;
use App\Support\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private DocumentRenderer $documents,
        private TemplateProvisioner $templates,
    ) {}

    private function resolveCompanyId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $companyId = (int) ($user->current_company_id ?? 0);

        if ($companyId && $user->isMemberOfCompany($companyId)) {
            return $companyId;
        }

        $fallbackCompanyId = (int) ($user->companies()
            ->orderBy('companies.name')
            ->value('companies.id') ?? 0);

        if ($fallbackCompanyId) {
            $user->forceFill(['current_company_id' => $fallbackCompanyId])->save();
        }

        return $fallbackCompanyId ?: null;
    }

    private function companyId(Request $request): int
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No active company selected.',
            ]);
        }

        return $companyId;
    }

    private function supplierRule(int $companyId): Exists
    {
        return Rule::exists('suppliers', 'id')->where('company_id', $companyId);
    }

    /**
     * Totals for a purchase order.
     *
     * Identical arithmetic to {@see QuotationController::computeTotals()} and
     * {@see CreditNoteController::computeTotals()} — prices are tax-inclusive,
     * discounts come off the gross, tax is carved back out by subtraction. All
     * three must not drift apart.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, array<string, mixed>>, subtotal: float, purchase_order_discount: float, discount_total: float, tax_percent: float, tax_total: float, total: float}
     */
    private function computeTotals(array $items, float $orderDiscount, float $taxPercent): array
    {
        $itemsGross = 0.0;
        $lineDiscountTotal = 0.0;

        foreach ($items as $i => $row) {
            $qty = (float) $row['quantity'];
            $price = (float) $row['unit_price'];
            $discount = (float) ($row['discount'] ?? 0);

            $lineBase = $qty * $price;

            $items[$i]['discount'] = $discount;
            $items[$i]['tax'] = 0;
            $items[$i]['line_total'] = max(0, $lineBase - $discount);
            $items[$i]['sort_order'] = $i;

            $itemsGross += $lineBase;
            $lineDiscountTotal += $discount;
        }

        $discountTotal = $lineDiscountTotal + $orderDiscount;

        $total = round(max(0, $itemsGross - $discountTotal), 2);
        $subtotal = round($total / (1 + ($taxPercent / 100)), 2);
        $taxTotal = round($total - $subtotal, 2);

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'purchase_order_discount' => $orderDiscount,
            'discount_total' => $discountTotal,
            'tax_percent' => $taxPercent,
            'tax_total' => $taxTotal,
            'total' => $total,
        ];
    }

    public function index(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            return Inertia::render('PurchaseOrders/Index', [
                'purchaseOrders' => [],
                'hasActiveCompany' => false,
                'hasSuppliers' => false,
            ]);
        }

        $purchaseOrders = PurchaseOrder::query()
            ->where('company_id', $companyId)
            ->with(['supplier:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'supplier_name' => $order->supplier?->name,
                'issue_date' => $order->issue_date,
                'expected_date' => $order->expected_date,
                'currency_code' => $order->currency_code,
                'total' => (float) $order->total,
                'status' => $order->status,
            ]);

        return Inertia::render('PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'hasActiveCompany' => true,
            'hasSuppliers' => Supplier::query()->where('company_id', $companyId)->exists(),
        ]);
    }

    public function create(Request $request): Response
    {
        $companyId = $this->companyId($request);

        return Inertia::render('PurchaseOrders/Create', [
            'suppliers' => Supplier::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'contact_person', 'address']),
            'defaultCurrencyCode' => Company::defaultCurrencyCodeFor(
                $companyId,
                $request->user()?->current_currency_code,
            ),
            'statuses' => PurchaseOrder::STATUSES,
            'hasActiveCompany' => true,
            'limitNotice' => $request->session()->get('limit_notice'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $user = $request->user();
        $limiter = new SubscriptionLimitService($user);

        if (! $limiter->canCreatePurchaseOrder($companyId)) {
            return back()->with('limit_notice', $limiter->limitNotice('purchase orders'));
        }

        $data = $request->validate([
            'supplier_id' => ['required', 'integer', $this->supplierRule($companyId)],
            'title' => ['nullable', 'string', 'max:190'],
            'reference' => ['nullable', 'string', 'max:190'],
            'issue_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'status' => ['required', Rule::in(PurchaseOrder::STATUSES)],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'purchase_order_discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $totals = $this->computeTotals(
            $data['items'],
            (float) ($data['purchase_order_discount'] ?? 0),
            (float) ($data['tax_percent'] ?? 0),
        );

        $order = DB::transaction(function () use ($companyId, $user, $data, $totals): PurchaseOrder {
            $order = PurchaseOrder::query()->create([
                'company_id' => $companyId,
                'supplier_id' => (int) $data['supplier_id'],
                'purchase_order_template_id' => $this->templates
                    ->forCompany($companyId, DocumentType::PurchaseOrder)->id,
                'created_by' => $user?->id,
                'number' => DocumentNumber::nextFor(PurchaseOrder::class, $companyId, DocumentType::PurchaseOrder),
                'reference' => $data['reference'] ?? null,
                'title' => $data['title'] ?? null,
                'issue_date' => $data['issue_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'currency_code' => strtoupper((string) $data['currency_code']),
                'delivery_address' => $data['delivery_address'] ?? null,

                'subtotal' => $totals['subtotal'],
                'purchase_order_discount' => $totals['purchase_order_discount'],
                'discount_total' => $totals['discount_total'],
                'tax_percent' => $totals['tax_percent'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],

                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $order->items()->createMany($totals['items']);

            return $order;
        });

        return redirect()
            ->route('purchase-orders.show', $order)
            ->with('success', 'Purchase order '.$order->number.' created.');
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless((int) $purchaseOrder->company_id === $this->companyId($request), 403);

        $purchaseOrder->load([
            'supplier:id,company_id,name,email,contact_person,address',
            'items',
        ]);

        return Inertia::render('PurchaseOrders/show', [
            'purchaseOrder' => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->number,
                'title' => $purchaseOrder->title,
                'reference' => $purchaseOrder->reference,
                'status' => $purchaseOrder->status,
                'issue_date' => $purchaseOrder->issue_date,
                'expected_date' => $purchaseOrder->expected_date,
                'currency_code' => $purchaseOrder->currency_code,
                'delivery_address' => $purchaseOrder->delivery_address,

                'subtotal' => (float) $purchaseOrder->subtotal,
                'discount_total' => (float) $purchaseOrder->discount_total,
                'purchase_order_discount' => (float) ($purchaseOrder->purchase_order_discount ?? 0),
                'tax_percent' => (float) ($purchaseOrder->tax_percent ?? 0),
                'tax_total' => (float) $purchaseOrder->tax_total,
                'total' => (float) $purchaseOrder->total,

                'notes' => $purchaseOrder->notes,
                'terms' => $purchaseOrder->terms,

                'supplier' => $purchaseOrder->supplier,
                'items' => $purchaseOrder->items,
            ],
            'statuses' => PurchaseOrder::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless((int) $purchaseOrder->company_id === $this->companyId($request), 403);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(PurchaseOrder::STATUSES)],
        ]);

        if ($purchaseOrder->status === $data['status']) {
            return back()->with('info', 'Purchase order is already '.$data['status'].'.');
        }

        $purchaseOrder->update(['status' => $data['status']]);

        return back()->with('success', 'Purchase order is now '.$data['status'].'.');
    }

    public function preview(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless((int) $purchaseOrder->company_id === $this->companyId($request), 403);

        return response($this->documents->html($purchaseOrder, 'preview'));
    }

    public function print(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless((int) $purchaseOrder->company_id === $this->companyId($request), 403);

        return response()->view(
            'invoices.templates.default',
            $this->documents->viewData($purchaseOrder, 'print') + [
                'autoPrint' => $request->boolean('download'),
            ]
        );
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, after the delivery notes block:

```php
    // Purchase orders
    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\PurchaseOrderController::class, 'store'])->name('store');

        Route::get('/{purchaseOrder}', [\App\Http\Controllers\PurchaseOrderController::class, 'show'])
            ->whereNumber('purchaseOrder')
            ->name('show');

        Route::post('/{purchaseOrder}/status', [\App\Http\Controllers\PurchaseOrderController::class, 'updateStatus'])
            ->whereNumber('purchaseOrder')
            ->name('status');

        Route::get('/{purchaseOrder}/preview', [\App\Http\Controllers\PurchaseOrderController::class, 'preview'])
            ->whereNumber('purchaseOrder')
            ->name('preview');

        Route::get('/{purchaseOrder}/print', [\App\Http\Controllers\PurchaseOrderController::class, 'print'])
            ->whereNumber('purchaseOrder')
            ->name('print');
    });
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test tests/Feature/PurchaseOrderCreationTest.php`
Expected: PASS, 13 tests

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/PurchaseOrderController.php routes/web.php tests/Pest.php tests/Feature/PurchaseOrderCreationTest.php
git commit -m "feat: add purchase order creation, status changes and printing"
```

---

## Task 4.7: Purchase order screens

**Files:**
- Create: `resources/js/pages/PurchaseOrders/Index.tsx`, `Create.tsx`, `show.tsx`

- [ ] **Step 1: Build the three pages**

Copy the `Quotations` trio and adapt:

`Index.tsx` — prop `purchaseOrders` (`id`, `number`, `supplier_name`, `issue_date`, `expected_date`, `currency_code`, `total`, `status`). Column header says Supplier, not Client. Status pills are `draft` / `sent` / `approved` / `received` / `cancelled`. Rows link to `/purchase-orders/{id}`. Replace the prerequisites blocker with a single empty state keyed on `hasSuppliers`: when false, show "Add a supplier before raising a purchase order" with a button to `/suppliers`.

`Create.tsx` — supplier picker driven by the `suppliers` prop instead of the client picker, `expected_date` in place of `valid_until`, a `delivery_address` field, status select from the `statuses` prop, and no template picker or send-to-client toggle. Posts to `/purchase-orders`. Keep the line-item editor and the running-total panel exactly as the quotation form has them — the arithmetic is identical, so the UI should be too.

`show.tsx` — prop `purchaseOrder`. Header shows the supplier. Status actions post to `/purchase-orders/{id}/status`. Print and preview point at `/purchase-orders/{id}/print` and `/purchase-orders/{id}/preview`.

- [ ] **Step 2: Build the frontend**

Run: `npm run build`
Expected: build completes with no TypeScript errors

- [ ] **Step 3: Confirm the page tests pass**

Run: `php artisan test tests/Feature/PurchaseOrderCreationTest.php`
Expected: PASS, 13 tests

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/PurchaseOrders
git commit -m "feat: add purchase order screens"
```

---

# Phase 5 — Wiring and verification

## Task 5.1: Navigation

**Files:**
- Modify: `resources/js/components/app-sidebar.tsx`
- Test: `tests/Unit/SidebarConfigurationsTest.php`

- [ ] **Step 1: Add the failing assertions**

`tests/Unit/SidebarConfigurationsTest.php` already asserts against the sidebar config. Open it, see how it reads the file, and add assertions in the same style for the four new entries: `/credit-notes`, `/delivery-notes`, `/purchase-orders` under Documents, and `/suppliers` as a top-level item next to Clients.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/SidebarConfigurationsTest.php`
Expected: FAIL — the new hrefs are absent

- [ ] **Step 3: Extend the sidebar**

In `resources/js/components/app-sidebar.tsx`, add to the `Documents` group's `items` array, after Quotations:

```tsx
            {
                title: 'Credit Notes',
                href: '/credit-notes',
                icon: FileMinus,
            },
            {
                title: 'Delivery Notes',
                href: '/delivery-notes',
                icon: Truck,
            },
            {
                title: 'Purchase Orders',
                href: '/purchase-orders',
                icon: ShoppingCart,
            },
```

And after the Clients entry:

```tsx
    {
        title: 'Suppliers',
        href: '/suppliers',
        icon: Factory,
    },
```

Add `FileMinus`, `Truck`, `ShoppingCart` and `Factory` to the `lucide-react` import.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Unit/SidebarConfigurationsTest.php`
Expected: PASS

- [ ] **Step 5: Add breadcrumbs**

`tests/Unit/PageBreadcrumbsTest.php` checks that pages declare breadcrumbs. Run it and add breadcrumb declarations to any of the seven new pages it flags, matching the pattern in `resources/js/pages/Quotations/Index.tsx`.

Run: `php artisan test tests/Unit/PageBreadcrumbsTest.php`
Expected: PASS

- [ ] **Step 6: Build and commit**

```bash
npm run build
vendor/bin/pint --dirty
git add resources/js tests/Unit/SidebarConfigurationsTest.php
git commit -m "feat: add the new document types to navigation"
```

---

## Task 5.2: Full-suite verification

- [ ] **Step 1: Run the entire test suite**

Run: `php artisan test`
Expected: PASS, no failures

If anything fails, fix it before continuing. The most likely breakages, in order:
1. `tests/Unit/InvoiceDesignSystemTest.php` and `tests/Unit/InvoiceModuleAccessibilityTest.php` scan the invoice pages for design-system and a11y conformance — the payment panel and its form must satisfy them.
2. `tests/Unit/QuotationModuleAccessibilityTest.php` may have a sibling expectation that now covers the new modules.
3. Dashboard tests, if the outstanding-total change in Task 1.8 touched a shared query.

- [ ] **Step 2: Verify formatting across everything touched**

Run: `vendor/bin/pint`
Expected: no files reported as changed, or only formatting fixes you then commit

- [ ] **Step 3: Build the production bundle**

Run: `npm run build`
Expected: clean build

- [ ] **Step 4: Confirm the migrations run from empty**

Run: `php artisan migrate:fresh --seed`
Expected: every migration runs in order with no foreign key errors, and the seeders complete

This matters because the eight new tables have foreign keys into `companies`, `clients`, `invoices`, `suppliers` and `invoice_templates`. Migration filenames are timestamped in creation order, so `suppliers` must exist before `purchase_orders` — if you generated them out of order, rename the files to fix the sequence.

- [ ] **Step 5: Commit any fixes**

```bash
vendor/bin/pint --dirty
git add app database resources routes tests
git commit -m "chore: v1 document suite green"
```

Never `git add -A` in this repo right now — there is unrelated uncommitted work in the tree (a Facebook auth removal). Stage only the paths this plan touches.

---

## Task 5.3: Manual smoke test

Automated tests do not prove the screens are usable. Walk the whole thing once.

- [ ] **Step 1: Start the app**

Run: `composer run dev`

- [ ] **Step 2: Walk the flow**

Sign in, then confirm each of these end to end:

1. Create an invoice for 5,000. Record a 2,000 payment → invoice shows `partially_paid`, balance 3,000, receipt `RCP-000001` prints with the balance line.
2. Record a 3,000 payment → invoice shows `paid`, balance 0.
3. Delete the second payment → invoice returns to `partially_paid`, balance 3,000.
4. Raise a credit note for 3,000 against the same invoice, status `issued` → invoice returns to `paid`.
5. Void that credit note → invoice returns to `partially_paid`.
6. Try a credit note larger than the balance → blocked with a readable message.
7. Generate a delivery note from the invoice → print it and confirm **no prices anywhere** and a signature block at the bottom.
8. Add a supplier, raise a purchase order, print it → addressed to the supplier, shows `Expected:` date, totals present.
9. Check `/clients` does not list the supplier.
10. Check the dashboard's outstanding figure matches the sum of real balances.

- [ ] **Step 3: Note anything broken and fix it before shipping**

---

# Self-review notes

Deliberate decisions a reviewer might otherwise flag as omissions:

- **No Form Request classes.** The codebase validates inline in controllers because rules close over a runtime company id. Following the existing pattern beats following generic Laravel advice here.
- **`InvoiceDocumentRenderer` and `QuotationDocumentRenderer` untouched.** They work and are tested. Folding all six types into `DocumentRenderer` is a post-v1 cleanup, not a v1 risk to take.
- **Credit notes have no email-to-client flow.** Invoices and quotations do. Adding it means a third mailable and a third delivery path; the credit note can be printed and sent manually for v1. Worth adding later.
- **Receipts are not emailed automatically** on payment, for the same reason.
- **Numbering counts rows rather than using a sequence table.** This races under concurrent creation, but it is exactly what invoices and quotations already do. Fixing it properly means one change across all six types — a single follow-up task, not six inconsistent ones.
- **Purchase orders do not create a payables ledger.** They record what was ordered, not what is owed to suppliers. Supplier bills and payables are a genuine product expansion, explicitly out of v1 scope.
- **Delivery notes have no partial-dispatch support.** One note copies the whole invoice. Splitting a shipment across several notes needs a per-line dispatched quantity, which is a Phase 6 concern if customers ask.

---

# What changed during execution

This plan was executed on 2026-07-30/31. Everything above is the plan as written; this section records where reality diverged. Where the two disagree, **this section is correct**.

## Defects found in the plan itself

**`instanceof` on an undefined variable (Task 0.4).** The blade code used `$documentType instanceof DocumentType`, but `InvoiceDocumentRenderer` passes no `documentType` key at all, so `instanceof` raised `Undefined variable` and broke 11 existing invoice tests. Shipped as `($documentType ?? null) instanceof ...`. The plan's own test would not have caught it — passing `null` still defines the variable — so a guard test that omits the array key entirely was added.

**`sync()` promoted unissued drafts (Task 1.4).** `default => 'sent'` fired whenever nothing was settled. Since `invoices.status` defaults to `draft`, any speculative `sync()` silently promoted a draft to `sent`, dragging never-issued invoices into every outstanding roll-up. The resting arm now only reverses a settlement:
```php
in_array($invoice->status, [STATUS_PAID, STATUS_PARTIALLY_PAID], true) => 'sent',
default => $invoice->status,
```

**Two rounding tests that guarded nothing (Tasks 1.4, 3.3).** `333.33 − 333.33` and `5000 − (1666.67 + 1666.67 + 1666.66)` are both exactly zero in IEEE doubles, so they passed with `round()` deleted. Replaced with `1000.33` split as `333.45 + 333.44 + 333.44`, which lands 1.1e-13 short and genuinely fails under mutation. Likewise the delivery note's `not->toContain('750.00')` was vacuous — delivery note items carry no price columns, so the sheet would render `0.00`, never `750.00`. Strengthened with a positive control and column-header assertions.

**A currency test that proved less than it looked (Task 2.4).** "Copies the invoice currency rather than trusting the form" would also have passed against a hardcoded company default, because the fixture invoice is company-denominated. A second test now moves the invoice to USD under a ZMW company.

**`UpdatePlanRequest` needed no edit (Task 4.5)** — it inherits via `parent::rules()`. The plan said to change it.

**`migrate:fresh --seed` (Task 5.2, Step 4) must never be run.** It destroyed the developer's local database during execution. The safe equivalent: `RefreshDatabase` already migrates the test database from empty on every run with foreign keys enforced, so a green suite *is* the proof that migrations run cleanly from scratch.

## Design changes made during execution

**Receipt balances are frozen at issuance.** `getNotesAttribute()` originally recomputed the balance live, so reprinting an older receipt showed the balance *now*. A customer's paper copy saying "3,000 owed" would disagree with a reprint saying "1,500". Added `invoice_payments.balance_after`, stamped inside the recording transaction; the live figure survives only as a fallback for rows predating the column.

**Document numbers derive from the highest issued, not a row count.** Counting reissues live numbers after deletions — delete two of three receipts and the sequence walks back onto a surviving number, violating the unique index mid-transaction. Receipts are the first document type with a delete path, so this stopped being theoretical. `DocumentNumber::nextFor()` gained a fourth parameter, `string $column = 'number'`, because a payment's number lives in `receipt_number`.

**The companies list was netted off too.** Task 1.8 fixed the dashboard but left `CompanyController` summing raw totals, so the two surfaces disagreed about the same money.

**`STATUS_TONES` gained five entries.** `issued`, `dispatched`, `delivered`, `approved` and `received` all fell through to the amber fallback, rendering identically to `draft` — on documents where that pair is exactly what must not look alike.

**Dates are serialised server-side.** Eloquent date casts serialise as full ISO-8601, which `<input type="date">` renders blank. The delivery note payload now sends `Y-m-d`. The printed sheet formats through a `$date()` closure, fixing a pre-existing divergence where a saved document printed `2026-07-31 00:00:00` while its preview printed `2026-07-31`.

**`closingTitle()` was added to `DocumentType`.** Making the closing line type-aware left a hardcoded "Thank you for your business!" above it, so a delivery note read "Thank you for your business!" over "Please check the goods on arrival".

**Unsaved-preview dialogs were stripped from the copied Create pages.** Only quotations have a `POST /preview` route for unsaved drafts; carrying the UI to credit notes and purchase orders would have shipped buttons that 404.

**`pluralLabel()` was removed** from `DocumentType` — speculative, no caller.

## Known gaps, deliberately not closed

- **No credit note can be raised from an invoice.** You must go Documents → Credit Notes → New and re-pick the invoice. `CreditNotes/Create.tsx` does not read an `invoice_id` query parameter. The natural gesture is missing; everything is still reachable.
- **Nothing dedupes delivery notes.** Clicking "Delivery note" twice creates two. The button disables while in flight, but the fix needs `has_delivery_note` added to the invoice payload so the button can confirm or become a link.
- **Purchase order statuses are freely clickable** in both directions — a `received` order can be clicked back to `draft`. Mirrors quotations; a PO arguably wants a one-way workflow.
- **Nothing suggests a currency for a foreign supplier.** `suppliers` has no `default_currency_code` and one was deliberately not added.
- **Concurrency.** Payment caps, credit-note headroom and document numbering all read state outside the transaction that writes it. Two simultaneous writers can overshoot. The fix is `lockForUpdate()` in each transaction; deferred as one coherent follow-up rather than three inconsistent ones.
- **`paid_at`** is written by `InvoiceController::updateStatus()` but has no migration and is not in `$fillable`, so those writes are silently discarded. Pre-existing.
- **23 files carry pre-existing Pint style debt** that `--dirty` never catches. Worth its own commit.
