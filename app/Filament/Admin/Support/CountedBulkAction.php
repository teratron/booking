<?php

declare(strict_types=1);

namespace App\Filament\Admin\Support;

use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Override;

/**
 * A bulk action that cannot execute without a confirmation naming how many
 * records it will affect.
 *
 * The requirement is specific about the wording: "hide 87 objects in Odesa
 * region" rather than "are you sure?". The difference is not politeness. An
 * administrator who mis-selects a filter and then confirms a dialog that
 * states no number has been given no opportunity to notice, and bulk hiding is
 * one of the operations with no cheap undo.
 *
 * Making this a type rather than a convention means a resource author gets the
 * behaviour by choosing the class, and the phase's authorization test can
 * assert that every registered bulk action is one of these.
 */
class CountedBulkAction extends BulkAction
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->requiresConfirmation();

        $this->modalDescription(fn (Collection $records): string => __('panel.bulk.confirmation', [
            'count' => $records->count(),
        ]));

        $this->modalSubmitActionLabel(fn (Collection $records): string => __('panel.bulk.confirm_label', [
            'count' => $records->count(),
        ]));

        // A confirmed bulk run leaves the selection checked, which invites a
        // second accidental run over records the first one already changed.
        $this->deselectRecordsAfterCompletion();
    }
}
