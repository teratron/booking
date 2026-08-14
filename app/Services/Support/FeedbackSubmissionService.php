<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Models\FeedbackSubmission;
use App\Support\Shell\FeedbackSubmissionData;

/**
 * Persists a visitor's message from the shared feedback overlay, invokable
 * from any public page.
 */
final class FeedbackSubmissionService
{
    public function submit(FeedbackSubmissionData $data): FeedbackSubmission
    {
        return FeedbackSubmission::query()->create([
            'name' => $data->name,
            'email' => $data->email,
            'message' => $data->message,
            'page_url' => $data->pageUrl,
            'locale' => $data->locale,
        ]);
    }
}
