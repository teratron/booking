<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Exceptions\ReviewSubmissionRefusedException;
use App\Filament\Admin\Resources\Reviews\ReviewResource;
use App\Models\Object_;
use App\Models\Review;
use App\Services\Integrations\CaptchaVerifier;
use App\Support\Reviews\ReviewSubmissionData;

/**
 * The public review-submission write path — the one place a visitor's
 * review reaches the database. Every submitted review enters as
 * `status = 'pending'`, regardless of which gate admitted it: this class
 * decides who may submit, never whether what they submit is published —
 * that is the moderation checkpoint's job
 * ({@see ReviewResource}), unchanged
 * by which mode is active here.
 */
final class ReviewSubmissionService
{
    public function __construct(
        private readonly ReviewSubmissionGate $gate,
        private readonly CaptchaVerifier $captcha,
    ) {}

    /**
     * @throws ReviewSubmissionRefusedException when the contact-click gate has not been
     *                                          satisfied in `contact_gated` mode, or the CAPTCHA challenge fails in `open` mode
     */
    public function submit(Object_ $object, ReviewSubmissionData $data, string $clientIp): Review
    {
        if (! $this->gate->canSubmit($object->id)) {
            throw ReviewSubmissionRefusedException::gateClosed($object->id);
        }

        if ($this->gate->mode() === 'open' && ! $this->captcha->verify($data->captchaResponse, $clientIp)) {
            throw ReviewSubmissionRefusedException::captchaFailed();
        }

        return Review::query()->create([
            'object_id' => $object->id,
            // Denormalized from the object at submission time — see the
            // migration's own comment for why the admin ReviewResource's
            // scope-narrowing query needs these as plain columns here.
            'country_id' => $object->country_id,
            'territory_id' => $object->territory_id,
            'object_type_id' => $object->object_type_id,
            'rating' => $data->rating,
            'body' => $data->body,
            'author_name' => $data->authorName,
            'status' => 'pending',
        ]);
    }
}
