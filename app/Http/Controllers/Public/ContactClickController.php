<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\BannerClickController;
use App\Http\Controllers\Controller;
use App\Models\ContactChannel;
use App\Models\ContactChannelType;
use App\Models\Object_;
use App\Services\Analytics\EventCaptureService;
use App\Services\Contact\ContactChannelLinkResolver;
use Illuminate\Http\RedirectResponse;

/**
 * The click-through every rendered contact action links to instead of its
 * deep link directly, so a contact click is counted server-side — before
 * the visitor ever leaves the portal — the same pattern
 * {@see BannerClickController} already establishes
 * for banner clicks. The redirect target is still the channel's own direct
 * deep link (`tel:`, `wa.me`, …); the portal never becomes a party to the
 * conversation that follows.
 */
final class ContactClickController extends Controller
{
    public function __construct(
        private readonly ContactChannelLinkResolver $resolver,
        private readonly EventCaptureService $events,
    ) {}

    /**
     * `$lang` is unused but must stay declared first: Laravel's controller
     * dependency resolver splices route parameters by reflection position,
     * and a leading route segment with no corresponding method parameter
     * throws that splice out of alignment for the models that follow.
     */
    public function __invoke(string $lang, Object_ $object, ContactChannel $channel): RedirectResponse
    {
        abort_unless($object->status === 'published', 404);
        abort_unless($channel->object_id === $object->id, 404);
        abort_unless($channel->is_active, 404);

        $type = $channel->contactChannelType;
        abort_unless($type instanceof ContactChannelType, 404);

        // Captured before the link is even resolved: a measurement failure
        // must never cost the visitor the hand-off, and symmetrically, a
        // hand-off that cannot complete (a misconfigured channel) must
        // never suppress the measurement of the visitor's real intent to
        // make contact.
        $this->events->capture('contact_click', $object, [
            'contact_channel_type_id' => $type->id,
            'territory_id' => $object->territory_id,
            'country_id' => $object->country_id,
            'locale' => $lang,
        ]);

        $href = $this->resolver->resolve($type, $channel->raw_value);
        abort_if($href === null, 404);

        return redirect()->away($href);
    }
}
