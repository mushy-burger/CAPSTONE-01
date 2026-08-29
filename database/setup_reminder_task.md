# Appointment reminders — Windows Task Scheduler setup

MotoTrack runs on Windows + XAMPP, so reminders are driven by **Windows Task
Scheduler** calling a PHP CLI worker. (Linux `cron` does not apply here.)

```
Windows Task Scheduler
        |
  php database/reminder_worker.php
        |
  confirmed appointments starting within the next 24 hours
        |
  NotificationService  ->  SMS (Semaphore) + Email
```

The worker is **idempotent**: `notification_log` has a unique key on
`(booking_id, notification_type, channel)` and the worker's query skips
bookings that already have a reminder logged. Running it every 15 minutes — or
twice at the same moment — will not send a customer two reminders.

## Register the task

Quoting differs between the two shells — use the matching version.

**Command Prompt (cmd.exe)** — one line:

```
schtasks /Create /TN "MotoTrack Appointment Reminders" /SC MINUTE /MO 15 /TR "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\CAPSTONE-01\CAPSTONE-01\database\reminder_worker.php\"" /RL LIMITED /F
```

**PowerShell** — build the command string first (the inline `\"` form above is
rejected by PowerShell's parser):

```powershell
$tr = '"C:\xampp\php\php.exe" "C:\xampp\htdocs\CAPSTONE-01\CAPSTONE-01\database\reminder_worker.php"'
schtasks /Create /TN "MotoTrack Appointment Reminders" /SC MINUTE /MO 15 /TR $tr /RL LIMITED /F
```

Both forms were verified on this machine: the task registered, ran, and
reported `Last Result: 0`.

- `/SC MINUTE /MO 15` — run every 15 minutes.
- `/RL LIMITED` — no admin rights needed; the worker only reads the database and sends messages.
- `/F` — replace the task if it already exists.

## Verify it

```
schtasks /Query  /TN "MotoTrack Appointment Reminders" /V /FO LIST
schtasks /Run    /TN "MotoTrack Appointment Reminders"
```

`Last Result: 0` means the run succeeded.

## Run it by hand

```
cd C:\xampp\htdocs\CAPSTONE-01\CAPSTONE-01
php database\reminder_worker.php --dry        list what would be sent, send nothing
php database\reminder_worker.php              send the due reminders
php database\reminder_worker.php --hours=48   widen the look-ahead window
```

## Remove it

```
schtasks /Delete /TN "MotoTrack Appointment Reminders" /F
```

## Notes

- While `SEMAPHORE_DRY_RUN=true` in `.env`, reminders are written to
  `storage/logs/sms.log` instead of being sent, so the schedule can be proven
  safely before any credits are spent.
- The task needs XAMPP's MySQL running; if MySQL is down the worker exits
  non-zero and Task Scheduler records the failure, and the pending reminders
  are simply picked up on the next run.
- Reminders are keyed off the appointment's scheduled date/time only. They are
  a separate event from `appointment_confirmed` and `job_completed`, so a
  passing appointment time can never be mistaken for a completion.
