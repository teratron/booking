<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Promotions\Pages;

use App\Filament\Cabinet\Resources\Promotions\PromotionResource;
use App\Models\Object_;
use App\Models\User;
use App\Services\Cabinet\PromotionSubmissionService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * The owner cabinet's promotion-submission page. The form's own five fields
 * are shaped into {@see PromotionSubmissionService::submit()}'s own input
 * and routed through moderation there — published immediately, or withheld
 * behind a pending review request — never applied here directly.
 */
class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    private bool $wasWithheld = false;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException when reached with no authenticated actor or resolved tenant — never expected through a real cabinet request
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $actor = Filament::auth()->user();
        $tenant = Filament::getTenant();

        if (! $actor instanceof User || ! $tenant instanceof Object_) {
            throw new AuthorizationException;
        }

        $outcome = app(PromotionSubmissionService::class)->submit($tenant, [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
        ], $actor);

        $this->wasWithheld = ! $outcome->applied;

        return $outcome->record;
    }

    #[Override]
    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->wasWithheld
            ? __('panel.cabinet.promotions.lifecycle.submitted_for_review')
            : __('panel.cabinet.promotions.lifecycle.published');
    }
}
