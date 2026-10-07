# MotoTrack Handoff Changelog

This changelog uses Git history, migration filenames, and current source. It does not assert production deployment dates without external proof.

## Current local working tree, not yet committed at inspection

- Audit Logs foundation and broad Admin, Staff, Technician, and System audit instrumentation.
- Service-level, motorcycle-specific PMS Gmail reminders and PMS test email tooling.
- Password-reset Gmail OAuth migration and OTP hardening.
- Hostinger portability updates, including environment-driven URL/database handling and public outbound URL helper.
- Booking slot timezone correction using PHP `Asia/Manila` time instead of interpreting database `NOW()` as Manila time.
- Online order delivery tracking UI/service changes.
- My Vehicle Add Motorcycle wizard fix for Year and Plate payload persistence.
- Responsive/UI work in shared CSS and My Vehicle page.

## 2026-10 migrations in current working tree

- `2026_10_01_service_pms_email_reminders.sql`: adds service-level PMS flags/intervals and `pms_reminder_sends` cycle deduplication table.
- `2026_10_01_password_reset_security.sql`: adds reset attempt tracking and indexes.

## 2026-09 migrations and source history

- `2026_09_30_audit_logs.sql`: adds durable audit log table with actor, action, entity, JSON changes, IP, and indexes.
- `2026_09_29_order_delivery_tracking.sql`: adds delivery/tracking support for online orders.
- `2026_09_23_order_checkout_idempotency.sql`: adds checkout attempt idempotency protection.
- `2026_09_14_*`: notification action URLs, PO communication status, supplier Gmail email, and supplier reply processing support.
- `2026_09_02_qa_role.sql`: adds QA role support.
- `2026_09_01_*`: ratings, PMS baseline, parts reservations, and purchase orders.

## 2026-08 migrations

- Booking estimated-duration support.
- Product code support.
- Service tracking/notifications.
- Booking reservation deposits.

## 2026-07 migrations

- Products minimum stock.
- Technician management.
- Staff POS orders.

## 2026-06 migrations

- Google Sign-In and initial password-reset schema.
- Vehicle option availability.
- Admin management schema.
- PayMongo checkout.
- Cart selection/checkout.
- Contact messages.

## Git milestones

- `8a6a376` — `Preserve current authoritative MotoTrack system`; current `main` and `origin/main` at inspection time.
- `ca2150d` — merged teammate integration branch.
- `cebb760` — integrated system updates and deployment preparation branch tip.
- `81a638f` — ratings/PMS/PO/parts reservation/technician matching work, based on repository history and prior integration notes.
- `e9d4e31` and `2c2316e` — QA role and QA hub changes.
- `294bbf4` — PayMongo reservation-deposit payments.
- `6ace2bf` (`v2.1`) — SMS appointment notifications.

## Handoff caution

Current working tree contains substantial uncommitted changes after `8a6a376`. Review each file and migration before creating next authoritative commit. Do not assume this changelog means every listed change is already deployed.
