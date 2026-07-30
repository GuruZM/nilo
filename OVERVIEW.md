# Nilo — Project Overview

## What It Is

Nilo is a **multi-tenant SaaS invoicing and quotation platform** for small businesses. Users can create companies, manage clients, generate invoices/quotations, track payments, and analyze revenue — all within a polished, modern UI.

---

## Core Domain Models

| Model | Key Relationships |
|---|---|
| `User` | belongs to many `Company` (with role pivot) |
| `Company` | has many `Client`, `Invoice`, `Quotation`, `InvoiceTemplate` |
| `Client` | has many `Invoice`, `Quotation` |
| `Invoice` | has many `InvoiceItem`, belongs to `Client`, `InvoiceTemplate` |
| `Quotation` | has many `QuotationItem`, belongs to `Client` |
| `InvoiceTemplate` | JSON settings, belongs to `Company` |
| `Currency` | standalone, ISO-standard currency codes |

Multi-tenant via `company_user` pivot with `is_owner` and `status` fields. Each session has a `current_company_id` for context switching.

---

## Key Features

- **Invoices** — CRUD, status lifecycle (draft → sent → paid/void/overdue), recurring support, PDF export, due date aging
- **Quotations** — Same structure as invoices, validity dates, separate templates
- **Template Builder** — Visual customization (colors, fonts, layout), JSON settings, HTML terms/footer, live preview
- **Dashboard Analytics** — 12-month revenue chart, aging analysis, top clients, upcoming due dates
- **Multi-Company** — Own/join multiple companies, quick switcher
- **Multi-Currency** — ISO codes with symbol and precision per currency
- **Client Management** — Contact info, TPIN, per-client invoice/quotation history

---

## Tech Stack

**Backend:** Laravel 12, PHP 8.3, Fortify (auth), Cashier (Stripe-ready), DomPDF, Spatie Permission, Pest tests

**Frontend:** React 19, Inertia.js v2, Tailwind CSS v4, Radix UI, Recharts, Framer Motion, TypeScript

**Architecture:** Inertia SSR-style routing, Form Requests for validation, Eloquent resources, no Redux (React hooks only)

---

## Notable Business Logic

- **Recurring invoices** — `next_run_at` field with daily/weekly/monthly/yearly frequency
- **Template system** — type differentiates invoice vs quotation; "wave_premium" is the default preset
- **Dashboard SQL** — Complex aggregations for 12-month trends with NULL month handling
- **Preview before save** — `/preview` POST route renders template without committing to DB
- **Line item math** — quantity × unit_price → discount → tax → total, stored server-side with `decimal(14,2)`

---

## Project Status

Latest commits suggest it just completed the **invoice template builder** and reached a "pilot ready" → "stable" milestone. The app appears production-ready for core invoicing workflows.
