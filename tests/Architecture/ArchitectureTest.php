<?php

declare(strict_types=1);

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Architecture Tests
|--------------------------------------------------------------------------
|
| Mechanical enforcement of the engineering conventions CLAUDE.md states in
| prose. A convention a machine cannot check is a convention that erodes —
| these tests exist so review discipline is not the only thing holding the
| line.
|
*/

arch('every file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging leftovers reach the application code')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'print_r'])
    ->not->toBeUsed();

arch('models are thin — no dependency on the orchestration layers')
    ->expect('App\Models')
    ->not->toUse([
        'App\Services',
        'App\Http',
        'App\Jobs',
        'App\Filament',
        'App\Livewire',
    ]);

arch('Filament resources reach the database only through services')
    ->expect('App\Filament')
    ->not->toUse(DB::class);

arch('Livewire components reach the database only through services')
    ->expect('App\Livewire')
    ->not->toUse(DB::class);

arch('controllers, jobs, and services are final by default')
    ->expect(['App\Http\Controllers', 'App\Jobs', 'App\Services'])
    // Interfaces (e.g. a channel adapter contract) are exempt by
    // construction — PHP forbids `final` on an interface, and the rule's
    // own intent ("final unless deliberately extended") is about
    // concrete implementations, not the contracts they implement.
    ->classes()
    ->toBeFinal()
    // The base controller is Laravel's own template for extension — every
    // concrete controller extends it. That is the "deliberately extended"
    // exception CLAUDE.md's rule anticipates, not a violation of it.
    ->ignoring(Controller::class);
