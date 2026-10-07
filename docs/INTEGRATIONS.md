# MotoTrack Integrations

This document lists integrations verified from current source. It lists variable names only.

## Google Sign-In

- Purpose: customer account sign-in/registration using OpenID Connect.
- Files: `google-login.php`, `google-callback.php`, `config/google.php`, `includes/auth.php`.
- Callback route: `google-callback.php`.
- Variables: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`.
- Behavior: callback validates session state, exchanges authorization code, reads Google userinfo, then updates or creates normal customer `users` row.
- Deployment: register exact production callback URL in Google Cloud. Keep client secret only in production `.env`.
- Current status: implemented in source. Production setup must be verified outside repository.

## Gmail OAuth

- Purpose: supplier purchase-order messages, supplier reply processing, PMS reminder email, and password-reset OTP email.
- Files: `includes/GmailService.php`, `config/gmail.php.example`, `admin/gmail-connect.php`, `admin/gmail-callback.php`, `admin/gmail-disconnect.php`, `includes/PurchaseOrderService.php`, `includes/PmsReminderService.php`, `includes/PasswordResetService.php`.
- Callback route: `admin/gmail-callback.php`.
- Variables: `GMAIL_OAUTH_CLIENT_SECRET_PATH`, `GMAIL_OAUTH_TOKEN_PATH`, `GMAIL_OAUTH_REDIRECT_URI`, `GMAIL_SENDER_EMAIL`, `GMAIL_SENDER_NAME`, `GMAIL_MESSAGE_ID_DOMAIN`.
- Scopes: Gmail readonly and send.
- Deployment: OAuth client JSON and token file stay outside `public_html`; PHP process needs read/write access to token path. Configure authorized redirect URI in Google Cloud.
- Current status: implementation exists. Connection/token state is environment-specific and cannot be inferred from Git.

## PayMongo

- Purpose: online shop checkout and booking reservation deposits.
- Files: `config/paymongo.php`, `includes/paymongo.php`, `checkout.php`, `booking-deposit.php`, `includes/BookingDeposit.php`, `paymongo-webhook.php`.
- Webhook route: `paymongo-webhook.php`.
- Variables: `PAYMONGO_SECRET_KEY`, `PAYMONGO_PUBLIC_KEY`, `PAYMONGO_WEBHOOK_SECRET`, `PAYMONGO_WEBHOOK_URL`.
- Behavior: webhook validates signature; checkout-session matching and idempotent fulfillment protect order/deposit transitions. Booking deposits are separately verified against PayMongo.
- Deployment: configure production callback/webhook URL in PayMongo. Never store keys in source.
- Current status: implemented in source. Test/live mode depends on configured keys.

## API Ninjas Motorcycle API

- Purpose: motorcycle specification lookup for catalog tooling.
- Files: `includes/MotorcycleApiService.php`, `api/admin/motorcycle/search.php`.
- Variables: `MOTORCYCLE_API_ENDPOINT`, `MOTORCYCLE_API_KEY`.
- Behavior: source identifies returned lookup data as API Ninjas Motorcycles API; service includes cache/fallback behavior.
- Deployment: set endpoint/key in environment. Do not expose key to browser.
- Current status: implemented in source; provider availability/key validity requires environment test.

## Gemini

- Purpose: MotoTrack chatbot/API response generation.
- Files: `config/gemini.php`, `includes/gemini.php`, `includes/ChatbotService.php`, `api/chatbot.php`, `assets/js/chatbot.js`.
- Variables: `GEMINI_API_KEY`, `GEMINI_MODEL`.
- Deployment: keep key server-side. Verify model availability and quota in target environment.
- Current status: implemented in source; production credentials are not repository state.

## SMS

- Purpose: appointment and job notification channels through `NotificationService`.
- Files: `includes/SmsProvider.php`, `includes/NotificationService.php`, `database/reminder_worker.php`.
- Supported providers: Mocean and Semaphore.
- Variables: `SMS_PROVIDER`, `SMS_SENDER_NAME`, `SMS_DRY_RUN`, `SEMAPHORE_API_KEY`, `SEMAPHORE_SENDER_NAME`, `SEMAPHORE_DRY_RUN`, `MOCEAN_API_TOKEN`, `MOCEAN_SENDER_NAME`.
- Behavior: `SMS_DRY_RUN` records/logs dry-run processing instead of sending a real SMS. Valid Philippine phone numbers are normalized server-side.
- Deployment: leave dry-run enabled until provider account, sender approval, credits, and consent are verified.
- Current status: provider abstraction implemented. Real sending depends entirely on environment configuration.

## Browser/CDN dependencies

- Font Awesome: CDN stylesheet in shared page shells.
- Chart.js: CDN script in Admin sidebar shell for dashboard/analytics charts.
- jsQR: local vendor file for scanner-related UI.

Availability of CDN assets should be considered during production testing.

## Legacy PHP mail helpers

- Files: `includes/mail.php`, `includes/NotificationService.php`, `paymongo-webhook.php`.
- Variables: `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.
- Purpose: legacy notification/order email calls using PHP `mail()`.
- Important: this is separate from Gmail OAuth. Do not claim all MotoTrack email uses Gmail without checking the caller.
