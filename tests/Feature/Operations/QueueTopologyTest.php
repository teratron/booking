<?php

declare(strict_types=1);

use App\Filament\Admin\Imports\ObjectImporter;
use App\Models\User;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Queue Topology & Dashboard Authorization
|--------------------------------------------------------------------------
|
| Every ShouldQueue job under app/Jobs, plus Filament's own import/export
| machinery, declares a real, named queue rather than falling through to
| the connection's implicit "default" — see config/horizon.php's own five
| supervisors (notifications, analytics, bulk, backups, default), each
| sized for the weight and urgency of the jobs assigned to it. A directory
| walk plus reflection, the same construction TransferableRegistryTest's
| own containment sweep and the public route registry's indexation
| invariant already use, so a job added later without an explicit
| onQueue() call fails this test rather than silently landing on a queue
| no supervisor drains at the right balance/retry posture.
|
| Both the Horizon and Pulse dashboards are gated behind the identical
| staff-panel door every other admin surface in this codebase uses — see
| App\Providers\HorizonServiceProvider::gate() and
| App\Providers\AppServiceProvider::registerPulseAuthorization(), both of
| which call User::canAccessPanel() rather than maintaining a second,
| independent access rule.
|
*/

/**
 * The queue name every supervisor drains — declared once in "defaults"
 * (see config/horizon.php), never in "environments", which only overrides
 * per-environment sizing (maxProcesses, balance shift/cooldown) on top of
 * the same queue name across every environment.
 *
 * @return list<string>
 */
function queueTopologyDeclaredQueues(): array
{
    return collect(config('horizon.defaults'))
        ->pluck('queue')
        ->flatten()
        ->unique()
        ->values()
        ->all();
}

/**
 * Best-effort dummy argument per constructor parameter. Only the
 * constructor runs here (where every job calls onQueue()) — handle() is
 * never invoked — so the concrete values are irrelevant, only their types
 * need to satisfy the parameter's own signature.
 *
 * @return array<int, mixed>
 */
function queueTopologyDummyArgs(ReflectionClass $class): array
{
    $constructor = $class->getConstructor();

    if (! $constructor instanceof ReflectionMethod) {
        return [];
    }

    return array_map(function (ReflectionParameter $parameter): mixed {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        $type = $parameter->getType();
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';

        return match ($typeName) {
            'int' => 1,
            'float' => 1.0,
            'bool' => true,
            'array' => [],
            'string' => 'queue-topology-test',
            default => null,
        };
    }, $constructor->getParameters());
}

/**
 * Discovers every ShouldQueue class under app/Jobs by path, not by a
 * hand-typed list, and reads back the queue its own constructor declared.
 *
 * @return array<class-string, ?string>
 */
function queueTopologyJobQueues(): array
{
    $queues = [];

    foreach (glob(app_path('Jobs/*.php')) ?: [] as $file) {
        $class = 'App\\Jobs\\'.basename($file, '.php');

        if (! class_exists($class) || ! is_subclass_of($class, ShouldQueue::class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        /** @var object{queue?: ?string} $job */
        $job = $reflection->newInstanceArgs(queueTopologyDummyArgs($reflection));

        $queues[$class] = $job->queue ?? null;
    }

    return $queues;
}

function queueTopologyActor(bool $staff): User
{
    Permission::findOrCreate('admin_panel_access', 'web');
    Permission::findOrCreate('cabinet_access', 'web');

    $roleName = $staff ? 'queue_topology_staff' : 'queue_topology_non_staff';
    $role = Role::findOrCreate($roleName, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions([$staff ? 'admin_panel_access' : 'cabinet_access']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

it("declares an explicit queue for every ShouldQueue job under app/Jobs, drawn from Horizon's own configured supervisors", function (): void {
    $declaredQueues = queueTopologyDeclaredQueues();
    $jobQueues = queueTopologyJobQueues();

    // A regression guard on the sweep itself: if this ever finds nothing,
    // every assertion below would vacuously pass without proving anything.
    expect($jobQueues)->toHaveCount(17);

    foreach ($jobQueues as $class => $queue) {
        expect($queue)->not->toBeNull("{$class} does not declare an explicit queue.");
        expect(in_array($queue, $declaredQueues, true))->toBeTrue("{$class}'s queue [{$queue}] has no matching supervisor in config/horizon.php.");
    }
});

it('routes every Filament import/export job to the declared "bulk" queue', function (): void {
    $importer = new ObjectImporter(new Import, [], []);

    expect($importer->getJobQueue())->toBe('bulk');

    $files = [];
    $directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app/Filament')));

    foreach ($directory as $fileInfo) {
        if ($fileInfo->isFile() && str_ends_with($fileInfo->getFilename(), 'Exporter.php')) {
            $files[] = $fileInfo->getPathname();
        }
    }

    $checked = 0;

    foreach ($files as $file) {
        $relative = Str::of($file)->after(base_path('app').DIRECTORY_SEPARATOR)->replace(['/', '\\'], '\\')->beforeLast('.php');
        $fqcn = 'App\\'.$relative;

        expect(class_exists($fqcn))->toBeTrue("Exporter file [{$file}] does not resolve to class [{$fqcn}].");
        expect(is_subclass_of($fqcn, Exporter::class))->toBeTrue("[{$fqcn}] does not extend Filament's Exporter.");

        /** @var class-string<Exporter> $fqcn */
        $exporter = new $fqcn(new Export, [], []);

        expect($exporter->getJobQueue())->toBe('bulk', "[{$fqcn}] does not route to the declared bulk queue.");
        $checked++;
    }

    // Regression guard on the sweep itself — see the job sweep above.
    expect($checked)->toBe(10);
});

it('refuses an unauthenticated request to the Horizon dashboard', function (): void {
    $this->get('/'.config('horizon.path'))->assertForbidden();
});

it('refuses a non-staff request to the Horizon dashboard', function (): void {
    $actor = queueTopologyActor(staff: false);

    $this->actingAs($actor)->get('/'.config('horizon.path'))->assertForbidden();
});

it('permits a staff request to the Horizon dashboard', function (): void {
    $actor = queueTopologyActor(staff: true);

    $this->actingAs($actor)->get('/'.config('horizon.path'))->assertSuccessful();
});

it('refuses an unauthenticated request to the Pulse dashboard', function (): void {
    $this->get('/'.config('pulse.path'))->assertForbidden();
});

it('refuses a non-staff request to the Pulse dashboard', function (): void {
    $actor = queueTopologyActor(staff: false);

    $this->actingAs($actor)->get('/'.config('pulse.path'))->assertForbidden();
});

it('permits a staff request to the Pulse dashboard', function (): void {
    $actor = queueTopologyActor(staff: true);

    $this->actingAs($actor)->get('/'.config('pulse.path'))->assertSuccessful();
});
