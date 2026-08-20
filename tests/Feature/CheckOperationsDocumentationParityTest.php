<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

/*
|--------------------------------------------------------------------------
| docs:check-operations-parity
|--------------------------------------------------------------------------
|
| The command's own git call is faked (Process::fake) so these assertions
| exercise the exit-code contract the quality.yml step depends on, without
| needing a real pull request to diff against.
|
*/

test('fails when only the English rendering of a procedure was touched', function (): void {
    Process::fake([
        'git diff*' => Process::result("docs/operations/en/deploy.md\n"),
    ]);

    $this->artisan('docs:check-operations-parity', ['--base' => 'fixture-base'])
        ->assertFailed();
});

test('passes when all three renderings of the changed procedure were touched together', function (): void {
    Process::fake([
        'git diff*' => Process::result(implode("\n", [
            'docs/operations/en/deploy.md',
            'docs/operations/ru/deploy.md',
            'docs/operations/agent/deploy.prompt.md',
        ])."\n"),
    ]);

    $this->artisan('docs:check-operations-parity', ['--base' => 'fixture-base'])
        ->assertSuccessful();
});

test('passes when two procedures change and each is fully touched across all three trees', function (): void {
    Process::fake([
        'git diff*' => Process::result(implode("\n", [
            'docs/operations/en/deploy.md',
            'docs/operations/ru/deploy.md',
            'docs/operations/agent/deploy.prompt.md',
            'docs/operations/en/rollback.md',
            'docs/operations/ru/rollback.md',
            'docs/operations/agent/rollback.prompt.md',
        ])."\n"),
    ]);

    $this->artisan('docs:check-operations-parity', ['--base' => 'fixture-base'])
        ->assertSuccessful();
});

test('fails when one of two changed procedures is missing its agent rendering', function (): void {
    Process::fake([
        'git diff*' => Process::result(implode("\n", [
            'docs/operations/en/deploy.md',
            'docs/operations/ru/deploy.md',
            'docs/operations/agent/deploy.prompt.md',
            'docs/operations/en/rollback.md',
            'docs/operations/ru/rollback.md',
            // agent/rollback.prompt.md deliberately missing.
        ])."\n"),
    ]);

    $this->artisan('docs:check-operations-parity', ['--base' => 'fixture-base'])
        ->assertFailed();
});

test('ignores files outside docs/operations and passes when nothing there changed', function (): void {
    Process::fake([
        'git diff*' => Process::result("docs/release/branching.md\napp/Providers/AppServiceProvider.php\n"),
    ]);

    $this->artisan('docs:check-operations-parity', ['--base' => 'fixture-base'])
        ->assertSuccessful();
});

test('passes with an empty diff', function (): void {
    Process::fake([
        'git diff*' => Process::result(''),
    ]);

    $this->artisan('docs:check-operations-parity', ['--base' => 'fixture-base'])
        ->assertSuccessful();
});
