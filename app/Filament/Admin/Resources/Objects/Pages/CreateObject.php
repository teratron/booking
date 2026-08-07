<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Objects\Pages;

use App\Filament\Admin\Resources\Objects\ObjectResource;
use App\Models\Object_;
use App\Models\User;
use App\Services\Authorization\ScopeAuthorizer;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Override;

/**
 * Creation has no existing record for the policy's `create` ability to scope
 * against, so a category- or country-scoped administrator submitting a form
 * for a country outside their grant would otherwise slip through the field
 * being merely present rather than hidden. Validated here instead, against
 * the same authorizer every list and policy already uses — a rejected
 * request, not a restricted option list.
 */
class CreateObject extends CreateRecord
{
    protected static string $resource = ObjectResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->assertWithinScope((int) $data['country_id'], (int) $data['object_type_id']);

        $this->pendingTranslations = $data['translations'] ?? [];
        unset($data['translations']);

        $data['ulid'] = (string) Str::ulid();
        $data['status'] ??= 'draft';

        return $data;
    }

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Object_ $record */
        $record = parent::handleRecordCreation($data);

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn ($value): bool => $value !== null && $value !== '');

            if ($fields === []) {
                continue;
            }

            $fields['slug'] ??= Str::slug(($fields['name'] ?? (string) $record->id).'-'.$record->id);

            $record->translations()->create(['locale' => $locale, ...$fields]);
        }

        return $record;
    }

    private function assertWithinScope(int $countryId, int $categoryId): void
    {
        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            throw ValidationException::withMessages(['data.country_id' => __('panel.objects.form.out_of_scope')]);
        }

        $constraint = app(ScopeAuthorizer::class)->constraintFor($actor, 'object.create');

        if ($constraint->isUnrestricted) {
            return;
        }

        if ($constraint->reachesNothing()) {
            throw ValidationException::withMessages(['data.country_id' => __('panel.objects.form.out_of_scope')]);
        }

        $inCountry = $constraint->countryIds === [] || in_array($countryId, $constraint->countryIds, true);
        $inCategory = $constraint->categoryIds === [] || in_array($categoryId, $constraint->categoryIds, true);

        // A grant is bounded to exactly one axis at a time in this schema
        // (country XOR category XOR territory), so an empty list on one axis
        // means that axis carries no restriction for this actor — checked
        // against the other axis instead of failing closed on an axis the
        // actor's grant never spoke to.
        if ($constraint->countryIds !== [] && ! $inCountry) {
            throw ValidationException::withMessages(['data.country_id' => __('panel.objects.form.out_of_scope')]);
        }

        if ($constraint->categoryIds !== [] && ! $inCategory) {
            throw ValidationException::withMessages(['data.object_type_id' => __('panel.objects.form.out_of_scope')]);
        }
    }
}
