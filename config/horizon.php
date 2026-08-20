<?php

declare(strict_types=1);

use App\Jobs\CaptureStatEventJob;
use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    |
    | This name appears in notifications and in the Horizon UI. Unique names
    | can be useful while running multiple instances of Horizon within an
    | application, allowing you to identify the Horizon you're viewing.
    |
    */

    'name' => env('HORIZON_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    | Notifications are the one queue an operator needs to know about within
    | seconds, not minutes — the other four tolerate the same generous
    | default the package ships with.
    |
    */

    'waits' => [
        'redis:notifications' => 30,
        'redis:analytics' => 60,
        'redis:bulk' => 120,
        'redis:backups' => 300,
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // CaptureStatEventJob fires on nearly every public page interaction
        // (a page view, a phone-number click) — silencing keeps the
        // completed-jobs list useful for the other, much lower-volume queues
        // instead of drowning in one high-frequency job's own entries.
        CaptureStatEventJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Five supervisors, one per declared queue (see every ShouldQueue job
    | under app/Jobs, each of which calls onQueue() explicitly rather than
    | falling through to Laravel's implicit "default" queue) — never one
    | shared supervisor draining an implicit queue name. Isolating queues
    | this way means a backlog on one never delays another: a slow database
    | restore must never sit behind a backlog of stat-event captures, and a
    | flood of stat events must never delay a time-sensitive notification.
    |
    | - notifications : DispatchNotificationJob, DispatchRetryJob — a
    |                    delivery a recipient is actively waiting on, so it
    |                    gets the shortest wait threshold and the most
    |                    processes relative to its own (light) per-job cost.
    | - analytics      : CaptureStatEventJob, AnalyticsRollupJob,
    |                    AnalyticsCompactionJob — highest volume by job
    |                    count (every public page interaction), lightweight
    |                    per job, tolerant of a short queueing delay.
    | - bulk            : ExecuteObjectBulkActionJob plus every Filament
    |                    import/export job (see getJobQueue() on
    |                    ReadsTransferableRegistry and ObjectImporter) —
    |                    long-running, spreadsheet-sized work with its own
    |                    generous timeout, deliberately capped at a low
    |                    process count so a handful of large imports cannot
    |                    starve Redis connections the other queues need.
    | - backups         : DatabaseBackupJob, MediaBackupJob,
    |                    DatabaseRestoreJob — the heaviest, slowest, and
    |                    most destructive jobs in the codebase; a single
    |                    process, generous timeout, and a single retry
    |                    (retrying a half-finished restore is unsafe) keep
    |                    this queue from ever running two of these at once.
    | - default         : every scheduled lifecycle sweep (expiry, staleness,
    |                    availability confirmation, promotion/news archival,
    |                    journal archival, sitemap regeneration) — daily or
    |                    hourly cadence, no urgency, ordinary retry posture.
    |
    */

    'defaults' => [
        'supervisor-notifications' => [
            'connection' => 'redis',
            'queue' => ['notifications'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 5,
            'timeout' => 60,
            'nice' => 0,
        ],

        'supervisor-analytics' => [
            'connection' => 'redis',
            'queue' => ['analytics'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'size',
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],

        'supervisor-bulk' => [
            'connection' => 'redis',
            'queue' => ['bulk'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 600,
            'nice' => 0,
        ],

        'supervisor-backups' => [
            'connection' => 'redis',
            'queue' => ['backups'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            // A retried backup or restore could interleave with a fresh
            // attempt against the same destination or the same database —
            // a failure here is surfaced (see ErrorTrackingTest, the backup
            // failure notification) rather than silently retried.
            'tries' => 1,
            'timeout' => 3600,
            'nice' => 0,
        ],

        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 90,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-notifications' => [
                'maxProcesses' => 5,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 1,
            ],
            'supervisor-analytics' => [
                'maxProcesses' => 5,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-bulk' => [
                'maxProcesses' => 3,
            ],
            'supervisor-backups' => [
                'maxProcesses' => 1,
            ],
            'supervisor-default' => [
                'maxProcesses' => 3,
            ],
        ],

        'local' => [
            'supervisor-notifications' => [
                'maxProcesses' => 1,
            ],
            'supervisor-analytics' => [
                'maxProcesses' => 1,
            ],
            'supervisor-bulk' => [
                'maxProcesses' => 1,
            ],
            'supervisor-backups' => [
                'maxProcesses' => 1,
            ],
            'supervisor-default' => [
                'maxProcesses' => 1,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watcher Configuration
    |--------------------------------------------------------------------------
    |
    | The following list of directories and files will be watched when using
    | the `horizon:listen` command. Whenever any directories or files are
    | changed, Horizon will automatically restart to apply all changes.
    |
    */

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
