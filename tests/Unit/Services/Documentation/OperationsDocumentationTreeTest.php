<?php

declare(strict_types=1);

use App\Services\Documentation\OperationsDocumentationTree;

beforeEach(function (): void {
    $this->operationsDir = sys_get_temp_dir().'/operations-tree-test-'.bin2hex(random_bytes(8));
    mkdir("{$this->operationsDir}/en", recursive: true);
    mkdir("{$this->operationsDir}/ru", recursive: true);
    mkdir("{$this->operationsDir}/agent", recursive: true);
});

afterEach(function (): void {
    foreach (['en', 'ru', 'agent'] as $tree) {
        foreach (glob("{$this->operationsDir}/{$tree}/*") ?: [] as $file) {
            unlink($file);
        }
        rmdir("{$this->operationsDir}/{$tree}");
    }
    rmdir($this->operationsDir);
});

test('reads matching stems from all three trees, .prompt.md included', function (): void {
    foreach (['deploy', 'rollback'] as $stem) {
        touch("{$this->operationsDir}/en/{$stem}.md");
        touch("{$this->operationsDir}/ru/{$stem}.md");
        touch("{$this->operationsDir}/agent/{$stem}.prompt.md");
    }

    $stemsByTree = (new OperationsDocumentationTree($this->operationsDir))->stemsByTree();

    expect($stemsByTree['en'])->toBe(['deploy', 'rollback'])
        ->and($stemsByTree['ru'])->toBe(['deploy', 'rollback'])
        ->and($stemsByTree['agent'])->toBe(['deploy', 'rollback']);
});

test('reports a stem missing from one tree as absent only there', function (): void {
    touch("{$this->operationsDir}/en/deploy.md");
    touch("{$this->operationsDir}/ru/deploy.md");
    // agent/deploy.prompt.md deliberately not written.

    $stemsByTree = (new OperationsDocumentationTree($this->operationsDir))->stemsByTree();

    expect($stemsByTree['en'])->toBe(['deploy'])
        ->and($stemsByTree['ru'])->toBe(['deploy'])
        ->and($stemsByTree['agent'])->toBe([]);
});

test('returns an empty set for a tree directory that does not exist yet', function (): void {
    rmdir("{$this->operationsDir}/agent");

    expect((new OperationsDocumentationTree($this->operationsDir))->stemsByTree()['agent'])->toBe([]);

    // Recreated so afterEach()'s own cleanup does not fail on a missing directory.
    mkdir("{$this->operationsDir}/agent");
});
