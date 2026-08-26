<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\ActionJournal\Pages\ListActionJournal;
use App\Filament\Admin\Resources\ActionJournal\Tables\ActionJournalTable;
use App\Jobs\ArchiveJournalEntriesJob;
use App\Models\User;
use App\Services\Audit\AuditPresenter;
use App\Services\Settings\SettingsRepository;
use Filament\Facades\Filament;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Console\Scheduling\Schedule as ConsoleSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\Filament\TableHost;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Action Journal
|--------------------------------------------------------------------------
|
| One journal, not two — the same table a moderation decision writes to is
| the table a sign-in, an availability override, and every other privileged
| mutation this panel makes writes to as well. Reading it is its own
| permission, distinct from every resource's own CRUD verbs, because who may
| change a record and who may read the portal-wide history of every change
| are different questions.
|
*/

/** @param  array<string, mixed>  $overrides */
function journalSeedEntry(array $overrides = []): Audit
{
    $actor = User::factory()->create();

    $audit = Audit::create(array_merge([
        'user_type' => User::class,
        'user_id' => $actor->id,
        'event' => 'object_updated',
        'auditable_type' => User::class,
        'auditable_id' => $actor->id,
        'old_values' => ['status' => 'draft'],
        'new_values' => ['status' => 'published'],
        'url' => 'https://portal.test/admin/objects/1',
        'ip_address' => '203.0.113.10',
        'user_agent' => 'Mozilla/5.0 (Test Runner)',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $audit;
}

function journalActor(array $permissions = ['admin_panel_access', 'audit.view', 'audit.export']): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('journal_role_'.implode('_', $permissions), 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Filament::setCurrentPanel('admin');
    test()->actingAs($user->fresh());

    return $user->fresh();
}

it('carries actor, action, target, previous value, new value, timestamp, IP, device, and outcome per entry', function (): void {
    $entry = journalSeedEntry(['event' => 'sign_in_failed']);
    journalActor();

    $loaded = Audit::query()->with('user')->findOrFail($entry->id);

    expect($loaded->user->id)->toBe($entry->user_id)
        ->and($loaded->event)->toBe('sign_in_failed')
        ->and($loaded->auditable_type)->toBe(User::class)
        ->and($loaded->old_values)->toBe(['status' => 'draft'])
        ->and($loaded->new_values)->toBe(['status' => 'published'])
        ->and($loaded->created_at)->not->toBeNull()
        ->and($loaded->ip_address)->toBe('203.0.113.10')
        ->and($loaded->user_agent)->toBe('Mozilla/5.0 (Test Runner)')
        ->and(AuditPresenter::outcome($loaded->event))->toBe('failure');
});

it('searches and filters the journal by actor, action, target, and date, and derives outcome from the event name', function (): void {
    $matching = journalSeedEntry(['event' => 'object_updated', 'created_at' => now()->subDays(2)]);
    $otherEvent = journalSeedEntry(['event' => 'sign_in_failed', 'created_at' => now()->subDays(2)]);
    journalActor();

    $table = ActionJournalTable::configure(Table::make(new TableHost));
    $filters = collect($table->getFilters());

    $actorFilter = $filters->first(fn ($f): bool => $f instanceof SelectFilter && $f->getName() === 'user_id');
    $eventFilter = $filters->first(fn ($f): bool => $f instanceof SelectFilter && $f->getName() === 'event');
    $targetFilter = $filters->first(fn ($f): bool => $f instanceof SelectFilter && $f->getName() === 'auditable_type');
    $outcomeFilter = $filters->first(fn ($f): bool => $f instanceof SelectFilter && $f->getName() === 'outcome');
    $dateFilter = $filters->first(fn ($f): bool => $f instanceof Filter && $f->getName() === 'occurred_between');

    expect($actorFilter->apply(Audit::query(), ['value' => $matching->user_id])->pluck('id')->all())
        ->toBe([$matching->id])
        ->and($eventFilter->apply(Audit::query(), ['value' => 'object_updated'])->pluck('id')->all())
        ->toBe([$matching->id])
        ->and($targetFilter->apply(Audit::query(), ['value' => User::class])->pluck('id')->all())
        ->toEqualCanonicalizing([$matching->id, $otherEvent->id])
        ->and($outcomeFilter->apply(Audit::query(), ['value' => 'failure'])->pluck('id')->all())
        ->toBe([$otherEvent->id])
        ->and($outcomeFilter->apply(Audit::query(), ['value' => 'success'])->pluck('id')->all())
        ->toBe([$matching->id])
        ->and($dateFilter->apply(Audit::query(), ['from' => now()->subDays(5)->toDateString()])->pluck('id')->all())
        ->toEqualCanonicalizing([$matching->id, $otherEvent->id])
        ->and($dateFilter->apply(Audit::query(), ['from' => now()->toDateString()])->pluck('id')->all())
        ->toBe([]);
});

it('reading the journal requires audit.view specifically, distinct from every other panel permission', function (): void {
    journalSeedEntry();

    $withoutAudit = journalActor(['admin_panel_access', 'moderation.view', 'settings.view']);
    expect($withoutAudit->can('viewAny', Audit::class))->toBeFalse();

    $withAudit = journalActor(['admin_panel_access', 'audit.view']);
    expect($withAudit->can('viewAny', Audit::class))->toBeTrue();
});

it('export requires the audit.export permission, distinct from audit.view', function (): void {
    $viewOnly = journalActor(['admin_panel_access', 'audit.view']);
    expect($viewOnly->can('audit.export'))->toBeFalse();

    $withExport = journalActor(['admin_panel_access', 'audit.view', 'audit.export']);
    expect($withExport->can('audit.export'))->toBeTrue();
});

it('archives entries older than the configured retention setting without deleting them, honouring the setting', function (): void {
    Storage::fake('s3');

    $actor = journalActor(['admin_panel_access', 'audit.view', 'audit.export', 'settings_management']);
    $actor->assignRole(Role::findOrCreate('chief_administrator', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app(SettingsRepository::class)->set('journal.retention_days', 30, $actor->fresh());

    $old = journalSeedEntry(['event' => 'old_entry', 'created_at' => now()->subDays(40)]);
    $recent = journalSeedEntry(['event' => 'recent_entry', 'created_at' => now()->subDays(5)]);

    app(ArchiveJournalEntriesJob::class)->handle(app(SettingsRepository::class));

    // Never deleted — the database enforces that, and archival must not
    // pretend otherwise by removing rows some other way.
    expect(Audit::query()->whereKey($old->id)->exists())->toBeTrue()
        ->and(Audit::query()->whereKey($recent->id)->exists())->toBeTrue();

    $files = Storage::disk('s3')->files('journal-archive');
    expect($files)->not->toBeEmpty();

    $archived = json_decode(Storage::disk('s3')->get($files[0]), true);
    $archivedIds = collect($archived)->pluck('id')->all();

    expect($archivedIds)->toContain($old->id)
        ->and($archivedIds)->not->toContain($recent->id);
});

it('renders the journal list page once it holds an entry with old and new values', function (): void {
    // Regression: the page 500'd for every role, including chief_administrator,
    // as soon as the journal held a single row — a fresh install's empty table
    // masked it. formatStateUsing() previously type-hinted ?array but the
    // column state can arrive as the raw JSON string; every prior test in this
    // file builds filter queries directly against Eloquent and never exercises
    // this render path. Searching for the seeded value both isolates this row
    // from whatever else the actor's own creation may itself have audited, and
    // proves the searchable ILIKE query and the value formatter agree with
    // each other on the same row.
    journalSeedEntry(['old_values' => ['status' => 'qa-probe-draft'], 'new_values' => ['status' => 'qa-probe-published']]);
    journalActor();

    Livewire::test(ListActionJournal::class)
        ->assertOk()
        ->searchTable('qa-probe-draft')
        ->assertOk()
        ->assertSee('qa-probe-draft')
        ->assertSee('qa-probe-published');
});

it('registers the archival job on the scheduler', function (): void {
    $events = collect(app(ConsoleSchedule::class)->events());

    expect($events->contains(fn ($event): bool => str_contains($event->description ?? '', 'journal:archive')))->toBeTrue();
});
