<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Owners\RelationManagers;

use App\Exceptions\OwnerDetachmentException;
use App\Models\Object_;
use App\Models\User;
use App\Services\Owners\OwnerAccountService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * The objects an owner currently holds, with the two moves the owner
 * management screen makes over that set: attaching one — which may be a
 * plain assignment or, when the object already belonged to someone else, a
 * reassignment {@see OwnerAccountService::attachObject()} resolves either
 * way — and detaching one, clearing its owner.
 *
 * Neither move uses Filament's built-in `AttachAction`/`DetachAction`: both
 * assume a pivot-table relationship, and `objects.owner_id` is a plain
 * foreign key. Both go through {@see OwnerAccountService} instead, so the
 * journal entry and the staff-grant cleanup a reassignment triggers happen
 * every time, not only when a developer remembers to call the service by
 * hand.
 */
class ObjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'objects';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.owners.objects.title');
    }

    #[Override]
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(self::baseQuery(...))
            ->columns(self::columns())
            ->headerActions([$this->attachAction()])
            ->recordActions([$this->detachAction()]);
    }

    /**
     * @param  Builder<Object_>  $query
     * @return Builder<Object_>
     */
    private static function baseQuery(Builder $query): Builder
    {
        return $query
            ->withUnmoderated()
            ->with(['translations', 'objectType', 'country']);
    }

    /** @return array<TextColumn> */
    private static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('panel.objects.columns.name'))
                ->getStateUsing(fn (Object_ $record): string => $record->name ?? "#{$record->id}"),

            TextColumn::make('objectType.key')
                ->label(__('panel.objects.columns.type'))
                ->badge(),

            TextColumn::make('country.code')
                ->label(__('panel.objects.columns.country')),

            TextColumn::make('status')
                ->label(__('panel.objects.columns.status'))
                ->badge(),
        ];
    }

    private function attachAction(): Action
    {
        return Action::make('attach')
            ->label(__('panel.owners.objects.attach'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('update', $this->getOwnerRecord()))
            ->schema([
                Select::make('object_id')
                    ->label(__('panel.owners.objects.object'))
                    // Not `->relationship()`: the value is read once in the
                    // action closure and passed to the service explicitly,
                    // the same reasoning `EditObject`'s own transfer-of-
                    // ownership select follows for the identical shape.
                    ->options(fn (): array => Object_::query()->with('translations')->get()
                        ->mapWithKeys(fn (Object_ $object): array => [$object->id => $object->name ?? "#{$object->id}"])
                        ->all())
                    ->required()
                    ->searchable(),
            ])
            ->action(function (array $data): void {
                $actor = Filament::auth()->user();
                $object = Object_::query()->find($data['object_id']);

                if ($actor instanceof User && $object instanceof Object_) {
                    /** @var User $owner */
                    $owner = $this->getOwnerRecord();

                    app(OwnerAccountService::class)->attachObject($owner, $object, $actor);
                }

                Notification::make()->title(__('panel.owners.objects.applied'))->success()->send();
            });
    }

    private function detachAction(): Action
    {
        return Action::make('detach')
            ->label(__('panel.owners.objects.detach'))
            ->color('danger')
            ->authorize(fn (): bool => (bool) auth()->user()?->can('update', $this->getOwnerRecord()))
            ->requiresConfirmation()
            ->modalDescription(__('panel.owners.objects.detach_confirm'))
            ->action(function (Object_ $record): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                try {
                    app(OwnerAccountService::class)->detachObject($record, $actor);
                } catch (OwnerDetachmentException) {
                    Notification::make()
                        ->danger()
                        ->title(__('panel.owners.objects.detachment_refused'))
                        ->send();

                    return;
                }

                Notification::make()->title(__('panel.owners.objects.applied'))->success()->send();
            });
    }
}
