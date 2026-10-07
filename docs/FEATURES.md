# MotoTrack Feature Inventory

Statuses reflect current local source, including uncommitted working-tree files. Production status requires separate verification.

## Customer

- **IMPLEMENTED** Account registration, local login, logout, profile, Google Sign-In.
- **IMPLEMENTED** Saved motorcycle add, edit, delete, catalog validation, compatibility-based booking use.
- **IMPLEMENTED LOCALLY / UNCOMMITTED** Add Motorcycle wizard Year and Plate persistence fix plus add-only server validation.
- **IMPLEMENTED** Service booking, appointment edit/cancel within allowed states, service/product selection, booking slot capacity checks, and same-day lead-time validation.
- **IMPLEMENTED** Reservation deposit checkout and server-side PayMongo verification.
- **IMPLEMENTED** Shop, cart, checkout, PayMongo payment, order history, delivery state, and tracking link visibility.
- **IMPLEMENTED** Booking ratings.
- **IMPLEMENTED LOCALLY / UNCOMMITTED** Gmail OAuth password-reset OTP flow and security hardening.

## Staff

- **IMPLEMENTED** Dashboard, bookings, confirmation/cancellation, technician assignment/reassignment, and new booking creation.
- **IMPLEMENTED** Product/category/inventory management, service management, vehicle option catalog management.
- **IMPLEMENTED** POS checkout, inventory consumption, receipt view, and order history.
- **IMPLEMENTED** Online order tracking link management and manual delivered state.
- **IMPLEMENTED LOCALLY / UNCOMMITTED** Meaningful Staff action audit events.

## Technician

- **IMPLEMENTED** Work queue, availability state, job history, ratings view.
- **IMPLEMENTED** Estimated duration validation/storage, start job, estimated finish, completion, parts consumption, and technician notes.
- **IMPLEMENTED LOCALLY / UNCOMMITTED** Technician action audit events.

## Admin

- **IMPLEMENTED** Dashboard, revenue/business metrics, analytics charts, bookings/orders views, ratings, suppliers, purchase orders, users, and settings.
- **IMPLEMENTED** Supplier PO email workflow, Gmail connection management, supplier reply processing support.
- **IMPLEMENTED** Purchase-order creation, approval, status progression, receipt stock update protection, low-stock auto-generation.
- **IMPLEMENTED** Global PMS master switch and service-level reminder configuration in current local source.
- **IMPLEMENTED LOCALLY / UNCOMMITTED** Audit Logs page, filters, details view, and Admin/Staff/Technician/System event coverage.
- **IMPLEMENTED LOCALLY / UNCOMMITTED** Admin PMS email test selector and test-send flow.
- **IMPLEMENTED LOCALLY / UNCOMMITTED** Temporary Clear Transactional Data Settings action.

## System and automation

- **IMPLEMENTED** PayMongo webhook validation and idempotent order fulfillment.
- **IMPLEMENTED** Deposit verification and parts reservation lifecycle.
- **IMPLEMENTED** Customer/staff in-app notifications and notification log deduplication.
- **IMPLEMENTED** Appointment reminder CLI worker with dry-run support.
- **IMPLEMENTED LOCALLY / UNCOMMITTED** Service-specific PMS Gmail worker with per-vehicle/service deduplication.
- **IMPLEMENTED** Supplier reply CLI worker.
- **IMPLEMENTED** API Ninjas motorcycle lookup and Gemini chatbot endpoint.

## Security

- **IMPLEMENTED** PDO prepared statements across core flows, role checks, CSRF helper, session auth contexts, safe local return paths, QA read-only POST enforcement.
- **IMPLEMENTED** PayMongo webhook signature verification and checkout-session ownership checks.
- **IMPLEMENTED LOCALLY / UNCOMMITTED** Audit value filtering for secret-like fields.
- **IMPLEMENTED LOCALLY / UNCOMMITTED** Hardened password-reset OTP rate limits, attempt limits, generic response, Gmail failure cleanup, and atomic consumption.
- **PARTIALLY IMPLEMENTED** Email transport is mixed: Gmail OAuth handles supplier, PMS, and password-reset email; legacy notifications still use PHP `mail()`.

## Pending verification or work

- **PENDING VERIFICATION** Which current local working-tree features are committed, pushed, and uploaded to Hostinger.
- **PENDING VERIFICATION** Hostinger migration history for audit logs, PMS, and password reset.
- **PENDING VERIFICATION** Hostinger cron configuration for all workers.
- **PENDING** Remove temporary transactional reset feature after capstone/demo period, if still required by project policy.
- **PENDING** Replace or intentionally retain legacy PHP-mail notification paths after a scoped email-transport decision.
