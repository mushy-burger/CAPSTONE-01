# MotoTrack Deployment Guide

## Current model

MotoTrack is developed and tested locally, versioned through GitHub, then uploaded manually to Hostinger. Production is expected to run directly from Hostinger `public_html`.

The tracked source does not contain a verified production domain. Set production `APP_URL` and `PUBLIC_APP_URL` in Hostinger `.env`; do not hardcode a domain in PHP.

An older staging directory exists at `C:\xampp\htdocs\CAPSTONE-01\deployment\mototrack-hostinger` and a ZIP exists beside it. Treat both as historical artifacts. Rebuild or inspect them before every production release.

## Release workflow

1. Develop on localhost from repository root.
2. Run targeted local tests, PHP lint, and `git diff --check`.
3. Inspect `git status`, staged diff, and untracked files.
4. Commit only approved source, safe templates, and required migrations.
5. Push only when explicitly requested.
6. Identify changed runtime files with `git diff --name-only <previous-production-commit>..HEAD` plus approved working-tree files.
7. Upload only those runtime files to matching locations under Hostinger `public_html`.
8. Run required SQL migrations once against the Hostinger MotoTrack database.
9. Verify affected production pages, permissions, integrations, logs, and error paths.
10. Keep rollback copy of replaced production files and a database backup before risky migrations/data work.

## Hostinger layout

`public_html` must contain application contents directly:

```text
public_html/
  index.php
  .htaccess
  admin/
  api/
  assets/
  config/
  includes/
  staff/
  tech/
  uploads/
  storage/
```

Do not upload a parent project folder around these contents.

## Environment and secrets

- Create production `.env` directly on Hostinger. Never upload local `.env`.
- Start from `.env.production.example`; replace placeholders only on Hostinger.
- Confirm `APP_URL`, `PUBLIC_APP_URL`, DB variables, PayMongo variables, Google Sign-In variables, Gmail OAuth paths, SMS variables, Motorcycle API variables, and Gemini variables are correct for production.
- Keep Gmail OAuth client JSON and refresh-token JSON outside `public_html` at paths named by `GMAIL_OAUTH_CLIENT_SECRET_PATH` and `GMAIL_OAUTH_TOKEN_PATH`.
- Keep `config/gmail.php` machine-local or production-local. It is ignored by Git and must not contain repository secrets.
- `.htaccess` blocks direct `.env`, `.production`, SQL, backup, and index listing access. This is defense in depth, not a replacement for safe uploads.

## Files that must not be uploaded

- Local `.env`, `.env.production`, `config/gmail.php`, OAuth client/token JSON, keys, passwords, and provider secrets.
- `backups/`, database dumps, `database/backups/`, temporary SQL exports, reset/cleanup utilities unless intentionally required at runtime.
- `.git/`, `.impeccable/`, tests, QA artifacts, local logs, probe scripts, Windows scheduler documentation, and deployment archives.
- Any file not reviewed as production runtime content.

## Migrations

1. Identify migrations from changed code or new files under `database/migrations/`.
2. Back up production database before schema or data migration.
3. Inspect current production schema in phpMyAdmin to confirm migration has not already been applied.
4. Run SQL once, in migration order, against MotoTrack production database.
5. Verify expected tables, columns, and indexes.
6. Upload application files that depend on those schema changes.
7. Exercise affected feature with a safe production account.

Current local working tree contains untracked migrations for audit logs, service-level PMS reminders, and password-reset hardening. They are not evidence that Git or Hostinger contains them. Confirm source, deployment, and production schema separately.

## Workers and cron

Workers are CLI-only and should run with Hostinger PHP CLI using absolute paths outside web requests:

```text
php /home/<account>/public_html/database/pms_reminder_worker.php
php /home/<account>/public_html/database/reminder_worker.php
php /home/<account>/public_html/database/supplier_reply_worker.php
```

Confirm Hostinger's actual PHP binary path before configuring cron.

- PMS worker: daily, preferably off-peak.
- Appointment reminder worker: every 15 minutes.
- Supplier reply worker: choose an operational interval, such as every 15 to 30 minutes.

Run each with its supported `--dry` mode first where available. Verify Gmail connection and worker output without exposing credentials.

## Production verification

After each release, verify relevant paths only:

- Login, role routing, customer session/context behavior.
- Affected customer, Staff, Technician, and Admin pages.
- Schema-dependent feature reads/writes.
- PayMongo callback URL and webhook signature handling after payment changes.
- Google Sign-In callback after Google changes.
- Gmail connection/send path after Gmail changes.
- File upload/media rendering and `storage/logs/` writability where applicable.
- PHP error log and application behavior; never expose errors to end users.

## Rollback principles

- Do not overwrite production blindly.
- Preserve copies of replaced files.
- Take database backup before destructive or irreversible work.
- Roll back code first when schema change is additive and compatible.
- Use migration rollback SQL only when migration explicitly provides safe rollback and data impact is understood.
- Stop when production state differs from expected. Inspect before further changes.
