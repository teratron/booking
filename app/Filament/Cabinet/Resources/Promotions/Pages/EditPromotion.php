<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Promotions\Pages;

use App\Filament\Cabinet\Resources\Promotions\PromotionResource;
use App\Models\Object_;
use App\Models\Promotion;
use App\Models\PromotionTranslation;
use App\Models\User;
use App\Services\Cabinet\PromotionSubmissionService;
use App\Support\Cabinet\ContentSubmissionOutcome;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * The owner cabinet's promotion-edit page. An edit to a promotion that is
 * not yet live applies directly; an edit to an already-published,
 * already-approved promotion is routed back through moderation — see
 * {@see PromotionSubmissionService::applyEdit()}.
 */
class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    private ?ContentSubmissionOutcome $lastOutcome = null;

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ?PromotionTranslation $translation */
        $translation = $this->currentPromotion()->translate(app()->getLocale(), withFallback: true);

        $data['title'] = $translation?->title;
        $data['description'] = $translation?->summary;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException when reached with no authenticated actor or resolved tenant — never expected through a real cabinet request
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Promotion $record */
        $actor = Filament::auth()->user();
        $tenant = Filament::getTenant();

        if (! $actor instanceof User || ! $tenant instanceof Object_) {
            throw new AuthorizationException;
        }

        $this->lastOutcome = app(PromotionSubmissionService::class)->applyEdit($record, $tenant, [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
        ], $actor);

        return $this->lastOutcome->record;
    }

    #[Override]
    protected function getSavedNotificationTitle(): ?string
    {
        return $this->lastOutcome?->applied === false
            ? __('panel.cabinet.promotions.lifecycle.submitted_for_review')
            : __('panel.cabinet.promotions.lifecycle.published');
    }

    private function currentPromotion(): Promotion
    {
        /** @var Promotion $record */
        $record = $this->getRecord();

        return $record;
    }
}
