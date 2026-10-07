# MotoTrack Project Context

## Purpose

MotoTrack is a motorcycle shop management system. It combines customer booking, reservation deposits, product sales, inventory, technician work, purchase orders, analytics, notifications, and administration.

## Verified local environment

- Repository root: `C:\xampp\htdocs\CAPSTONE-01\CAPSTONE-01`.
- Local stack: XAMPP, PHP 8.2.12, MariaDB/MySQL through PDO, vanilla PHP/HTML/CSS/JavaScript.
- Local database: `mototrack`.
- Current local schema contains 37 tables.
- Configuration loads environment values through `includes/functions.php` and `.env`; database connection is in `includes/db.php`.
- Application URLs use `APP_URL`; customer outbound links may use `PUBLIC_APP_URL`, falling back to `APP_URL`.

## Git state at handoff

- Branch: `main`.
- HEAD: `8a6a376e35abf9fc83b40dd0c2c4f3f0ac3f82af` (`Preserve current authoritative MotoTrack system`).
- `origin/main` and safety branch `safety/local-authoritative-20260930` point to that commit at inspection time.
- Working tree is intentionally dirty. It contains later audit-log, PMS, password-reset, delivery, UI, and My Vehicle work. Do not assume these changes are committed or deployed.
- Untracked SQL/catalog files and local deployment artifacts require review before staging.

## Application structure

- Root PHP pages: customer authentication, shop, cart, checkout, booking, deposits, vehicles, profile, payments, and ratings.
- `admin/`: dashboard, users, orders, bookings, analytics, ratings, purchase orders, suppliers, settings, Gmail connection, audit logs.
- `staff/`: dashboard, bookings, orders, POS, products, services, vehicle options, and new bookings.
- `tech/`: work queue, job detail, history, and ratings.
- `api/`: booking slots, product/model lookups, notification feed, chatbot, customer search, and staff booking catalog.
- `includes/`: shared domain services, authentication, configuration, UI partials, metrics, payment, notifications, inventory, and delivery logic.
- `database/migrations/`: manual SQL migrations. There is no verified automatic migration runner.
- `database/*_worker.php`: CLI workers for appointment reminders, PMS email reminders, and supplier reply processing.
- `uploads/`: runtime media. `storage/logs/` is runtime-only.

## Roles and access

- Customer: account, saved motorcycles, booking, appointment history, shop, cart, checkout, order delivery visibility, ratings.
- Staff: bookings, technician assignment, inventory/products, services, vehicle catalog, POS, and order delivery tracking.
- Technician: availability, assigned work queue, estimates, job start/completion, notes, and ratings.
- Admin: users, operational dashboards, analytics, suppliers, purchase orders, settings, Gmail integration, audit logs, and read-oriented booking/order views.
- QA: existing authorization treats QA as eligible for several Admin, Staff, and Technician views. `enforceQAReadOnlyRequest()` blocks QA non-GET mutations. Do not change this architecture without a scoped request.

## Core modules and decisions

### Authentication

- Local email/password login uses `password_hash()` and `password_verify()`.
- Google Sign-In uses Google OpenID Connect. Google accounts are represented in the normal `users` table with `google_id` and `auth_provider`.
- Multi-tab authentication contexts use a `ctx` value in session-aware URLs.
- CSRF helpers are in `includes/auth.php`.

### Motorcycle management

- Customer motorcycles live in `customer_vehicles`, linked to normalized motorcycle type, brand, and model tables.
- My Vehicle validates selected catalog values server-side and uses prepared statements.
- Current local working tree includes a fix for Add Motorcycle wizard Year and Plate persistence: hidden form-owned payloads preserve Step 4 values while inactive wizard steps are moved into a `DocumentFragment`.
- This My Vehicle fix is local/uncommitted at inspection time; production deployment cannot be inferred from repository state.

### Booking and reservation deposits

- Customers choose vehicle, services, products, date, and time in `book-service.php`.
- `includes/BookingSlots.php` provides capacity and same-day lead-time logic. It uses PHP `DateTimeImmutable` in `Asia/Manila` so availability does not depend on database server timezone.
- Slot capacity remains three bookings per slot.
- `includes/BookingDeposit.php` creates and verifies PayMongo reservation-deposit sessions. A booking deposit becomes paid only after server-side PayMongo verification.
- Parts reservations use `parts_reservations`; held parts are released or consumed through `PartsReservationService`.

### Technician workflow

- Technician assignment uses service qualifications in `technician_services` and `TechnicianService`.
- Job Detail uses `JobService`: validate estimate, store estimated duration, start a confirmed job, calculate estimated finish, complete job, and record actual timing.
- Completion consumes held parts and updates booking state. Customer vehicle `last_service_date` is also retained for existing behavior.

### Inventory, product, and services

- Products, categories, product codes, stock, minimum stock, reorder quantities, product images, and service compatibility are managed through shared Staff/Admin paths.
- `PartsReservationService` distinguishes held availability from physical product stock.
- `ProductCodes` supports product barcode/code workflows.
- Services use `service_types`, `service_products`, `service_material_rules`, and compatibility fields.

### Shop, POS, and delivery

- Customer cart uses `cart_items`; online orders use `orders` and `order_items`.
- PayMongo checkout uses a unique checkout attempt key to prevent duplicate orders.
- Staff POS uses `PosService::createPosSale()` and updates inventory inside its transaction.
- `OrderDeliveryService` derives delivery state from tracking URL and delivered timestamp: pending, delivering, delivered.
- Tracking URLs are server-validated as HTTP/HTTPS. Customer tracking links open in a new tab.

### Suppliers and purchase orders

- Purchase order lifecycle is draft, approved, ordered, received, or cancelled.
- `PurchaseOrderService` finds low-stock products, creates draft POs, sends supplier email, receives stock, and processes supplier replies.
- `database/supplier_reply_worker.php` polls Gmail through the existing Gmail OAuth integration.

### Metrics and analytics

- `BusinessMetrics.php` is shared revenue/analytics logic for admin dashboard and analytics pages.
- Revenue combines paid online/POS orders, completed booking revenue, and verified deposits without double-counting deposits when booking revenue is later recognized.

### Notifications and SMS

- In-app notifications use `notifications`; delivery attempts use `notification_log`.
- `NotificationService` handles appointment confirmation, completion, reminder, and rating notifications.
- `SmsProvider` supports Mocean and Semaphore. `SMS_DRY_RUN` prevents real SMS transmission.
- Legacy notification email helpers in `includes/mail.php` still use PHP `mail()`. Supplier, PMS, and password-reset email use `GmailService`.

### PMS email reminders

- Current local implementation is service-specific and motorcycle-specific, not one global interval.
- Each enabled service has `pms_reminder_enabled` and `pms_reminder_interval_days`.
- PMS due cycles derive from completed `booking_services` for a vehicle and service pair.
- `pms_reminder_sends` provides unique cycle claims and duplicate-send prevention.
- `database/pms_reminder_worker.php` is CLI-only. It respects global `site_settings.pms_reminder_enabled` and sends through Gmail OAuth.
- Admin Settings contains a controlled PMS test-email flow. It does not mark a real cycle as sent.

### Audit logs

- `audit_logs` captures actor, role, module, action, entity, safe old/new values, IP, and timestamp.
- `AuditLogService` intentionally filters secret-like fields and accepts only explicitly chosen values.
- Current local working tree instruments meaningful Admin, Staff, Technician, and selected System actions.
- Audit history is excluded from transactional reset.

### Forgot Password

- Current local implementation uses `PasswordResetService` and Gmail OAuth.
- OTPs use `random_int`, bcrypt hashing, ten-minute expiry, five verification attempts, a 60-second resend cooldown, and five requests per hour per user.
- Public request response remains generic. Failed Gmail delivery removes newly created reset record. Successful password change consumes/reset-invalidates pending codes.
- Migration `2026_10_01_password_reset_security.sql` adds attempt tracking and indexes.

### Transactional Data Reset

- Admin Settings has a temporary/demo-oriented reset action backed by `TransactionalDataReset.php`.
- It uses an explicit child-to-parent table list, transaction, stock snapshot comparison, and safe sequence reset attempts.
- It clears operational/test records but preserves accounts, catalog, suppliers, settings, vehicles, physical stock, and audit logs.

## Database map

Key tables include:

- Identity/configuration: `users`, `site_settings`.
- Vehicle/catalog: `customer_vehicles`, `motorcycle_types`, `motorcycle_brands`, `motorcycle_models`, `categories`, `products`, `product_codes`, `service_types`, `service_products`, `service_material_rules`, `technician_services`, `suppliers`.
- Booking: `bookings`, `booking_services`, `booking_products`, `booking_deposits`, `parts_reservations`, `booking_ratings`.
- Commerce: `cart_items`, `orders`, `order_items`.
- Purchase operations: `purchase_orders`, `purchase_order_items`, `purchase_order_emails`, `purchase_order_replies`.
- Notifications/audit/PMS: `notifications`, `notification_log`, `audit_logs`, `pms_reminder_sends`, `password_resets`.
- Site content: `blogs`, `testimonials`, `contact_messages`.

## Current status

### Implemented in source

- Customer, Staff, Technician, Admin, and QA panels.
- Booking, deposits, parts reservation, online shop, POS, delivery tracking, supplier PO workflow, analytics, notifications, Google Sign-In, PayMongo, Gmail OAuth, API Ninjas lookup, Gemini chatbot, SMS provider abstraction.
- Audit logs, PMS email reminders, hardened password reset, and My Vehicle wizard persistence fixes exist in current local working tree.

### Pending or requires explicit verification

- Commit/stage review for all current local changes.
- Production upload state for uncommitted files, especially audit/PMS/password-reset/My Vehicle work.
- Production migration history cannot be proven from this checkout. Confirm each migration in Hostinger phpMyAdmin before assuming it ran.
- Production cron configuration must be checked externally; repository contains workers but cannot prove scheduled execution.
- `README.md` contains only the project title. These handoff documents are primary repository context.

## Safety notes

- Never use local database dumps or deployment archives as a source of secrets.
- Never infer production configuration from local `.env`.
- Check migration dependencies, dirty worktree, and current database schema before data-changing work.
