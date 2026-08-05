<?php

declare(strict_types=1);

test('the Vite build produced a manifest with both entry points', function (): void {
    $manifestPath = public_path('build/manifest.json');

    expect($manifestPath)->toBeFile();

    $manifest = json_decode((string) file_get_contents($manifestPath), true);

    expect($manifest)->toHaveKeys(['resources/css/app.css', 'resources/js/app.js']);
});
