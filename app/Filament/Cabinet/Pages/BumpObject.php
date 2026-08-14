<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Pages;

use App\Exceptions\BumpRefusedException;
use App\Models\Object_;
use App\Models\User;
use App\Services\Placement\BumpService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The owner cabinet's entry point into {@see BumpService} — a UI surface
 * and an owner-presentable refusal message only. Every actual bump rule
 * (package eligibility, the free-bump interval, the per-period allowance)
 * is decided entirely inside the service; this page adds none of its own.
 *
 * Always calls with `type: 'free'` — a paid bump has no self-service
 * checkout in this portal (payments are staff-recorded against a ledger,
 * never owner-initiated), so the only bump an owner can trigger themselves
 * is the one their placement package's free allowance already covers.
 * Scope defaults to the object's own territory, mirroring the back
 * office's own bump action — no scope picker is offered here either.
 */
class BumpObject extends Page
{
    protected static string $routePath = '/bump-object';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected string $view = 'filament.cabinet.pages.bump-object';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return __('panel.cabinet.bump.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('comment')
                    ->label(__('panel.objects.lifecycle.bump_comment')),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm_bump')
                ->label(__('panel.cabinet.bump.confirm'))
                ->color('primary')
                ->authorize(fn (): bool => ($tenant = Filament::getTenant()) instanceof Object_
                    && (bool) auth()->user()?->can('update', $tenant))
                ->action(fn () => $this->bump()),
        ];
    }

    private function bump(): void
    {
        $tenant = Filament::getTenant();
        $actor = Filament::auth()->user();

        if (! $tenant instanceof Object_ || ! $actor instanceof User) {
            return;
        }

        $scope = $tenant->territory()->first();

        if ($scope === null) {
            return;
        }

        try {
            app(BumpService::class)->bump($tenant, $scope, $actor, 'free', $this->data['comment'] ?? null);
        } catch (BumpRefusedException $exception) {
            Notification::make()
                ->danger()
                ->title(__('panel.cabinet.bump.refused'))
                ->body($this->refusalMessage($exception))
                ->send();

            return;
        }

        Notification::make()->title(__('panel.cabinet.bump.applied'))->success()->send();
    }

    private function refusalMessage(BumpRefusedException $exception): string
    {
        return match ($exception->reasonKey) {
            'interval_not_elapsed' => __('panel.cabinet.bump.refused_reasons.interval_not_elapsed', [
                'hours' => $exception->intervalHours,
            ]),
            'allowance_exhausted' => __('panel.cabinet.bump.refused_reasons.allowance_exhausted', [
                'count' => $exception->freeBumpsPerPeriod,
            ]),
            'not_allowed_by_package' => __('panel.cabinet.bump.refused_reasons.not_allowed_by_package'),
            'no_current_placement' => __('panel.cabinet.bump.refused_reasons.no_current_placement'),
            default => __('panel.cabinet.bump.refused_reasons.unknown'),
        };
    }
}
