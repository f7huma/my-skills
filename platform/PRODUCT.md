# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users
- **The lawyer (primary):** Faisal Humaidan Al-Humaidan, who runs a Saudi law office (license 47529). He works at a desk on desktop and checks on his phone between sessions. Each morning his first job is to see today's and this week's hearings and tasks, and the statutory deadlines that are approaching (execution compliance, objections). After that he records filings, hearing outcomes, judgments, executions and collections.
- **Clients (portal):** an even mix of companies (finance or legal managers following several cases and collections, some allowed to submit claims and some view-only) and individuals following one or two cases. They want to know a case's status, its latest update, and how much has been collected, at a glance.

## Product Purpose
A private office workspace for the case lifecycle: client, power of attorney, case, Taradhi reconciliation, Najiz filing, hearings, memos, judgment, deadlines, execution, collections and reports. Each client gets an isolated portal showing only its own cases, executions and collected amounts, plus claim submission. Success means the lawyer never misses a deadline or a hearing, and clients stop phoning to ask where things stand.

## Positioning
A single-office platform, self-hosted on the office's own server. Nothing is processed by external storage, AI or analytics. Files are encrypted at rest and virus-scanned. Najiz and Taradhi are recorded manually, with no credential use and no login simulation.

## Operating Context
- Arabic RTL first; dates appear in both Hijri (Umm al-Qura) and Gregorian, in Riyadh time.
- Business days are Sunday–Thursday, and official holidays are kept in a holidays table.
- Deadline rules are versioned and apply only after the lawyer reviews and approves them.
- Reports are exported to PDF.

## Capabilities and Constraints
- Stack: Laravel 13, Inertia React 19 TS, Tailwind v4, PostgreSQL 17, Docker on a Hostinger VPS.
- Strict CSP: no external fonts, scripts or CDNs; every asset is self-hosted.
- All routes, permissions, data scoping and security behavior must be preserved.
- The owner is non-technical and deploys by pasting one command.

## Brand Commitments
- The logo is set in dark slate charcoal ink, #323943 (`public/brand/*`).
- The office name: «مكتب فيصل حميضان الحميضان للمحاماة والاستشارات القانونية».
- The impression clients must get: a long-established, trustworthy Saudi law office (owner's answer).

## Evidence on Hand
- Logo files are in `public/brand/`.
- No real client data is in the repo; the preview environment uses synthetic data only.
- There are no testimonials or claims to show, and none may be invented.

## Product Principles
1. A deadline is never missed: time pressure is always visible before anything else.
2. Each client sees only its own matters, with no leakage by design or by layout.
3. The facts come first: status, amount and next step, readable at a glance in Arabic.
4. Office-grade gravity: calm authority over gadgetry.

## Accessibility & Inclusion
- Text contrast of at least 4.5:1.
- Full keyboard use with a visible focus state.
- Respect `prefers-reduced-motion`.
- Mobile usable at 390px.
