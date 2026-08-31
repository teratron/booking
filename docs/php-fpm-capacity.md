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

## The pre-launch concurrency benchmark — required by `[TZ]` §18

`composer bench --scenario=load` measures **single-request** cost per public
surface (p50/p95 wall-clock, query count) against seeded volume. It is a
per-commit gate and it does not measure concurrency — a developer
workstation cannot: the built-in PHP server serialises, and the Docker
bind mount on Windows serialises concurrent file I/O so badly that even a
zero-query page plateaus at ~5 req/s. A trustworthy concurrency number
needs the **provisioned production instance** (or an identical staging box),
with the release image and its code on the instance's own disk.

Run this once before the first release, and again whenever the
catalog, territory, or object query-cost fixes land:

1. **Baseline.** With one virtual user, record p50/p95/p99 and worker RSS
   for `/{lang}/catalog`, `/{lang}/{country}/{territory}`, `/{lang}/o/{slug}`,
   and `/api/v1/objects`.
2. **Ramp.** `k6` or `wrk`, concurrency `c = 4 → 8 → 16 → 32 → 64`, ~1 min
   per step, bounded request counts (not `-t`/time-bounded — a
   time-bounded run past capacity produces a 502 storm once the FPM
   `listen.backlog` fills, which is a real failure mode but not the knee
   you are looking for here). Stop when any of: error rate > 1 %, p99 > 2 s
   or > 4× the single-user baseline, or CPU / memory / DB-pool utilisation
   > 90 %.
3. **Record**, per surface: the **throughput knee** (the concurrency where
   req/s stops rising and latency runs away), the **first resource to
   saturate** — worker CPU (`docker stats`), the Postgres connection pool
   (`pg_stat_activity`), a lock (`pg_stat_activity.wait_event_type`), Redis
   (`redis-cli INFO`), or a downstream service — and p99 against the
   portal's documented per-surface response-time and query-count budgets.
4. **Hold** at ~80 % of the lowest knee for 10 minutes; confirm no memory
   creep (worker RSS flat), no rising error rate, and full recovery once
   load stops.
5. **Re-size `pm.max_children` from the result, clamped by CPU as well as
   memory.** The RSS formula above is the memory *upper bound*. These pages
   are CPU-bound, not IO-bound; if the knee arrives well below the
   memory-derived child count, the pool is oversubscribed for the vCPU
   count — set `pm.max_children ≈ 2 × vCPU` and keep the memory figure as
   the cap. The `docker/nginx/default.conf` catalog `limit_req` and
   `docker/app/www.conf`'s explicit `listen.backlog = 1024` bound the blast
   radius of a spike; they do not replace this measurement.

## Verifying the running configuration

```bash
docker compose exec app php-fpm -tt 2>&1 | grep -A4 'pm '
```

Confirms the pool is reading `docker/app/www.conf`, not the vendor default,
and shows the values currently in effect. `listen.backlog` appears in the
same output.
