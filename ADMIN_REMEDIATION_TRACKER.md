# Admin Dashboard Remediation Tracker

This file is the durable source of truth for the 69-item admin audit. Update it in the same commit as each fix so work can resume safely after a context reset.

## Resume protocol

1. Run `git status --short` and preserve unrelated/user files.
2. Read this file and `git log -5 --oneline`.
3. Resume the first `IN PROGRESS` item, otherwise the first `TODO` item in the current phase.
4. Run the verification named in the issue evidence before marking it `VERIFIED`.
5. Record the fixing commit hash in the next checkpoint update and push only after checks pass.

Statuses: `TODO` → `IN PROGRESS` → `FIXED` → `VERIFIED`; use `BLOCKED` only with a concrete reason.

## Checkpoint

- Audit baseline: `293df13`
- Current phase: Phase 1 — authorization, CSRF, session security, and data integrity
- Next item: `ADM-004` stored-XSS output audit and remediation
- Progress: 0 verified / 69 total
- Verification constraints: local MySQL was unavailable at audit time; authenticated browser testing requires a working database and an admin account.

## Phase 1 — Security and data integrity

- [ ] `ADM-001` **P0 Security** — Mutation endpoints do not consistently enforce role permissions. Status: `FIXED` (role-matrix integration tests pending). Evidence: permission tests for every mutation endpoint. Commit: `76f334d` + current batch
- [ ] `ADM-002` **P0 Security** — Most admin mutations have no CSRF protection. Status: `FIXED` (valid/invalid-token integration tests pending). Evidence: valid/invalid-token POST and JSON tests. Commit: `76f334d` + current batch
- [ ] `ADM-003` **P0 Data integrity** — Booking hold stores an admin ID in a donor-user foreign key and joins the wrong table. Status: `FIXED` (dynamic migration/hold test pending). Evidence: schema/query correction and hold toggle test. Commit: current batch
- [ ] `ADM-004` **P0 Security** — Reservation data reaches HTML/JavaScript without consistent escaping, enabling stored XSS. Status: `TODO`. Evidence: malicious-text rendering test. Commit: —
- [ ] `ADM-005` **P0 Security** — Pricing notes are injected into inline JavaScript unsafely. Status: `FIXED` (browser payload test pending). Evidence: quote/script payload test. Commit: current batch
- [ ] `ADM-006` **P0 Reliability** — Standalone analytics queries a nonexistent `dhana_type` field. Status: `TODO`. Evidence: analytics page and endpoint smoke tests. Commit: —
- [ ] `ADM-007` **P0 Logic** — Reservation status selector omits `receipt_submitted`. Status: `FIXED` (browser verification pending). Evidence: all schema statuses rendered and accepted. Commit: current batch
- [ ] `ADM-008` **P0 UX/Data integrity** — Changing a status immediately submits with no review or recovery. Status: `FIXED` (browser verification pending). Evidence: explicit save/confirm flow. Commit: current batch
- [ ] `ADM-009` **P0 Data integrity** — Approval processing is incomplete and non-atomic. Status: `FIXED` (concurrency/integration tests pending). Evidence: each action type applies transactionally or is rejected. Commit: current batch
- [ ] `ADM-010` **P0 Security** — Destructive settings actions are insufficiently protected. Status: `FIXED` (integration tests pending). Evidence: permission, CSRF, and confirmation tests. Commit: current batch

## Phase 2 — Admin workflows and business logic

- [ ] `ADM-011` **P1 Reliability** — Settings tabs depend on a browser-global `event`. Status: `FIXED` (browser keyboard/mouse tests pending). Evidence: mouse and keyboard tab tests. Commit: current batch
- [ ] `ADM-012` **P1 Architecture** — Settings view silently alters the database schema. Status: `FIXED` (GET smoke test pending). Evidence: migration exists and GET is read-only. Commit: current batch
- [ ] `ADM-013` **P1 Security** — Permission editing can lock every administrator out. Status: `FIXED` (last-admin tests pending). Evidence: last-admin permission safeguards. Commit: current batch
- [ ] `ADM-014` **P1 Logic** — Multiple independent settings actions can execute from one POST. Status: `FIXED` (crafted POST test pending). Evidence: single action dispatch tests. Commit: current batch
- [ ] `ADM-015` **P1 UX** — Reservation edit modal can disable the wrong primary button. Status: `TODO`. Evidence: edit/save button state test. Commit: —
- [ ] `ADM-016` **P1 Maintainability** — Booking details rendering is duplicated. Status: `TODO`. Evidence: one implementation and detail-modal regression test. Commit: —
- [ ] `ADM-017` **P1 Reliability** — Global modal handlers overwrite each other. Status: `TODO`. Evidence: each modal closes independently by button/Escape/backdrop. Commit: —
- [ ] `ADM-018` **P1 Logic** — Annual-booking edits are inconsistent across parent and child rows. Status: `TODO`. Evidence: parent/child update scenarios. Commit: —
- [ ] `ADM-019` **P1 Reliability** — Email is sent inside the booking edit transaction. Status: `FIXED` (failure-path integration test pending). Evidence: DB commit remains correct when mail fails. Commit: current batch
- [ ] `ADM-020` **P1 Privacy** — Deleting a booking leaves physical receipt files behind. Status: `TODO`. Evidence: safe file cleanup test. Commit: —
- [ ] `ADM-021` **P1 Auditability** — Booking deletion has no durable audit trail. Status: `TODO`. Evidence: actor, target, time, and before-state recorded. Commit: —
- [ ] `ADM-022` **P1 Logic** — Admin calendar has a hard 2030 year ceiling. Status: `TODO`. Evidence: settings-driven range boundary tests. Commit: —
- [ ] `ADM-023` **P1 Edge case** — Annual calendar behavior is undefined for February 29. Status: `TODO`. Evidence: leap-day recurrence test. Commit: —
- [ ] `ADM-024` **P1 Logic** — Calendar full/partial availability calculation does not match slot-conflict rules. Status: `TODO`. Evidence: whole-day/morning/lunch matrix. Commit: —
- [ ] `ADM-025` **P1 Data integrity** — Receipt/annual joins can duplicate booking rows. Status: `TODO`. Evidence: multi-receipt/annual query test. Commit: —
- [ ] `ADM-026` **P1 Security** — Receipt access is inconsistent and direct upload paths are exposed. Status: `TODO`. Evidence: authorized streaming only; direct access denied. Commit: —
- [ ] `ADM-027` **P1 Security** — Reports do not enforce report/export permissions. Status: `TODO`. Evidence: role matrix endpoint tests. Commit: —
- [ ] `ADM-028` **P1 Correctness** — The PDF report option returns HTML rather than a PDF. Status: `TODO`. Evidence: valid PDF signature/content or honest UI label. Commit: —
- [ ] `ADM-029` **P1 Security** — CSV export is vulnerable to spreadsheet formula injection. Status: `TODO`. Evidence: cells beginning `= + - @` neutralized. Commit: —
- [ ] `ADM-030` **P1 Data integrity** — Pricing updates are not transactional. Status: `FIXED` (rollback integration test pending). Evidence: rollback-on-failure test. Commit: current batch
- [ ] `ADM-031` **P1 Validation** — Pricing inputs are weakly validated server-side. Status: `FIXED` (boundary integration tests pending). Evidence: invalid amount/month/type boundary tests. Commit: current batch
- [ ] `ADM-032` **P1 Logic** — Pricing-window calculation ignores direction/boundary cases. Status: `FIXED` (date-boundary integration tests pending). Evidence: past/current/future month tests. Commit: current batch
- [ ] `ADM-033` **P1 Deployment** — Backup flow lacks shared-hosting preflight and reliable `mysqldump` discovery. Status: `TODO`. Evidence: actionable failure/success diagnostics. Commit: —
- [ ] `ADM-034` **P1 UX/Reliability** — Backend errors are frequently invisible or overly generic. Status: `TODO`. Evidence: consistent safe error surfaces and server logs. Commit: —
- [ ] `ADM-035` **P1 Security** — APIs expose raw internal error details. Status: `TODO`. Evidence: production-safe JSON errors. Commit: —

## Phase 3 — Authentication and account recovery

- [ ] `ADM-036` **P1 Security** — Admin login does not regenerate the session ID. Status: `TODO`. Evidence: session ID changes after login. Commit: —
- [ ] `ADM-037` **P1 Security** — Admin sessions have no inactivity timeout. Status: `TODO`. Evidence: expiry and activity refresh tests. Commit: —
- [ ] `ADM-038` **P1 Security** — Role/permission state remains stale in the session after account changes. Status: `TODO`. Evidence: disable/role-change takes effect next request. Commit: —
- [ ] `ADM-039` **P1 Security** — Admin login has no throttling. Status: `TODO`. Evidence: repeated-failure throttling test. Commit: —
- [ ] `ADM-040` **P1 Security** — Logout is a state-changing GET request. Status: `TODO`. Evidence: CSRF-protected POST logout. Commit: —
- [ ] `ADM-041` **P1 UX/Security** — Admin password recovery, autocomplete, and loading feedback are missing. Status: `TODO`. Evidence: recovery happy/failure paths and form audit. Commit: —

## Phase 4 — Shared admin design system and screen redesign

- [ ] `ADM-042` **P2 UI** — The admin area has no single enforced design system. Status: `TODO`. Evidence: shared tokens/components across all pages. Commit: —
- [ ] `ADM-043` **P2 UX** — Navigation is duplicated and inconsistent across pages. Status: `TODO`. Evidence: one shared shell/navigation. Commit: —
- [ ] `ADM-044` **P2 Logic/UX** — Pending-approvals badge counts pending bookings rather than approval actions. Status: `TODO`. Evidence: badge query and empty-state test. Commit: —
- [ ] `ADM-045` **P2 UX** — Role management is misleading and does not clearly support donor-to-agent/staff assignment. Status: `TODO`. Evidence: end-to-end assignment flows. Commit: —
- [ ] `ADM-046` **P2 UI** — Dashboard header is overloaded and poorly prioritized. Status: `TODO`. Evidence: responsive visual review. Commit: —
- [ ] `ADM-047` **P2 UI** — Sidebar is excessively tall and vertically inefficient. Status: `TODO`. Evidence: 768px-height viewport review. Commit: —
- [ ] `ADM-048` **P2 Accessibility** — Clickable `div` navigation is not keyboard/semantic accessible. Status: `TODO`. Evidence: keyboard-only navigation. Commit: —
- [ ] `ADM-049` **P2 UX** — Reservation table is too wide and action-heavy. Status: `TODO`. Evidence: desktop/tablet/mobile layout review. Commit: —
- [ ] `ADM-050` **P2 UX** — Supervisors see duplicated/redundant status information. Status: `TODO`. Evidence: role-specific table review. Commit: —
- [ ] `ADM-051` **P2 UI** — Button hierarchy is unclear and inconsistent. Status: `TODO`. Evidence: primary/secondary/danger component audit. Commit: —
- [ ] `ADM-052` **P2 UX** — Native alert/prompt/confirm dialogs interrupt workflows. Status: `TODO`. Evidence: consistent app dialogs/toasts. Commit: —
- [ ] `ADM-053` **P2 Accessibility** — Modals lack focus management, roles, labels, and Escape behavior. Status: `TODO`. Evidence: keyboard/screen-reader checklist. Commit: —
- [ ] `ADM-054` **P2 UX** — Nested dialog scrolling creates confusing double-scroll layouts. Status: `TODO`. Evidence: short and tall viewport review. Commit: —
- [ ] `ADM-055` **P2 UX** — Settings overloads routine and high-risk actions on one screen. Status: `TODO`. Evidence: reorganized information architecture. Commit: —
- [ ] `ADM-056` **P2 Correctness** — Settings contains invalid nested style markup. Status: `FIXED` (render validation pending). Evidence: HTML validation/source inspection. Commit: current batch
- [ ] `ADM-057` **P2 UX** — The 72-column pricing grid is not practically usable. Status: `TODO`. Evidence: focused period/type workflow and responsive review. Commit: —
- [ ] `ADM-058` **P2 Edge case** — Empty pricing data displays January 1970. Status: `FIXED` (empty-database render pending). Evidence: empty-state test. Commit: current batch
- [ ] `ADM-059` **P2 UX** — Analytics is duplicated between dashboard and standalone page. Status: `TODO`. Evidence: one coherent analytics route/navigation. Commit: —
- [ ] `ADM-060` **P2 Correctness** — Currency formatting uses `$` instead of Rs/LKR in places. Status: `TODO`. Evidence: cross-page currency audit. Commit: —
- [ ] `ADM-061` **P2 Maintainability** — `admin.css` contains conflicting repeated definitions. Status: `TODO`. Evidence: cascade audit and visual regression review. Commit: —
- [ ] `ADM-062` **P2 Correctness** — `admin-pro-ui` targets nonexistent classes and only affects part of the dashboard. Status: `TODO`. Evidence: selector audit and removal/integration. Commit: —
- [ ] `ADM-063` **P2 Maintainability** — Inline styles are widespread and block consistent theming. Status: `TODO`. Evidence: reusable component classes on admin screens. Commit: —
- [ ] `ADM-064` **P2 UX** — Loading, error, success, and empty states are inconsistent. Status: `TODO`. Evidence: shared state patterns across screens. Commit: —

## Phase 5 — Performance, structure, and resilience

- [ ] `ADM-065` **P2 Performance** — Dashboard eagerly loads hidden features and data. Status: `TODO`. Evidence: request/query comparison and lazy loading. Commit: —
- [ ] `ADM-066` **P2 UX/Logic** — Reservation search only searches the current page. Status: `TODO`. Evidence: server-side full-result search test. Commit: —
- [ ] `ADM-067` **P2 Performance** — Pricing/permission views contain N+1 query patterns. Status: `TODO`. Evidence: query-count comparison. Commit: —
- [ ] `ADM-068` **P2 Maintainability** — Giant page files mix querying, mutation, markup, CSS, and JS. Status: `TODO`. Evidence: bounded includes/services with regression tests. Commit: —
- [ ] `ADM-069` **P2 Resilience** — Optional database feature errors can crash the whole dashboard. Status: `TODO`. Evidence: graceful degradation with missing optional tables/data. Commit: —

## Verification log

Add dated entries here with command/browser evidence. Never include credentials, uploaded receipts, or production data.

- 2026-09-14 — Tracker created from the audit of baseline `293df13`; no issues marked fixed without verification.
- 2026-09-14 — Added shared admin security helpers; secured booking update/delete/hold/detail routes; added explicit status save and the missing receipt status; corrected hold ownership schema with a one-time migration; moved update email after commit. PHP lint and the elevated Node syntax check passed.
- 2026-09-14 — Secured all settings/pricing/role/approval POST flows with one-action dispatch, CSRF, permission checks, and safe errors; made approvals atomic and reject unsupported types; protected the last administrator permissions/role; made pricing writes transactional with bounded input; fixed note encoding, signed pricing window calculation, settings tabs, invalid style nesting, and empty pricing dates. PHP lint passed.
