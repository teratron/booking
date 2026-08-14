<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\NewsItems\Pages;

use App\Filament\Cabinet\Resources\NewsItems\NewsItemResource;
use App\Models\Object_;
use App\Models\User;
use App\Services\Cabinet\NewsItemSubmissionService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * The owner cabinet's news-submission page. The form's own five fields are
 * shaped into {@see NewsItemSubmissionService::submit()}'s own input and
 * routed through moderation there — published immediately, or withheld
 * behind a pending review request — never applied here directly.
 */
class CreateNewsItem extends CreateRecord
{
    protected static string $resource = NewsItemResource::class;

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

        $outcome = app(NewsItemSubmissionService::class)->submit($tenant, [
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'body' => $data['body'],
            'publish_at' => $data['publish_at'] ?? null,
        ], $actor);

        $this->wasWithheld = ! $outcome->applied;

        return $outcome->record;
    }

    #[Override]
    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->wasWithheld
            ? __('panel.cabinet.news_items.lifecycle.submitted_for_review')
            : __('panel.cabinet.news_items.lifecycle.published');
    }
}
