<?php

declare(strict_types=1);

use App\Filament\Admin\Exports\ObjectExporter;
use App\Models\User;
use App\Services\Audit\ImpersonationContext;
use App\Services\Owners\ImpersonationService;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Exit Impersonation & JSON Export Download Controllers
|--------------------------------------------------------------------------
|
| Two thin, admin-only controllers that never had an HTTP-level test: the
| "return to admin" route ImpersonationService::exit() is wired behind, and
| the signed-URL download route the JSON export format links to. Both are
| security-relevant surfaces — one ends a support-mode session and restores
| the real actor, the other gates a data artefact by ownership — so both
| are driven here as real requests, not as direct service calls.
|
*/

/** @return User  holding the `object_owner` role, the target ImpersonationService::enter() requires */
function exitControllerOwner(): User
{
    Role::findOrCreate('object_owner', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $owner = User::factory()->create();
    $owner->assignRole('object_owner');

    return $owner->fresh();
}

/** @return User  holding the `impersonate` permission, the actor ImpersonationService::enter() requires */
function exitControllerAdministrator(): User
{
    Permission::findOrCreate('impersonate', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $administrator = User::factory()->create();
    $administrator->givePermissionTo('impersonate');

    return $administrator->fresh();
}

/**
 * A completed export row plus the CSV chunk files JsonDownloader reads
 * directly off a faked disk — the same on-disk shape Filament's queued
 * export pipeline leaves behind, built by hand here so the controller test
 * stays independent of the full export-triggering pipeline already
 * exercised elsewhere (EntityExportTest).
 *
 * @param  list<string>  $header
 * @param  list<list<string>>  $rows
 */
function jsonExportArtifact(User $owner, array $header, array $rows): Export
{
    $disk = config('filament.default_filesystem_disk', 'local');
    Storage::fake($disk);

    $export = Export::create([
        'file_disk' => $disk,
        'file_name' => 'objects-export',
        'exporter' => ObjectExporter::class,
        'total_rows' => count($rows),
        'successful_rows' => count($rows),
        'processed_rows' => count($rows),
        'completed_at' => now(),
        'user_id' => $owner->id,
    ]);

    $directory = $export->getFileDirectory();

    Storage::disk($disk)->put($directory.'/headers.csv', implode(',', $header)."\n");
    Storage::disk($disk)->put(
        $directory.'/data-1.csv',
        implode("\n", array_map(static fn (array $row): string => implode(',', $row), $rows))."\n",
    );

    return $export->fresh();
}

function jsonExportSignedUrl(Export $export): string
{
    return URL::signedRoute('exports.download-json', ['authGuard' => 'web', 'export' => $export], absolute: false);
}

it("streams the export's real JSON content to its owner over a valid signed URL", function (): void {
    $owner = User::factory()->create();
    $header = ['Name', 'Status'];
    $rows = [
        ['Villa One', 'published'],
        ['Villa Two', 'draft'],
    ];

    $export = jsonExportArtifact($owner, $header, $rows);

    $response = $this->actingAs($owner)->get(jsonExportSignedUrl($export));

    $response->assertOk();

    expect(json_decode($response->streamedContent(), true))->toBe([
        array_combine($header, $rows[0]),
        array_combine($header, $rows[1]),
    ]);
});

it('refuses an unauthenticated request for a signed export download with 401', function (): void {
    $owner = User::factory()->create();
    $export = jsonExportArtifact($owner, ['Name'], [['Villa One']]);

    $this->get(jsonExportSignedUrl($export))->assertUnauthorized();
});

it("refuses an authenticated user who does not own the export, and holds no export policy override, with 403", function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $export = jsonExportArtifact($owner, ['Name'], [['Villa One']]);

    $this->actingAs($stranger)->get(jsonExportSignedUrl($export))->assertForbidden();
});

it('ends an active impersonation session over a real request, restoring the administrator and redirecting to the admin panel', function (): void {
    $owner = exitControllerOwner();
    $administrator = exitControllerAdministrator();

    app(ImpersonationService::class)->enter($owner, $administrator);

    expect(Auth::guard('web')->id())->toBe($owner->id)
        ->and(session(ImpersonationContext::SESSION_KEY))->toBe($administrator->id);

    $expectedRedirect = Filament::getPanel('admin')->getUrl();

    $this->get('/support-mode/exit')->assertRedirect($expectedRedirect);

    expect(session(ImpersonationContext::SESSION_KEY))->toBeNull()
        ->and(Auth::guard('web')->id())->toBe($administrator->id);

    $endedAudit = DB::table('audits')
        ->where('event', 'owner_impersonation_ended')
        ->where('auditable_id', $owner->id)
        ->first();

    expect($endedAudit)->not->toBeNull()
        ->and($endedAudit->user_id)->toBe($administrator->id);
});

it('treats a visit with no active impersonation as a no-op, leaving the current administrator authenticated and writing no audit entry', function (): void {
    $administrator = exitControllerAdministrator();

    $expectedRedirect = Filament::getPanel('admin')->getUrl();

    $this->actingAs($administrator)
        ->get('/support-mode/exit')
        ->assertRedirect($expectedRedirect);

    expect(Auth::guard('web')->id())->toBe($administrator->id)
        ->and(session(ImpersonationContext::SESSION_KEY))->toBeNull()
        ->and(DB::table('audits')->where('event', 'owner_impersonation_ended')->count())->toBe(0);
});
