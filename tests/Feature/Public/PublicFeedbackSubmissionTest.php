<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Feedback Submission
|--------------------------------------------------------------------------
|
| The shared feedback overlay, invokable from any public page. F-16: this
| endpoint had no rate limit at all, while the sibling review-submission
| endpoint two lines away in the same route file already carried one —
| eight rapid submissions from one client were all accepted and stored.
|
*/

beforeEach(function (): void {
    // The {lang} route segment 404s without an active language row —
    // this endpoint carries no geography of its own to seed for.
    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
});

/** @param  array<string, mixed>  $overrides */
function feedbackPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'QA Tester',
        'email' => 'qa@example.test',
        'message' => 'Testing the feedback channel end to end.',
        'page_url' => 'https://portal.test/en',
        // The overlay's personal-data-processing checkbox — the controller
        // rejects the submission without it (rule `accepted`).
        'consent' => '1',
    ], $overrides);
}

it('stores a valid submission and redirects back with a success flag', function (): void {
    $this->post(route('public.feedback.submit', ['lang' => 'en']), feedbackPayload())
        ->assertRedirect()
        ->assertSessionHas('public-feedback-submitted', true);

    expect(DB::table('feedback_submissions')->where('email', 'qa@example.test')->exists())->toBeTrue();
});

it('rejects an invalid email with a validation error, storing nothing', function (): void {
    $this->post(route('public.feedback.submit', ['lang' => 'en']), feedbackPayload(['email' => 'not-an-email']))
        ->assertSessionHasErrors('email');

    expect(DB::table('feedback_submissions')->exists())->toBeFalse();
});

it('rate-limits repeated submissions from the same client', function (): void {
    // F-16/S-15: previously unthrottled, unlike the review-submission
    // endpoint in the same file which already carried throttle:5,1.
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('public.feedback.submit', ['lang' => 'en']), feedbackPayload())
            ->assertRedirect();
    }

    $this->post(route('public.feedback.submit', ['lang' => 'en']), feedbackPayload())
        ->assertStatus(429);

    expect(DB::table('feedback_submissions')->count())->toBe(5);
});
