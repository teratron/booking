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
            'phone' => ['nullable', 'string', 'max:32'],
            'message' => ['required', 'string', 'max:5000'],
            'page_url' => ['required', 'string', 'max:2048'],
            // Personal-data-processing consent (Figma's own feedback-popup
            // frame, node 244:230) is a gate on the submission, not a
            // stored fact — the same "accepted" pattern the object review
            // form's CAPTCHA challenge already uses elsewhere on this
            // portal, enforced here too rather than trusting a
            // client-side-only checkbox.
            'consent' => ['accepted'],
        ]);

        $service->submit(new FeedbackSubmissionData(
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            message: $data['message'],
            pageUrl: $data['page_url'],
            locale: app()->getLocale(),
        ));

        return redirect()->back()->with('public-feedback-submitted', true);
    }
}
