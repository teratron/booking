<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\NewsItems\Pages;

use App\Filament\Cabinet\Resources\NewsItems\NewsItemResource;
use App\Models\NewsItem;
use App\Models\NewsTranslation;
use App\Models\Object_;
use App\Models\User;
use App\Services\Cabinet\NewsItemSubmissionService;
use App\Support\Cabinet\ContentSubmissionOutcome;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * The owner cabinet's news-edit page. An edit to an item that is not yet
 * live applies directly; an edit to an already-published, already-approved
 * item is routed back through moderation — see
 * {@see NewsItemSubmissionService::applyEdit()}.
 */
class EditNewsItem extends EditRecord
{
    protected static string $resource = NewsItemResource::class;

    private ?ContentSubmissionOutcome $lastOutcome = null;

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ?NewsTranslation $translation */
        $translation = $this->currentNewsItem()->translate(app()->getLocale(), withFallback: true);

        $data['title'] = $translation?->title;
        $data['summary'] = $translation?->summary;
        $data['body'] = $translation?->body;

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
        /** @var NewsItem $record */
        $actor = Filament::auth()->user();
        $tenant = Filament::getTenant();

        if (! $actor instanceof User || ! $tenant instanceof Object_) {
            throw new AuthorizationException;
        }

        $this->lastOutcome = app(NewsItemSubmissionService::class)->applyEdit($record, $tenant, [
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'body' => $data['body'],
            'publish_at' => $data['publish_at'] ?? null,
        ], $actor);

        return $this->lastOutcome->record;
    }

    #[Override]
    protected function getSavedNotificationTitle(): ?string
    {
        return $this->lastOutcome?->applied === false
            ? __('panel.cabinet.news_items.lifecycle.submitted_for_review')
            : __('panel.cabinet.news_items.lifecycle.published');
    }

    private function currentNewsItem(): NewsItem
    {
        /** @var NewsItem $record */
        $record = $this->getRecord();

        return $record;
    }
}
