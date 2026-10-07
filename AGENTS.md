# MotoTrack agent guide

MotoTrack is a server-rendered PHP/MySQL motorcycle service, shop, and operations system.

## Working rules

- Repository root: `C:\xampp\htdocs\CAPSTONE-01\CAPSTONE-01`.
- Local XAMPP is the primary development environment. Inspect before changing code.
- Preserve working behavior unless the requested task requires a change.
- Test locally before any production step. Use focused PHP lint, `git diff --check`, and relevant runtime checks.
- Never commit, push, deploy, merge, reset, discard, or change production unless the user explicitly requests it.
- Workflow: localhost development, local test, approved Git commit, approved GitHub push, manual Hostinger upload, required migration, production verification.
- Never print, commit, upload, or unnecessarily modify `.env`, OAuth JSON, Gmail tokens, API keys, PayMongo secrets, database passwords, backups, or dumps.
- Keep real `config/gmail.php` local. Use `.env.production.example` and `config/gmail.php.example` only as safe templates.
- Do not invent architecture. Confirm behavior in current code and schema.
- Preserve dirty worktree changes outside task scope.

## Skills

- Use Impeccable when user requests UI/design work.
- Use Caveman when user requests Caveman mode.

Read [docs/MOTOTRACK_CONTEXT.md](docs/MOTOTRACK_CONTEXT.md) first. Use deployment and integration documents before production-related work.
