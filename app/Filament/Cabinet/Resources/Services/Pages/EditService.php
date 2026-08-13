<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Services\Pages;

use App\Filament\Cabinet\Resources\Services\ServiceResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Every field on this form is one of `ServiceForm`'s own `->relationship()`
 * -bound checkbox lists, so there is nothing left for a custom
 * `handleRecordUpdate()` override to do here: Filament persists each one
 * during the form's own save lifecycle, strictly before this page's default
 * record-update step ever runs — see `ServiceForm`'s own docblock for why
 * that also makes every selection here structurally immediate regardless of
 * the object's publication state.
 */
class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;
}
