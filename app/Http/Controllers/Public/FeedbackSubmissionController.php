<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Support\FeedbackSubmissionService;
use App\Support\Shell\FeedbackSubmissionData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Receives a submission from the shared feedback overlay, invokable from
 * any public page.
 */
final class FeedbackSubmissionController extends Controller
{
    public function __invoke(Request $request, FeedbackSubmissionService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'page_url' => ['required', 'string', 'max:2048'],
        ]);

        $service->submit(new FeedbackSubmissionData(
            name: $data['name'],
            email: $data['email'],
            message: $data['message'],
            pageUrl: $data['page_url'],
            locale: app()->getLocale(),
        ));

        return redirect()->back()->with('public-feedback-submitted', true);
    }
}
