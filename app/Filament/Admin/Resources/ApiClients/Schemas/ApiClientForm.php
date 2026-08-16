<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ApiClients\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ApiClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('panel.api_clients.form.name'))
                ->required()
                ->maxLength(255),

            TextInput::make('contact')
                ->label(__('panel.api_clients.form.contact'))
                ->maxLength(255),

            Toggle::make('is_active')
                ->label(__('panel.api_clients.form.active'))
                ->default(true),
        ]);
    }
}
