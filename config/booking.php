<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Panel Addresses
    |--------------------------------------------------------------------------
    |
    | Both panels are reachable at configurable paths. The staff panel does not
    | sit at the conventional `/admin`: the requirement is a "separate, protected
    | address", and a guessable one invites the credential-stuffing traffic the
    | throttle below then has to absorb. Obscurity is not the control — the
    | throttle, the second factor, and the policies are — but it removes the
    | cheapest attack from the table.
    |
    */

    'panels' => [
        'admin' => [
            'path' => env('ADMIN_PANEL_PATH', 'portal-admin'),
        ],
        'cabinet' => [
            'path' => env('CABINET_PANEL_PATH', 'cabinet'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sign-in Throttle
    |--------------------------------------------------------------------------
    |
    | Attempts allowed per IP before the panel refuses further sign-ins for the
    | decay window. Exceeding it is journalled as a lockout — the record is the
    | point, since a lockout with no trace is indistinguishable from a user who
    | simply stopped trying.
    |
    */

    'sign_in' => [
        'max_attempts' => (int) env('SIGN_IN_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('SIGN_IN_DECAY_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    |
    | The second factor is offered to every staff account and required of the
    | roles named here. The chief administrator holds every permission in the
    | system, including the one that edits permissions, so an unprotected
    | chief-administrator credential is a single point of total compromise.
    |
    */

    'two_factor' => [
        'required_for_roles' => ['chief_administrator'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backups
    |--------------------------------------------------------------------------
    |
    | Database and media back up on separate schedules (see routes/console.php)
    | to a destination disk distinct from both the application server and the
    | media bucket they protect (see the `backups` disk in
    | config/filesystems.php). The counts below are the "several generations
    | retained" figures: the database dump's own pruning reads
    | `database_generations_to_keep` through GenerationCountCleanupStrategy,
    | configured as Spatie's own cleanup strategy in config/backup.php; the
    | media mirror reads `media_generations_to_keep` directly.
    |
    */

    'backups' => [
        'database_generations_to_keep' => (int) env('BACKUP_DATABASE_GENERATIONS_TO_KEEP', 7),
        'media_generations_to_keep' => (int) env('BACKUP_MEDIA_GENERATIONS_TO_KEEP', 5),

        /*
         * How old the last successful database backup may be before the
         * administration screen renders it as a staleness warning rather
         * than a neutral date. Deliberately looser than the daily schedule
         * itself (see routes/console.php): one missed run should not read
         * as an emergency the moment the next one is due.
         */
        'staleness_threshold_hours' => (int) env('BACKUP_STALENESS_THRESHOLD_HOURS', 48),
    ],

];
