<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Language;
use App\Models\User;
use App\Services\Localization\LanguageRegistry;
use App\Services\Localization\TranslatableEntity;
use App\Services\Localization\TranslatableEntityRegistry;
use App\Services\Localization\TranslationCompletenessReport;
use App\Services\Localization\TranslationCopyService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Completeness across every translatable entity class, discovered from the
 * models that implement the `Translatable` contract rather than a
 * maintained list. The summary matrix answers "how much is left"; the
 * table below drills into one entity class and one language at a time, so
 * it stays a plain Eloquent query instead of a hand-built union.
 *
 * @property-read Table $table
 */
class TranslationReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.admin.pages.translation-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    public ?string $entityClass = null;

    public ?string $locale = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('settings.view');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.translation_report.title');
    }

    public function getTitle(): string
    {
        return __('panel.translation_report.title');
    }

    public function mount(): void
    {
        $entries = app(TranslatableEntityRegistry::class)->entries();
        $this->entityClass = $entries[0]->modelClass ?? null;
        $this->locale = $this->selectableLocales()[0] ?? null;
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    /** @return list<array{entity: TranslatableEntity, locale: string, total: int, translated: int, needsReview: int, missing: int}> */
    public function summary(): array
    {
        return app(TranslationCompletenessReport::class)->summary($this->activeLocales());
    }

    /** @return list<TranslatableEntity> */
    public function entities(): array
    {
        return app(TranslatableEntityRegistry::class)->entries();
    }

    /** @return list<string> */
    public function selectableLocales(): array
    {
        return $this->activeLocales();
    }

    public function entityLabel(string $modelClass): string
    {
        return Str::headline(class_basename($modelClass));
    }

    public function table(Table $table): Table
    {
        $entity = $this->currentEntity();
        $locale = $this->locale;
        $report = app(TranslationCompletenessReport::class);

        return $table
            ->query(function () use ($entity, $locale, $report): Builder {
                if ($entity === null || $locale === null) {
                    return Language::query()->whereRaw('1 = 0');
                }

                return $report->missingQuery($entity, $locale);
            })
            ->columns([
                TextColumn::make('id')
                    ->label(__('panel.translation_report.columns.entity'))
                    ->getStateUsing(fn (Model $record): string => $this->labelFor($record, $entity)),

                TextColumn::make('translation_state')
                    ->label(__('panel.translation_report.columns.state'))
                    ->getStateUsing(fn (Model $record): string => $this->stateFor($record, $locale)),
            ])
            ->recordActions([
                Action::make('copy_from_primary')
                    ->label(__('panel.translation_report.actions.copy_from_primary'))
                    ->action(fn (Model $record) => $this->copyFromPrimary($record, $entity)),

                Action::make('publish')
                    ->label(__('panel.translation_report.actions.publish'))
                    /** @phpstan-ignore-next-line method.notFound (provided by astrotomic/laravel-translatable) */
                    ->visible(fn (Model $record): bool => $locale !== null && $record->hasTranslation($locale))
                    ->action(fn (Model $record) => $this->publish($record)),
            ]);
    }

    private function currentEntity(): ?TranslatableEntity
    {
        if ($this->entityClass === null) {
            return null;
        }

        return app(TranslatableEntityRegistry::class)->find($this->entityClass);
    }

    private function labelFor(Model $record, ?TranslatableEntity $entity): string
    {
        if ($entity === null) {
            return "#{$record->getKey()}";
        }

        $primary = app(LanguageRegistry::class)->primaryLocale();

        /** @phpstan-ignore-next-line method.notFound (provided by astrotomic/laravel-translatable) */
        $translation = $record->translate($primary, false);

        $value = $translation?->{$entity->labelColumn};

        return is_string($value) && $value !== '' ? $value : "#{$record->getKey()}";
    }

    private function stateFor(Model $record, ?string $locale): string
    {
        if ($locale === null) {
            return '—';
        }

        /** @phpstan-ignore-next-line method.notFound (provided by astrotomic/laravel-translatable) */
        return $record->hasTranslation($locale)
            ? __('panel.translation_report.state.needs_review')
            : __('panel.translation_report.state.missing');
    }

    private function copyFromPrimary(Model $record, ?TranslatableEntity $entity): void
    {
        if ($entity === null || $this->locale === null) {
            return;
        }

        app(TranslationCopyService::class)->copyFromPrimary($record, $this->locale, $entity->translatedAttributes, $this->actor());

        Notification::make()->title(__('panel.translation_report.notifications.copied'))->success()->send();
    }

    private function publish(Model $record): void
    {
        if ($this->locale === null) {
            return;
        }

        try {
            app(TranslationCopyService::class)->publish($record, $this->locale, $this->actor());
        } catch (ModelNotFoundException) {
            Notification::make()->title(__('panel.translation_report.notifications.publish_refused'))->danger()->send();

            return;
        }

        Notification::make()->title(__('panel.translation_report.notifications.published'))->success()->send();
    }

    /** @return list<string> */
    private function activeLocales(): array
    {
        return array_values(Language::query()->where('is_active', true)->orderBy('display_order')->pluck('code')->all());
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
