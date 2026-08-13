<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Banners\Pages;

use App\Filament\Admin\Resources\Banners\BannerResource;
use App\Models\Banner;
use App\Models\BannerTranslation;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Override;

class EditBanner extends EditRecord
{
    protected static string $resource = BannerResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /**
     * The default scoped query excludes soft-deleted rows, which would make
     * an archived banner unreachable from its own row in the table's
     * trashed filter. Trashed records are included here; delete stays the
     * only lifecycle action, so reachability is not the same as inviting
     * further changes.
     */
    #[Override]
    protected function resolveRecord(int|string $key): Model
    {
        $record = static::getResource()::getEloquentQuery()
            ->withTrashed()
            ->whereKey($key)
            ->first();

        if ($record === null) {
            throw (new ModelNotFoundException)->setModel($this->getModel(), [$key]);
        }

        return $record;
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->currentBanner();

        /** @var BannerTranslation $translation */
        foreach ($record->translations as $translation) {
            $data['translations'][$translation->locale] = $translation->only(['link_text']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingTranslations = $data['translations'] ?? [];
        unset($data['translations']);

        return $data;
    }

    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Banner $record */
        $record->update($data);

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn ($value): bool => $value !== null && $value !== '');

            if ($fields === []) {
                continue;
            }

            $record->translations()->updateOrCreate(['locale' => $locale], $fields);
        }

        return $record;
    }

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    private function currentBanner(): Banner
    {
        /** @var Banner $record */
        $record = $this->getRecord();

        return $record;
    }
}
