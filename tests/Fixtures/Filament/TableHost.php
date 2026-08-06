<?php

declare(strict_types=1);

namespace Tests\Fixtures\Filament;

use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Livewire\Component;

/**
 * The minimum a table needs to exist: something that holds it. Nothing here is
 * asserted — it exists so the contract's table defaults can be read off a real
 * `Table` instance rather than inferred from the source.
 */
final class TableHost extends Component implements HasTable
{
    use InteractsWithTable;

    public function render(): string
    {
        return '<div></div>';
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }
}
