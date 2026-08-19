<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

use Illuminate\Support\Str;

/**
 * The identity fields of one incoming import row, extracted for
 * {@see DuplicateDetectionService} to compare against the existing catalog.
 *
 * `address`, `latitude`, and `longitude` are read through the operator's own
 * column mapping — the same registry-driven map the objects importer itself
 * reads — because those are real columns on `objects`. `name`, `phone`, and
 * `website` are not: the "objects" kind's own declared column set names only
 * `objects`' own table columns (name lives on `object_translations`, phone
 * and website on `contact_channels`, each its own registered kind), so the
 * mapping step never offers them a slot to be mapped into. They are read
 * directly from the uploaded file's own headers instead, via a short list of
 * header names an operator's spreadsheet plausibly already uses.
 */
final readonly class NewObjectSignals
{
    public function __construct(
        public ?string $name = null,
        public ?string $phone = null,
        public ?string $website = null,
        public ?string $address = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}

    public function hasAnySignal(): bool
    {
        return filled($this->name)
            || filled($this->phone)
            || filled($this->website)
            || filled($this->address)
            || ($this->latitude !== null && $this->longitude !== null);
    }

    /**
     * @param  array<string, mixed>  $row  raw row keyed by the uploaded file's own headers
     * @param  array<string, string|null>  $columnMap  registry column name => uploaded header name
     */
    public static function fromRow(array $row, array $columnMap): self
    {
        return new self(
            name: self::firstAliasValue($row, ['name', 'object_name', 'title']),
            phone: self::firstAliasValue($row, ['phone', 'phone_number', 'telephone', 'tel']),
            website: self::firstAliasValue($row, ['website', 'site', 'url', 'web']),
            address: self::mappedValue($row, $columnMap, 'address'),
            latitude: self::toFloat(self::mappedValue($row, $columnMap, 'latitude')),
            longitude: self::toFloat(self::mappedValue($row, $columnMap, 'longitude')),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string|null>  $columnMap
     */
    private static function mappedValue(array $row, array $columnMap, string $column): ?string
    {
        $header = $columnMap[$column] ?? null;

        if ($header === null || ! array_key_exists($header, $row)) {
            return null;
        }

        $value = $row[$header];

        return $value === null ? null : trim((string) $value);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $aliases
     */
    private static function firstAliasValue(array $row, array $aliases): ?string
    {
        $normalized = [];

        foreach ($row as $header => $value) {
            $normalized[Str::of((string) $header)->lower()->snake()->toString()] = $value;
        }

        foreach ($aliases as $alias) {
            $value = $normalized[$alias] ?? null;

            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private static function toFloat(?string $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
