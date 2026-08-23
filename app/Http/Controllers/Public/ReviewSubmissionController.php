<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Exceptions\ReviewSubmissionRefusedException;
use App\Http\Controllers\Controller;
use App\Models\Object_;
use App\Services\Reviews\ReviewSubmissionService;
use App\Support\Reviews\ReviewSubmissionData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Receives a visitor's review from the object page's own submission form.
 * Both the `contact_gated` session gate and, in `open` mode, the CAPTCHA
 * check are enforced inside {@see ReviewSubmissionService} itself — this
 * controller never trusts that the form was reachable, only that a request
 * arrived.
 */
final class ReviewSubmissionController extends Controller
{
    /**
     * `$lang` is unused but must stay declared first — see
     * {@see ContactClickController}'s identical note on why.
     */
    public function __invoke(string $lang, Object_ $object, Request $request, ReviewSubmissionService $service): RedirectResponse
    {
        abort_unless($object->status === 'published', 404);

        $data = $request->validate([
            'author_name' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'max:5000'],
            'cf-turnstile-response' => ['nullable', 'string'],
        ]);

        try {
            $service->submit($object, new ReviewSubmissionData(
                authorName: $data['author_name'],
                rating: (int) $data['rating'],
                body: $data['body'],
                captchaResponse: $data['cf-turnstile-response'] ?? null,
            ), $request->ip() ?? '');
        } catch (ReviewSubmissionRefusedException) {
            return redirect()->back()->withErrors(['review' => __('public.object.reviews.form.refused')])->withInput();
        }

        return redirect()->back()->with('public-review-submitted', true);
    }
}
