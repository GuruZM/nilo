# App Shell — Centered 4/5 Detached Frame

**Date:** 2026-07-19
**Status:** Approved design, pending implementation

## Goal

Restructure the authenticated app shell so the navigation sidebar (the links) and
the main content sit inside a centered container that spans **4/5 (80%) of the
viewport width**, with a visible gap between them so both read as **detached
floating panels**. The dashboard's inner content — and every other page's content
— is unchanged. This applies **app-wide** (every page using `AppSidebarLayout`),
not just the dashboard.

Out of scope: swapping the dashboard's Recharts charts for Bklit UI. That is a
separate follow-up task (see "Deferred" below).

## Current state

`AppSidebarLayout` → `AppShell variant="sidebar"` renders shadcn's `SidebarProvider`
containing `AppSidebar` + `AppContent` (which renders `SidebarInset`).

The shadcn `Sidebar` (currently `variant="inset"`, `collapsible="icon"`) renders
**two** elements on desktop:

1. An **in-flow "gap" spacer** `div` that reserves `--sidebar-width` (17rem) of
   layout space at the left of the flex wrapper.
2. A **`position: fixed`** overlay pinned to `left-0` (the viewport's left edge)
   that visually fills that reserved column.

Because the wrapper starts at the viewport's left edge, the fixed overlay (`left-0`)
and the in-flow gap coincide. The main content (`SidebarInset`) is a floating card
flush beside the sidebar (`m-2 ml-0 rounded-xl shadow-sm`), keyed on
`peer-data-[variant=inset]`.

Mobile (`< md`): the sidebar becomes a slide-over `Sheet`; no fixed overlay.

## Approach (chosen: A — offset the fixed sidebar)

Keep shadcn's `ui/sidebar.tsx` internals untouched. Center the whole shell to
`w-4/5` and shift the fixed sidebar overlay by the resulting left margin so it stays
aligned with its in-flow gap. Switch the sidebar to the `floating` variant so it
renders as its own rounded/bordered/shadowed card, and give the main content
matching card styling with a left margin for the gap.

**Why the offset works:** `w-4/5` centered = 80vw with **exactly 10vw margin** on
each side. A `fixed` element's percentage offsets are relative to the viewport, so
`left-[10%]` = 10vw, which lines the overlay up with the gap spacer at the left edge
of the centered band. This holds while collapsed to icon width too (the gap and
overlay share the same width rules; only `left` matters, and it stays `10%`).

**Constraint:** the frame must be *exactly* 80vw — do **not** add a `max-width`
cap, or the 10vw/10% math no longer matches. "4/5" stays literally 4/5 on all
desktop widths.

Rejected alternative (B): rewrite the desktop sidebar from `fixed` to `sticky`
in-flow. More "correct" and allows a max-width cap, but edits shadcn core
positioning and risks the collapse animation and mobile sheet. Not worth the risk
for this change.

## Changes

### 1. `resources/js/components/app-shell.tsx`
On the `sidebar` variant, center the provider wrapper on desktop, full width on
mobile. Add to the `SidebarProvider` (its `className` merges into the
`sidebar-wrapper` div):

```
className="md:mx-auto md:w-4/5"
```

`w-full` (base) still governs `< md`; `md:w-4/5` + `md:mx-auto` center the band on
desktop. Keep `defaultOpen` and the `--sidebar-width: 17rem` style.

### 2. `resources/js/components/app-sidebar.tsx`
- `variant="inset"` → `variant="floating"` (sidebar becomes a detached card:
  rounded, bordered, shadowed — built into the floating variant).
- Add `className="md:left-[10%]"` to `<Sidebar>` so the fixed overlay aligns with
  the centered gap. (`md:` only — below `md` the sidebar is the mobile Sheet.)
- Keep `collapsible="icon"`.

### 3. `resources/js/layouts/app/app-sidebar-layout.tsx` (content card + gap)
Switching to `floating` removes the `inset`-keyed card styling from `SidebarInset`,
so restyle the main content as a matching detached card via the `AppContent`
`className`:

- `bg-background md:m-2 md:rounded-xl md:border md:shadow-sm` — card look; the left
  `m-2` creates the visible gap between sidebar and content.
- Preserve `overflow-x-hidden`.
- Replace the lost inset min-height so the card fills the viewport without a tiny
  scroll: `md:min-h-[calc(100svh-(--spacing(4)))]` (matches what `inset` applied).

The inner `mx-auto w-full max-w-[1400px] px-4 sm:px-6 lg:px-8` content wrapper and
`AppSidebarHeader` stay as-is.

## Behavior after change

- **Desktop:** every authenticated page shows the nav card and the content card
  floating side-by-side, detached by a gap, centered in an 80vw band with ~10vw of
  breathing room on each side. Icon-collapse still works and stays aligned.
- **Mobile (`< md`):** unchanged — full-width content, sidebar as a slide-over sheet.
- **No new dependencies.** Pure Tailwind class changes across three files.

## Verification

- Load the dashboard and at least one other page (e.g. `/invoices`) on a desktop
  width: sidebar and content are two detached cards, centered, ~10vw side margins,
  no horizontal overflow.
- Toggle the sidebar collapse (icon mode): the sidebar card stays aligned with its
  reserved column; content reflows without a gap jump.
- Resize across the `md` breakpoint: mobile falls back to full width + sheet with no
  layout breakage.
- Confirm no vertical scrollbar caused by the content card margins (height calc).

## Deferred (separate task)

Migrate the dashboard's three Recharts charts (Paid-vs-Pending pie, Monthly-trend
line, Aging bar) to Bklit UI (`shadcn add @bklit/<chart>` — a shadcn registry that
copies source into the components dir; no locked runtime dep). Not part of this
change.
