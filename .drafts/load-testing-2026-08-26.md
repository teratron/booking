# Load testing — 2026-08-26

Concurrent HTTP load testing, distinct from and complementary to this
project's own `composer bench` (`php artisan bench:run --scenario=load`),
which deliberately measures the retrieval/cache layer directly rather than a
full HTTP round trip — its own docblock explains why: this machine's Windows
bind mount makes framework-bootstrap cost swing from roughly one second to
over a hundred, which would swamp any query-layer signal a full-request
measurement was meant to surface.

**Tooling installed for this pass:** [k6](https://k6.io) (`grafana/k6`, run
via Docker — no host install, no admin rights needed). Scripts and raw
output live in the session scratchpad; the methodology and findings below
are what's committed.

## The headline finding

**The production-shaped path (nginx + php-fpm) shipped with `pm.max_children
= 5`** — php-fpm's own unmodified vendor default, discovered while
investigating why a host-side load test collapsed, confirmed with
`docker compose exec app php-fpm -tt`, and traced to the Dockerfile never
overriding it. Every public, admin, cabinet, and API request nginx proxies
shares this one five-worker pool. **Already fixed and committed** — see
`docker/app/www.conf` and `docs/php-fpm-capacity.md` for the sizing
rationale and the formula to re-tune it for a real production instance.

This is the single most consequential thing this pass found: independent of
every other fix in this sweep, a five-concurrent-request ceiling across the
entire portal would have made the site unusable under any real traffic on
day one.

## Method

1. **Host-side PHP, not the container.** A PHP built-in server run from
   `public/` on the host (opcache on, native filesystem) reaches the same
   dockerized Postgres/Redis/MinIO, but avoids the bind-mount tax entirely —
   the same technique this sweep's earlier benchmarking used, extended here
   to real concurrent HTTP traffic rather than single in-process calls.
2. **k6 in Docker**, targeting the host server via `host.docker.internal`
   (Docker Desktop's host-reachable DNS name) — no k6 install on the host.
3. **Seeded volume**: 52,800 objects, 6,270 territories, 2,500 sample
   contact channels — the same `DemoVolumeSeeder` fixture this project's own
   `bench:run` requires before it will measure anything.

## What was found, in order of discovery

### 1. PHP's built-in development server does not degrade — it hangs

A steady-state run (ramping 0→50 virtual users across home/catalog/
territory/object/map-pins) drove `http_req_duration` to a 59.99s median —
effectively every request timing out — with a 31.9% outright failure rate.
Re-testing at a much lower, fixed concurrency (1, 3, 5, 10 VUs, one endpoint)
found the *same* collapse at every level tested, including a single virtual
user: 100% failure at 30s per request.

The server did not recover on its own. A direct `curl` from the host after
the test found it completely unresponsive (`t=30.002s`, connection never
established) and it stayed that way until manually restarted.

**This is expected, not a defect**: PHP's own documentation states the
built-in server "was designed to aid application development... not
recommended for production." The project's real deployment target is
php-fpm behind nginx (already the shape `docker-compose.yml` uses for the
`app` service), which manages a real worker pool for exactly this reason.
Recorded here because proving it concretely — a hard, unrecoverable hang at
a realistic concurrency level, not a graceful slowdown — is what justified
treating php-fpm's own pool configuration as worth checking at all.

### 2. The real production path had the same problem, for a different reason

Checking php-fpm's actual configuration (rather than assuming the vendor
image ships something reasonable) found `pm.max_children = 5`. **Fixed** —
see the headline finding above.

## What could not be measured on this machine, and why

A genuine concurrent-throughput measurement against the *fixed* php-fpm
pool, through nginx, at the volume this document opens with, was not
completed. After the pool fix and an image rebuild, the container path
still costs 12–17 seconds per request even warm — this machine's
Windows-bind-mount tax on every file read the framework performs (autoload,
view compilation, Filament component discovery), the same cost this
project's own `RunBenchmarks` command's docblock already measured and
designed around ("roughly one second to over a hundred, entirely dependent
on the host's own I/O state"). Running k6 concurrency against that path
would measure bind-mount contention, not the pool fix's own effect, and
would take an impractically long time to reach a steady state.

**What is verified instead**: the corrected pool configuration is actually
running (`pm.max_children = 32`, confirmed via `php-fpm -tt` after the
rebuild), and the stack still serves correct `200` responses end-to-end
through nginx after the change — the fix does what it claims to do. The
*capacity* claim (32 concurrent requests sustained) rests on php-fpm's own
well-documented worker-pool design, not on a fresh concurrency measurement
against this specific slow container.

**Recommended before launch**: re-run this same k6 methodology against a
real (non-Windows-bind-mounted) staging deployment — a cloud VM or a CI
runner building the `production` Dockerfile target, not the `base` dev
target this repository runs locally — where per-request cost should land
in the 100–400ms range this project's own performance budgets already name,
and concurrent throughput becomes directly observable rather than inferred.
`docs/php-fpm-capacity.md` names the exact worker-RSS measurement to take
at that point, to size `pm.max_children` for the real instance rather than
the planning default committed here.

## Per-request cost (measured earlier this sweep, restated for context)

These numbers come from the in-process benchmark harness built earlier in
this sweep (one process per request, host-side, no server round trip) and
are unaffected by the concurrency findings above — they are the
per-request cost a correctly-sized worker pool multiplies by:

| Page | Cold ms | Cold queries | Warm ms | Warm queries |
| --- | --- | --- | --- | --- |
| `/en` (home) | 832 | 49 | 886 | 24 |
| `/en/catalog` | 3,401 | 25 | 2,616 | 2 |
| `/en/o/{slug}` | 945 | 77 | 576 | 17 |
| `/en/md/territory-1` | 1,858 | 159→152¹ | 883 | 8 |

¹ After this sweep's own S-07 fix (territory subtree resolved once per
request instead of once per active object type).

The S-01/S-06 fix (bounding every Filament `Select` over objects/
territories/users) is not in this table — its cost was not "slow", it was
an outright crash past a 512 MB memory limit, which no amount of worker-pool
sizing would have survived either.
