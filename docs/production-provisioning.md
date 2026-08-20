# Production Object Storage & CDN

How the portal's media storage moves from the local development stack to a
production provider, and how a CDN sits in front of both the application and
the media bucket. This document covers provisioning only — the backup
destination (a separate bucket, protecting what this document's bucket
holds) is covered in [`backups.md`](backups.md).

## The Interface Does Not Change

The platform is built against Laravel's `s3` filesystem disk
(`config/filesystems.php`) and `spatie/laravel-medialibrary` on top of it.
Every S3-compatible provider — MinIO locally, Cloudflare R2 or Backblaze B2
in production — speaks the same API, so moving providers is a configuration
change, never a code change. Nothing in `app/` names a provider directly.

## Object Storage — Switching Provider

1. Create the production bucket with the provider (R2 or B2), and an access
   key/secret scoped to that bucket only — never an account-wide key.
2. Set the following in the production environment (never commit real
   values; `.env` is gitignored, `.env.example` carries only placeholders):

   | Variable | R2 | B2 (S3-compatible endpoint) |
   | --- | --- | --- |
   | `AWS_ACCESS_KEY_ID` | R2 access key id | B2 application key id |
   | `AWS_SECRET_ACCESS_KEY` | R2 secret access key | B2 application key |
   | `AWS_DEFAULT_REGION` | `auto` | the bucket's region |
   | `AWS_BUCKET` | the bucket name | the bucket name |
   | `AWS_ENDPOINT` | `https://<account_id>.r2.cloudflarestorage.com` | `https://s3.<region>.backblazeb2.com` |
   | `AWS_USE_PATH_STYLE_ENDPOINT` | `false` | `false` |

3. Redeploy — `config/filesystems.php`'s `s3` disk reads every one of these
   from the environment already; nothing else references the provider.

This is exactly the same disk `App\Jobs\ArchiveJournalEntriesJob` and every
`spatie/laravel-medialibrary` collection already write through — no code
change follows from a provider switch, only the environment.

## CDN — Cloudflare in Front of Both

**Decision** (per the integrations specification): Cloudflare in front of
both the application domain and the media bucket.

- **Application**: point the production domain's DNS at Cloudflare
  (proxied/"orange-clouded"), TLS mode "Full (strict)" so Cloudflare
  verifies the origin's own certificate rather than trusting it blindly.
  This is a DNS/dashboard change, not an application one — Laravel already
  serves whatever `APP_URL` names.
- **Media bucket**: bind a custom domain to the production bucket
  (R2's own custom-domain feature, or an equivalent CNAME/proxy in front of
  B2) and point `MEDIA_CDN_URL` at that host, e.g.
  `MEDIA_CDN_URL=https://media.example.com`. The `s3` disk's `url` key
  reads this directly (`config/filesystems.php`), so every media URL —
  `spatie/laravel-medialibrary` resolves a file's URL through its owning
  disk unconditionally — starts resolving through the CDN host without any
  other change. Leaving `MEDIA_CDN_URL` unset falls back to the bucket's own
  origin URL, exactly today's local behaviour against MinIO.

## Cache Posture

Every object uploaded through Media Library already carries a
`Cache-Control: max-age=604800` header (`config/media-library.php`'s
`remote.extra_headers`, one week) — R2 and B2 both honour an object's own
stored headers, so Cloudflare caches media at the edge for that same week
without any further configuration. No page rule is required for the media
host; one is only worth adding if a future provider does not preserve
object-level headers.

## TLS Posture

Cloudflare terminates TLS at the edge for both the application and the
media host. "Full (strict)" mode (not "Flexible") is required for the
application domain — it keeps the origin connection encrypted too, rather
than Cloudflare-to-origin traffic running in plain HTTP.

## Keeping the Backup Disk Separate

`BACKUP_DISK` and `BACKUP_AWS_BUCKET` (see [`backups.md`](backups.md)) are
independent of every variable in this document. Switching the media
provider must never repoint the backup disk at the same bucket — the two
disks already resolve to distinct buckets locally (`booking-media` and
`booking-backups`) and must keep resolving to distinct destinations in
production, whether or not both happen to sit with the same provider.

## Credentials

No key, secret, or token belonging to any provider named here is ever
committed. `.env` is gitignored; `.env.example` and this document carry
placeholders only.
