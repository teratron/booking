<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the moderation mode a change is decided against — `immediate` or
 * `review` — most-specific-wins down the ladder: object → owner → category
 * → country → portal default (`[TZ]` §44.1).
 *
 * The portal default is not a row in this ladder's own table; it is the
 * typed `moderation.default_mode` setting, so there is exactly one place a
 * portal-wide default lives rather than two that could disagree.
 */
final class ModerationModeResolver
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function resolve(ModerationScopeContext $context): string
    {
        $ladder = [
            ['level' => 'object', 'reference' => $context->objectId],
            ['level' => 'owner', 'reference' => $context->ownerId],
            ['level' => 'category', 'reference' => $context->categoryId],
            ['level' => 'country', 'reference' => $context->countryId],
        ];

        foreach ($ladder as $rung) {
            if ($rung['reference'] === null) {
                continue;
            }

            $mode = DB::table('moderation_settings')
                ->where('scope_level', $rung['level'])
                ->where('scope_reference_id', $rung['reference'])
                ->value('mode');

            if ($mode !== null) {
                return (string) $mode;
            }
        }

        return (string) $this->settings->get('moderation.default_mode');
    }
}
