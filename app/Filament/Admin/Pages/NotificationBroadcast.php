<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Exceptions\BroadcastRateLimitedException;
use App\Models\Country;
use App\Models\PlacementPackage;
use App\Models\Territory;
use App\Models\User;
use App\Services\Notifications\BroadcastComposer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Composes and sends one administration message to every distinct object
 * owner reached by a country, resort, or placement-package targeting
 * choice. A custom page rather than a resource: there is no record this
 * screen edits, only a one-shot action with a live-computed audience.
 *
 * Sending always runs through {@see BroadcastComposer}, which is the only
 * place recipient resolution, the daily rate limit, and delivery happen —
 * this class is presentation only.
 *
 * @property-read Schema $form
 */
class NotificationBroadcast extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.notification-broadcast';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'communication';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('notification.create');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.broadcast.title');
    }

    public function getTitle(): string
    {
        return __('panel.broadcast.title');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('target_type')
                    ->label(__('panel.broadcast.fields.target_type'))
                    ->options([
                        BroadcastComposer::TARGET_COUNTRY => __('panel.broadcast.target_types.country'),
                        BroadcastComposer::TARGET_RESORT => __('panel.broadcast.target_types.resort'),
                        BroadcastComposer::TARGET_PACKAGE => __('panel.broadcast.target_types.package'),
                    ])
                    ->required()
                    ->live(),

                Select::make('target_id')
                    ->label(__('panel.broadcast.fields.target'))
                    ->options(fn (Get $get): array => $this->targetOptions($get('target_type')))
                    ->disabled(fn (Get $get): bool => blank($get('target_type')))
                    ->required()
                    ->live()
                    ->searchable(),

                TextInput::make('title')
                    ->label(__('panel.broadcast.fields.title'))
                    ->required()
                    ->maxLength(255),

                Textarea::make('body')
                    ->label(__('panel.broadcast.fields.body'))
                    ->required()
                    ->rows(5),
            ])
            ->statePath('data');
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label(__('panel.broadcast.actions.send'))
                ->disabled(fn (): bool => app(BroadcastComposer::class)->isRateLimited())
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('panel.broadcast.confirmation', ['count' => $this->recipientCount()]))
                ->modalSubmitActionLabel(fn (): string => __('panel.broadcast.confirm_label', ['count' => $this->recipientCount()]))
                ->action(fn () => $this->send()),
        ];
    }

    public function remainingQuota(): int
    {
        return app(BroadcastComposer::class)->remainingQuota();
    }

    private function send(): void
    {
        /** @var array{target_type: string, target_id: int|string, title: string, body: string} $state */
        $state = $this->form->getState();

        try {
            $count = app(BroadcastComposer::class)->send(
                $state['target_type'],
                (int) $state['target_id'],
                $state['title'],
                $state['body'],
                $this->actor(),
            );
        } catch (BroadcastRateLimitedException $exception) {
            Notification::make()
                ->title(__('panel.broadcast.rate_limited'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('panel.broadcast.sent', ['count' => $count]))
            ->success()
            ->send();

        $this->form->fill();
    }

    /**
     * The current live count for whatever target_type/target_id the form
     * holds right now, read without triggering validation — the confirmation
     * dialog must show a real number even while other required fields (title,
     * body) are still blank.
     */
    private function recipientCount(): int
    {
        $targetType = $this->data['target_type'] ?? null;
        $targetId = $this->data['target_id'] ?? null;

        if (! is_string($targetType) || $targetType === '' || $targetId === null || $targetId === '') {
            return 0;
        }

        return app(BroadcastComposer::class)->recipientCount($targetType, (int) $targetId);
    }

    /** @return array<int, string> */
    private function targetOptions(?string $targetType): array
    {
        return match ($targetType) {
            BroadcastComposer::TARGET_COUNTRY => Country::query()->orderBy('display_order')->get()
                ->mapWithKeys(fn (Country $country): array => [$country->id => $country->code])
                ->all(),
            BroadcastComposer::TARGET_RESORT => Territory::query()->with('translations')->get()
                ->mapWithKeys(fn (Territory $territory): array => [$territory->id => $territory->name ?? "#{$territory->id}"])
                ->all(),
            BroadcastComposer::TARGET_PACKAGE => PlacementPackage::query()->with('translations')->get()
                ->mapWithKeys(fn (PlacementPackage $package): array => [$package->id => $package->name ?? "#{$package->id}"])
                ->all(),
            default => [],
        };
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
