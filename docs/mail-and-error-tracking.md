# Mail and Error Tracking

How outbound mail and error reporting move from the local development stack
to production, and what is scrubbed out of an error report before it ever
leaves this application.

## Mail — Mailpit Locally, a Real Relay in Production

The platform sends mail through Laravel's own mailer
(`config/mail.php`), configured entirely from the environment — nothing in
`app/` names a provider directly. Locally, `MAIL_MAILER=smtp` points at
Mailpit, the Docker Compose service that intercepts every message without
sending anything to a real inbox. Every message an owner or an administrator
receives can be inspected in Mailpit's own web UI (`http://localhost:8325`
by default) rather than a real mailbox.

### Switching Provider for Production

1. Choose a relay — Postmark, Amazon SES, Resend, or a self-hosted relay are
   all configuration, not code. Deliverability to mixed consumer domains
   across three countries may well require changing provider on evidence,
   which is exactly why the platform stays at plain SMTP instead of coupling
   to one provider's own SDK — it keeps that change cheap.
2. Set the following in the production environment (never commit real
   values; `.env` is gitignored, `.env.example` carries only placeholders):

   | Variable | Purpose |
   | --- | --- |
   | `MAIL_MAILER` | Stays `smtp` for any of the providers above — only the host, port, and credentials change. |
   | `MAIL_HOST` / `MAIL_PORT` | The relay's own SMTP endpoint. |
   | `MAIL_USERNAME` / `MAIL_PASSWORD` | Issued by the relay, scoped to sending only. |
   | `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | The address and display name every outbound message carries. |

3. Redeploy — `config/mail.php` reads every one of these from the
   environment already; nothing else references the provider.

**Administrator-editable templates stay in this project's own notification
model**, never a provider's own templating system — the record of what an
owner or administrator was told is the notification this application keeps,
not the send.

## Error Tracking — Sentry or Self-Hosted GlitchTip

**Decision** (per the integrations specification): Sentry, with self-hosted
GlitchTip as the no-cost alternative. GlitchTip speaks the same ingestion
protocol Sentry's own SDK targets, so switching between them is a DSN change,
never a code change.

- Local: `SENTRY_LARAVEL_DSN` is left empty. The SDK stays loaded (so the
  configuration surface is exercised the same way it will be in production)
  but sends nothing — Sentry's own HTTP transport skips transmission
  outright when no DSN is set.
- Production: set `SENTRY_LARAVEL_DSN` to the project's real DSN (a Sentry
  project, or a self-hosted GlitchTip project's DSN — both accepted as-is by
  the same SDK) and `SENTRY_ENVIRONMENT` to the deployment's own name.

### Queue and Scheduler Failures, Not Only Web Requests

`Sentry\Laravel\Integration::handles($exceptions)` is registered once, in
`bootstrap/app.php`'s `withExceptions()` callback. That single registration
covers three surfaces, not one, because Laravel's own worker and scheduler
both report an exception through the same container-bound exception handler
a web request does:

- **Web requests** — an uncaught exception during a request.
- **Queued jobs** — `Illuminate\Queue\Worker::runJob()` reports any
  exception a job throws once its own retries are exhausted, whether or not
  the job class declares its own `failed()` method.
- **The scheduler** — `Illuminate\Console\Scheduling\ScheduleRunCommand`
  reports an exception the same way, whether it came from a `Schedule::call()`
  closure or a scheduled command process that exited non-zero.

This is why a failed backup, rollup, sweep, or import job is visible without
a separate integration per job — every one of them runs as a queued job or a
scheduled dispatch, both already covered by this single wire-up.

### Personal Data Is Scrubbed Before Transmission

`App\Services\ErrorTracking\ScrubsPersonalDataBeforeSend` is wired as
Sentry's `before_send` hook (`config/sentry.php`) — the last point before an
event leaves this application. An owner's phone number, email address, or
other personal data attached to a failed job's context, request payload, or
exception message is redacted before the event is handed to the transport,
never after. See the class itself for exactly what it catches (key-name
redaction is the reliable mechanism; free-text pattern matching is
best-effort defense in depth on top of it).
