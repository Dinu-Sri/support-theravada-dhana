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
- Current phase: Phase 4 — shared admin design system and screen redesign
- Next item: `ADM-045` clarify and complete donor-to-agent/staff assignment
- Progress: 51 fixed / 69 total; 2 runtime-verified
- Verification constraints: the local MySQL service still refuses connections; authenticated browser and database integration checks remain pending.

## Phase 1 — Security and data integrity

- [ ] `ADM-001` **P0 Security** — Mutation endpoints do not consistently enforce role permissions. Status: `FIXED` (role-matrix integration tests pending). Evidence: permission tests for every mutation endpoint. Commit: `76f334d`, `cde0085`, `77f3ffe`, `7e952a7`
- [ ] `ADM-002` **P0 Security** — Most admin mutations have no CSRF protection. Status: `FIXED` (valid/invalid-token integration tests pending). Evidence: valid/invalid-token POST and JSON tests. Commit: `76f334d`, `cde0085`, `77f3ffe`
- [ ] `ADM-003` **P0 Data integrity** — Booking hold stores an admin ID in a donor-user foreign key and joins the wrong table. Status: `FIXED` (dynamic migration/hold test pending). Evidence: schema/query correction and hold toggle test. Commit: `76f334d`
- [ ] `ADM-004` **P0 Security** — Reservation data reaches HTML/JavaScript without consistent escaping, enabling stored XSS. Status: `FIXED` (browser payload test pending). Evidence: malicious-text rendering test. Commit: `77f3ffe`
- [ ] `ADM-005` **P0 Security** — Pricing notes are injected into inline JavaScript unsafely. Status: `FIXED` (browser payload test pending). Evidence: quote/script payload test. Commit: `cde0085`
- [ ] `ADM-006` **P0 Reliability** — Standalone analytics queries a nonexistent `dhana_type` field. Status: `FIXED` (database smoke test pending). Evidence: analytics page and endpoint smoke tests. Commit: `77f3ffe`
- [ ] `ADM-007` **P0 Logic** — Reservation status selector omits `receipt_submitted`. Status: `FIXED` (browser verification pending). Evidence: all schema statuses rendered and accepted. Commit: `76f334d`, `77f3ffe`
- [ ] `ADM-008` **P0 UX/Data integrity** — Changing a status immediately submits with no review or recovery. Status: `FIXED` (browser verification pending). Evidence: explicit save/confirm flow. Commit: `76f334d`, `77f3ffe`
- [ ] `ADM-009` **P0 Data integrity** — Approval processing is incomplete and non-atomic. Status: `FIXED` (concurrency/integration tests pending). Evidence: each action type applies transactionally or is rejected. Commit: `cde0085`
- [ ] `ADM-010` **P0 Security** — Destructive settings actions are insufficiently protected. Status: `FIXED` (integration tests pending). Evidence: permission, CSRF, and confirmation tests. Commit: `cde0085`

## Phase 2 — Admin workflows and business logic

- [ ] `ADM-011` **P1 Reliability** — Settings tabs depend on a browser-global `event`. Status: `FIXED` (browser keyboard/mouse tests pending). Evidence: mouse and keyboard tab tests. Commit: `cde0085`
- [ ] `ADM-012` **P1 Architecture** — Settings view silently alters the database schema. Status: `FIXED` (GET smoke test pending). Evidence: migration exists and GET is read-only. Commit: `cde0085`
- [ ] `ADM-013` **P1 Security** — Permission editing can lock every administrator out. Status: `FIXED` (last-admin tests pending). Evidence: last-admin permission safeguards. Commit: `cde0085`
- [ ] `ADM-014` **P1 Logic** — Multiple independent settings actions can execute from one POST. Status: `FIXED` (crafted POST test pending). Evidence: single action dispatch tests. Commit: `cde0085`
- [ ] `ADM-015` **P1 UX** — Reservation edit modal can disable the wrong primary button. Status: `FIXED` (browser verification pending). Evidence: edit/save button state test. Commit: `77f3ffe`
- [ ] `ADM-016` **P1 Maintainability** — Booking details rendering is duplicated. Status: `FIXED` (browser regression test pending); calendar summaries now route into the single full-details workflow. Evidence: one implementation and detail-modal regression test. Commit: `7e0bb46`
- [ ] `ADM-017` **P1 Reliability** — Global modal handlers overwrite each other. Status: `FIXED` (browser verification pending). Evidence: each modal closes independently by button/Escape/backdrop. Commit: `77f3ffe`
- [ ] `ADM-018` **P1 Logic** — Annual-booking edits are inconsistent across parent and child rows. Status: `FIXED` (database/browser scenarios pending); occurrence versus full-series scope is explicit, structural occurrence edits are prevented, and series conflicts are prevalidated before one transaction updates every instance. Evidence: parent/child update scenarios. Commit: `c16a573`
- [ ] `ADM-019` **P1 Reliability** — Email is sent inside the booking edit transaction. Status: `FIXED` (failure-path integration test pending). Evidence: DB commit remains correct when mail fails. Commit: `76f334d`
- [ ] `ADM-020` **P1 Privacy** — Deleting a booking leaves physical receipt files behind. Status: `FIXED` (filesystem integration test pending). Evidence: safe file cleanup test. Commit: `d5556a4`
- [ ] `ADM-021` **P1 Auditability** — Booking deletion has no durable audit trail. Status: `FIXED` (migration/database test pending). Evidence: actor, target, time, and before-state recorded. Commit: `d5556a4`
- [ ] `ADM-022` **P1 Logic** — Admin calendar has a hard 2030 year ceiling. Status: `FIXED` (settings-boundary integration test pending). Evidence: settings-driven range boundary tests. Commit: `77f3ffe`
- [ ] `ADM-023` **P1 Edge case** — Annual calendar behavior is undefined for February 29. Status: `FIXED` (database recurrence test pending). Evidence: leap-day recurrence test. Commit: `77f3ffe`
- [ ] `ADM-024` **P1 Logic** — Calendar full/partial availability calculation does not match slot-conflict rules. Status: `FIXED` (browser verification pending); five pure-JavaScript slot cases pass. Evidence: whole-day/morning/lunch matrix. Commit: `d5556a4`
- [ ] `ADM-025` **P1 Data integrity** — Receipt/annual joins can duplicate booking rows. Status: `FIXED` (multi-row database test pending). Evidence: multi-receipt/annual query test. Commit: `77f3ffe`
- [ ] `ADM-026` **P1 Security** — Receipt access is inconsistent and direct upload paths are exposed. Status: `FIXED` (Apache and role integration tests pending). Evidence: authorized streaming only; direct access denied. Commit: `77f3ffe`
- [ ] `ADM-027` **P1 Security** — Reports do not enforce report/export permissions. Status: `FIXED` (role-matrix integration test pending). Evidence: role matrix endpoint tests. Commit: `77f3ffe`
- [ ] `ADM-028` **P1 Correctness** — The PDF report option returns HTML rather than a PDF. Status: `FIXED` (browser verification pending); UI now truthfully offers CSV or print-ready HTML. Evidence: valid PDF signature/content or honest UI label. Commit: `77f3ffe`
- [ ] `ADM-029` **P1 Security** — CSV export is vulnerable to spreadsheet formula injection. Status: `FIXED` (payload download test pending). Evidence: cells beginning `= + - @` neutralized. Commit: `77f3ffe`
- [ ] `ADM-030` **P1 Data integrity** — Pricing updates are not transactional. Status: `FIXED` (rollback integration test pending). Evidence: rollback-on-failure test. Commit: `cde0085`
- [ ] `ADM-031` **P1 Validation** — Pricing inputs are weakly validated server-side. Status: `FIXED` (boundary integration tests pending). Evidence: invalid amount/month/type boundary tests. Commit: `cde0085`
- [ ] `ADM-032` **P1 Logic** — Pricing-window calculation ignores direction/boundary cases. Status: `FIXED` (date-boundary integration tests pending). Evidence: past/current/future month tests. Commit: `cde0085`
- [ ] `ADM-033` **P1 Deployment** — Backup flow lacks shared-hosting preflight and reliable `mysqldump` discovery. Status: `FIXED` (cPanel execution test pending); Settings reports command/storage/Zip readiness, common Linux/XAMPP paths are discovered, credentials remain off the command line, and cron failures return nonzero. Evidence: actionable failure/success diagnostics. Commit: `42995c2`
- [ ] `ADM-034` **P1 UX/Reliability** — Backend errors are frequently invisible or overly generic. Status: `FIXED` (browser failure-path checks pending); core admin fetches now preserve safe server messages in page states, dialogs, or toasts and settings failures are logged with actionable UI copy. Evidence: consistent safe error surfaces and server logs. Commit: `7e952a7`
- [ ] `ADM-035` **P1 Security** — APIs expose raw internal error details. Status: `FIXED` (crafted database-failure tests pending); expected validation uses domain exceptions while database/unexpected errors return fixed public messages. Evidence: production-safe JSON errors. Commit: `7e952a7`

## Phase 3 — Authentication and account recovery

- [ ] `ADM-036` **P1 Security** — Admin login does not regenerate the session ID. Status: `VERIFIED` (CLI session-rotation test passed). Evidence: session ID changes after login. Commit: `8304170`
- [ ] `ADM-037` **P1 Security** — Admin sessions have no inactivity timeout. Status: `VERIFIED` (CLI expiry test passed). Evidence: expiry and activity refresh tests. Commit: `8304170`
- [ ] `ADM-038` **P1 Security** — Role/permission state remains stale in the session after account changes. Status: `FIXED` (database integration test pending). Evidence: disable/role-change takes effect next request. Commit: `8304170`
- [ ] `ADM-039` **P1 Security** — Admin login has no throttling. Status: `FIXED` (persistent database integration test pending; session fallback implemented). Evidence: repeated-failure throttling test. Commit: `8304170`
- [ ] `ADM-040` **P1 Security** — Logout is a state-changing GET request. Status: `FIXED` (browser verification pending). Evidence: CSRF-protected POST logout. Commit: `8304170`
- [ ] `ADM-041` **P1 UX/Security** — Admin password recovery, autocomplete, and loading feedback are missing. Status: `FIXED` (database/email/browser paths pending). Evidence: recovery happy/failure paths and form audit. Commit: `818e4be`, `8304170`

## Phase 4 — Shared admin design system and screen redesign

- [ ] `ADM-042` **P2 UI** — The admin area has no single enforced design system. Status: `FIXED` (cross-browser visual review pending); shared color, spacing, focus, radius, and elevation tokens plus a reusable header are applied across admin pages. Evidence: shared tokens/components across all pages. Commit: `7cc7fac`
- [ ] `ADM-043` **P2 UX** — Navigation is duplicated and inconsistent across pages. Status: `FIXED` (browser navigation test pending); authenticated admin pages now use one permission-aware primary header and the duplicate analytics sidebar is removed. Evidence: one shared shell/navigation. Commit: `7cc7fac`
- [ ] `ADM-044` **P2 Logic/UX** — Pending-approvals badge counts pending bookings rather than approval actions. Status: `FIXED` (database render test pending); the shared badge counts pending `admin_actions` and degrades to no badge if the optional table is unavailable. Evidence: badge query and empty-state test. Commit: `7cc7fac`
- [ ] `ADM-045` **P2 UX** — Role management is misleading and does not clearly support donor-to-agent/staff assignment. Status: `TODO`. Evidence: end-to-end assignment flows. Commit: —
- [ ] `ADM-046` **P2 UI** — Dashboard header is overloaded and poorly prioritized. Status: `FIXED` (responsive visual review pending); compact brand, primary navigation, identity, and icon logout replace the crowded action strip. Evidence: responsive visual review. Commit: `7cc7fac`
- [ ] `ADM-047` **P2 UI** — Sidebar is excessively tall and vertically inefficient. Status: `FIXED` (768px browser review pending); dashboard status filters are a compact responsive summary grid above full-width content. Evidence: 768px-height viewport review. Commit: `7cc7fac`
- [ ] `ADM-048` **P2 Accessibility** — Clickable `div` navigation is not keyboard/semantic accessible. Status: `FIXED` (keyboard browser test pending); dashboard filters are real links with focus styling and current-page semantics. Evidence: keyboard-only navigation. Commit: `7cc7fac`
- [ ] `ADM-049` **P2 UX** — Reservation table is too wide and action-heavy. Status: `TODO`. Evidence: desktop/tablet/mobile layout review. Commit: —
- [ ] `ADM-050` **P2 UX** — Supervisors see duplicated/redundant status information. Status: `TODO`. Evidence: role-specific table review. Commit: —
- [ ] `ADM-051` **P2 UI** — Button hierarchy is unclear and inconsistent. Status: `TODO`. Evidence: primary/secondary/danger component audit. Commit: —
- [ ] `ADM-052` **P2 UX** — Native alert/prompt/confirm dialogs interrupt workflows. Status: `TODO`. Evidence: consistent app dialogs/toasts. Commit: —
- [ ] `ADM-053` **P2 Accessibility** — Modals lack focus management, roles, labels, and Escape behavior. Status: `TODO`. Evidence: keyboard/screen-reader checklist. Commit: —
- [ ] `ADM-054` **P2 UX** — Nested dialog scrolling creates confusing double-scroll layouts. Status: `TODO`. Evidence: short and tall viewport review. Commit: —
- [ ] `ADM-055` **P2 UX** — Settings overloads routine and high-risk actions on one screen. Status: `TODO`. Evidence: reorganized information architecture. Commit: —
- [ ] `ADM-056` **P2 Correctness** — Settings contains invalid nested style markup. Status: `FIXED` (render validation pending). Evidence: HTML validation/source inspection. Commit: `cde0085`
- [ ] `ADM-057` **P2 UX** — The 72-column pricing grid is not practically usable. Status: `TODO`. Evidence: focused period/type workflow and responsive review. Commit: —
- [ ] `ADM-058` **P2 Edge case** — Empty pricing data displays January 1970. Status: `FIXED` (empty-database render pending). Evidence: empty-state test. Commit: `cde0085`
- [ ] `ADM-059` **P2 UX** — Analytics is duplicated between dashboard and standalone page. Status: `TODO`. Evidence: one coherent analytics route/navigation. Commit: —
- [ ] `ADM-060` **P2 Correctness** — Currency formatting uses `$` instead of Rs/LKR in places. Status: `FIXED` (browser chart verification pending). Evidence: cross-page currency audit. Commit: `77f3ffe`
- [ ] `ADM-061` **P2 Maintainability** — `admin.css` contains conflicting repeated definitions. Status: `TODO`. Evidence: cascade audit and visual regression review. Commit: —
- [ ] `ADM-062` **P2 Correctness** — `admin-pro-ui` targets nonexistent classes and only affects part of the dashboard. Status: `FIXED` (visual regression pending); the partial override is no longer loaded and its valid refinements were incorporated into the shared stylesheet. Evidence: selector audit and removal/integration. Commit: `7cc7fac`
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
- 2026-09-15 — Escaped dynamic booking/analytics/report output; corrected analytics schema queries and LKR labels; constrained report options and protected exports with permission/CSRF checks; neutralized CSV formulas; removed the misleading PDF path; derived calendar bounds from settings; handled invalid annual recurrence dates; de-duplicated receipt/annual joins; and routed receipts through an authorized viewer while deploying direct-access denial. PHP lint, JavaScript syntax checks, and `git diff --check` passed. Database integration remains pending because local MySQL refused the connection.
- 2026-09-15 — Added a durable `admin_audit_log` schema/migration, atomic deletion audit snapshots, post-commit receipt-file cleanup, and an actionable missing-migration response. Replaced the calendar count heuristic with slot-conflict evaluation and consolidated day summaries into the full booking-details workflow. PHP lint, JavaScript syntax, diff checks, and five calendar rule cases passed.
- 2026-09-15 — Added explicit occurrence/series controls for annual edits. Structural changes require series scope; every series date and external conflict is validated before all instances update transactionally. PHP and JavaScript syntax checks passed; database scenarios remain pending.
- 2026-09-15 — Added shared-hosting backup preflight, `mysqldump` discovery, protected writable-storage checks, quoted temporary option-file credentials, safe failure classification, ZipArchive checks, Settings readiness output, and correct cron exit codes. PHP lint/diff checks passed; local preflight found `mysqldump.exe`, writable storage, and ZipArchive.
- 2026-09-15 — Completed the admin API error audit: role endpoints now use shared login/permission/CSRF guards; expected domain errors are separated from PDO/unexpected failures; settings no longer renders raw exceptions; and calendar, analytics, booking details, edits, deletion, hold, and user loading surface safe server messages consistently. PHP and JavaScript syntax plus diff checks passed.
- 2026-09-15 — Hardened admin authentication with session-ID rotation, inactivity expiry, live role/active-state refresh, persistent login throttling with a session fallback, and CSRF-protected POST logout. PHP lint passed; isolated CLI tests confirmed session rotation and timeout behavior.
- 2026-09-15 — Added non-enumerating admin password recovery with one-hour hashed, single-use reset tokens, CSRF protection, cooldown, safe mail failures, password bounds, and autocomplete/loading feedback. PHP lint and invalid-token rejection checks passed; database/email/browser integration remains pending.
- 2026-09-15 — Introduced shared admin design tokens and one responsive, permission-aware header across reservations, analytics, pricing, pricing history, settings, approvals, and roles. Corrected the approvals badge query, removed duplicate analytics navigation, stopped loading the partial pro-UI override, and converted dashboard summary navigation into compact semantic links. PHP lint and diff checks passed; browser/database review remains pending.
