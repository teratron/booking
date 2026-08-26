# PHP-FPM Worker Pool Capacity

## What this covers

The `app` service is the only container running `php-fpm` (worker/scheduler/
pulse each override `command:` to a long-running `artisan` process instead).
Every public, admin, cabinet, and API request nginx proxies reaches this one
pool — its `pm.max_children` is the hard ceiling on how many requests the
entire portal can serve at the same instant, regardless of how fast any
individual request is.

## What was found, and how

A k6 load test against a host-side instance of the application (bypassing
this Windows machine's own bind-mount slowdown, which the query-layer
benchmark in `composer bench` already documents and works around
differently) surfaced the built-in PHP development server's own inability to
sustain concurrency. Investigating the production-shaped path instead —
`docker compose exec app php-fpm -tt` — found the pool running php-fpm's
**unmodified vendor default**: `pm.max_children = 5`. Nothing in this
project's Dockerfile, compose file, or any config anywhere overrode it; the
Dockerfile's own prior comment said as much ("php-fpm's own default
www.conf"), just without registering that as a problem.

Five concurrent PHP processes for three countries' worth of public traffic,
plus the admin panel, plus the owner cabinet, plus the API, all sharing one
pool, is not survivable at any real load — the sixth simultaneous visitor
queues behind the other five regardless of how quickly each one is served.

## The fix

`docker/app/www.conf`, copied into the image at `/usr/local/etc/php-fpm.d/
www.conf` for both the `base` (dev) and `production` build targets:

```ini
pm = dynamic
pm.max_children = 32
pm.start_servers = 8
pm.min_spare_servers = 4
pm.max_spare_servers = 16
pm.max_requests = 500
```

`pm.max_requests = 500` is an addition beyond simply raising the ceiling —
it recycles a worker after 500 requests, guarding against a slow memory leak
accumulating across a long-lived process's lifetime.

## Sizing this for a real production instance — do this before launch

The `32` above is a **planning default**, not a measured ceiling for any
specific server. It assumes an 8GB / 4vCPU instance with roughly 2GB
reserved for PHP-FPM once Postgres, Redis, nginx, the OS, and the separate
worker/scheduler/pulse containers (each their own PHP process, never this
pool) take their share, and an estimated ~60MB per warmed-up worker (this
image's extension set: intl, pdo_pgsql, redis, imagick, gd, plus Filament's
own footprint) — `2048MB ÷ 60MB ≈ 32`.

Recompute this against the instance actually provisioned:

1. Deploy with a generous, deliberately-high `pm.max_children` (e.g. 64) and
   `pm.max_requests` unset temporarily.
2. Under representative load, measure actual worker RSS:
   `ps -o rss,cmd --ppid $(pgrep -o -f 'php-fpm: master') | awk '{sum+=$1; n++} END {print sum/n/1024 "MB avg"}'`
3. `pm.max_children = (RAM budgeted for PHP-FPM in MB) ÷ (measured average worker RSS in MB)`,
   leaving headroom for Postgres, Redis, nginx, the OS, and the other three
   containers.
4. Set `pm.start_servers` to roughly a quarter of the result, `pm.min_spare_servers`
   to half of `start_servers`, `pm.max_spare_servers` to double it — php-fpm's
   own documented proportions.
5. Re-run the load test in this same runbook's own method (or a real
   traffic replay) against the tuned value before it is trusted.

## Verifying the running configuration

```bash
docker compose exec app php-fpm -tt 2>&1 | grep -A4 'pm '
```

Confirms the pool is reading `docker/app/www.conf`, not the vendor default,
and shows the values currently in effect.
