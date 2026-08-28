<?php

declare(strict_types=1);

use App\Exceptions\DatabaseRestoreFailedException;
use App\Exceptions\LanguageAdministrationRefusedException;
use App\Exceptions\ModerationDecisionRefusedException;
use App\Exceptions\ModuleDependencyException;
use App\Exceptions\ObjectMergeRefusedException;

/*
|--------------------------------------------------------------------------
| Domain Exception Value Objects
|--------------------------------------------------------------------------
|
| These exceptions are named static constructors over a message template —
| the behaviour worth proving is that each one interpolates its arguments
| into the exact wording callers, journals, and Filament notifications
| depend on. Coverage already reaches most of them indirectly through the
| services that throw them, but those tests assert only the exception
| *class*, never the message text — so a wording regression there would
| pass silently. Each test below exists to catch that regression.
|
| BackupIntegrityFailedException is deliberately absent: both of its named
| constructors already carry exact-message assertions in
| tests/Unit/Jobs/BackupJobsTest.php and
| tests/Unit/Services/Backup/BackupJobServicesTest.php, so duplicating them
| here would only pad this file.
|
*/

it('formats DatabaseRestoreFailedException::processFailed without a trailing colon when the process produced no error output', function (): void {
    $exception = DatabaseRestoreFailedException::processFailed('schema reset', '   ');

    expect($exception->getMessage())->toBe('Restore step [schema reset] failed.');
});

it('formats LanguageAdministrationRefusedException for every refusal reason', function (): void {
    expect(LanguageAdministrationRefusedException::cannotDeactivatePrimary(7)->getMessage())
        ->toBe('Language [7] is the primary language and cannot be deactivated — set a different primary language first.');

    expect(LanguageAdministrationRefusedException::cannotDeactivateLastActive(3)->getMessage())
        ->toBe('Language [3] is the last active language — the portal must always have at least one active language.');

    expect(LanguageAdministrationRefusedException::mustBeActiveToBecomePrimary(9)->getMessage())
        ->toBe('Language [9] must be activated before it can become the primary language.');
});

it('formats ModerationDecisionRefusedException for every refusal reason', function (): void {
    expect(ModerationDecisionRefusedException::alreadyDecided(42, 'approved')->getMessage())
        ->toBe('Moderation request [42] was already decided (approved) and cannot be decided again.');

    expect(ModerationDecisionRefusedException::partialAcceptanceDisabled(15)->getMessage())
        ->toBe("Partial acceptance is disabled by the portal's moderation settings — request [15] must be approved or rejected as a whole.");

    expect(ModerationDecisionRefusedException::targetMissing(21)->getMessage())
        ->toBe("Moderation request [21]'s target no longer exists — it cannot be applied.");
});

it('formats ModuleDependencyException for both an unmet dependency and a conflicting module', function (): void {
    expect(ModuleDependencyException::unmet('booking', 'payments')->getMessage())
        ->toBe('Module [booking] cannot be enabled while [payments] is disabled at this scope.');

    expect(ModuleDependencyException::conflicting('legacy-search', 'scout')->getMessage())
        ->toBe('Module [legacy-search] conflicts with [scout], which is enabled at this scope.');
});

it('formats ObjectMergeRefusedException for a self-merge and for an already-archived record, naming its role', function (): void {
    expect(ObjectMergeRefusedException::sameRecord(11)->getMessage())
        ->toBe('Object [11] cannot be merged with itself.');

    expect(ObjectMergeRefusedException::alreadyArchived(11, 'survivor')->getMessage())
        ->toBe('Object [11] cannot be the survivor of a merge — it is already archived.');

    expect(ObjectMergeRefusedException::alreadyArchived(12, 'merged-away record')->getMessage())
        ->toBe('Object [12] cannot be the merged-away record of a merge — it is already archived.');
});
