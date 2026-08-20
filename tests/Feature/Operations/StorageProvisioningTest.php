<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Production Object Storage & CDN
|--------------------------------------------------------------------------
|
| The interface never changes — every provider (MinIO locally, Cloudflare
| R2 or Backblaze B2 in production) speaks the same S3 API behind the same
| 's3' disk (config/filesystems.php). This file covers the configuration
| surface only: a media URL resolves through the configured CDN host when
| one is set and through the bucket's own origin when it is not, no
| credential lands in any committed file, and the media disk stays distinct
| from the backup disk that protects it. No database access is involved —
| this suite reads configuration and the filesystem manager only, so it
| deliberately does not declare RefreshDatabase.
|
*/

it('resolves a media URL through the configured CDN host when one is set', function (): void {
    config(['filesystems.disks.s3.url' => 'https://cdn.example.com']);
    // The filesystem manager memoises a resolved disk instance — a config
    // change made after the disk was first touched this process would
    // otherwise silently not apply. Every real request resolves the disk
    // fresh, so this is a test-only concern, not a production one.
    Storage::forgetDisk('s3');

    $url = Storage::disk('s3')->url('objects/photo.jpg');

    expect($url)->toStartWith('https://cdn.example.com/')
        ->and($url)->toEndWith('objects/photo.jpg');
});

it('falls back to the bucket origin URL when no CDN host is configured', function (): void {
    config(['filesystems.disks.s3.url' => null]);
    Storage::forgetDisk('s3');

    $url = Storage::disk('s3')->url('objects/photo.jpg');

    expect($url)->not->toContain('cdn.example.com')
        ->and($url)->toEndWith('/'.config('filesystems.disks.s3.bucket').'/objects/photo.jpg');
});

it('resolves the backup disk and the media disk to different destinations', function (): void {
    $mediaBucket = (string) config('filesystems.disks.s3.bucket');
    $backupBucket = (string) config('filesystems.disks.backups.bucket');

    expect($mediaBucket)->not->toBeEmpty()
        ->and($backupBucket)->not->toBeEmpty()
        ->and($mediaBucket)->not->toBe($backupBucket);

    // Full destination, not only the bucket name in isolation — a future
    // config change repointing both disks at the same endpoint while
    // somehow keeping bucket names distinct would still be a real
    // collision if the endpoint were what actually mattered; it is the
    // (endpoint, bucket) pair together that must differ.
    $mediaDestination = config('filesystems.disks.s3.endpoint').'/'.$mediaBucket;
    $backupDestination = config('filesystems.disks.backups.endpoint').'/'.$backupBucket;

    expect($mediaDestination)->not->toBe($backupDestination);
});

it('carries no real credential in any committed file', function (): void {
    // "Committed" is asked literally — git's own tracked-file list, not a
    // guess at which directories matter. Excludes nothing: a leaked
    // credential in a fixture or a doc is exactly as real as one in app/.
    $result = Process::path(base_path())->run(['git', 'ls-files']);

    expect($result->successful())->toBeTrue();

    $files = array_filter(explode("\n", trim($result->output())));

    // High-confidence credential shapes only — patterns specific enough
    // that a real hit is never a false positive, unlike a generic
    // high-entropy-string heuristic that would flag migration hashes and
    // compiled asset fingerprints as often as it flags anything real.
    $patterns = [
        'AWS access key ID' => '/\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/',
        'private key block' => '/-----BEGIN(?: RSA| EC| OPENSSH| DSA)? PRIVATE KEY-----/',
        'Slack token' => '/\bxox[baprs]-[0-9A-Za-z-]{10,}\b/',
        'Stripe live key' => '/\bsk_live_[0-9A-Za-z]{10,}\b/',
        'GitHub token' => '/\bgh[pousr]_[0-9A-Za-z]{30,}\b/',
    ];

    $offenders = [];

    foreach ($files as $file) {
        $path = base_path($file);

        if (! is_file($path)) {
            continue;
        }

        $contents = (string) file_get_contents($path);

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                $offenders[] = "{$file} ({$label})";
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('carries only placeholder credentials in .env.example for the object storage variables', function (): void {
    $contents = (string) file_get_contents(base_path('.env.example'));

    // The same placeholders every other credential-shaped variable in this
    // file already uses (DB_PASSWORD=booking, etc.) — a real key would
    // never coincidentally equal one of these literal strings.
    expect($contents)->toContain('AWS_ACCESS_KEY_ID=booking')
        ->and($contents)->toContain('AWS_SECRET_ACCESS_KEY=booking-secret')
        // Empty, not merely present — a real URL would still satisfy a bare
        // toContain('MEDIA_CDN_URL=') check as a substring of its own line.
        ->and($contents)->toMatch('/^MEDIA_CDN_URL=\s*$/m');
});
