<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Services\Settings\SettingsRepository;

/**
 * Resolves the MapLibre GL JS style URL from the administrator-configured
 * tile provider and key. The public OSM tile servers are prohibited for
 * production use by the OSMF Tile Usage Policy, so no entry for them
 * exists in the template map below — an unrecognised or unset provider
 * falls back to MapTiler rather than ever producing an OSM tile URL.
 */
final class MapTileConfigResolver
{
    /** @var array<string, string> */
    private const array STYLE_URL_TEMPLATES = [
        'maptiler' => 'https://api.maptiler.com/maps/streets-v2/style.json?key={key}',
        'stadia' => 'https://tiles.stadiamaps.com/styles/alidade_smooth.json?api_key={key}',
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function styleUrl(): string
    {
        $provider = (string) $this->settings->get('integrations.map_tile_provider');
        $key = (string) $this->settings->get('integrations.map_tile_key');

        $template = self::STYLE_URL_TEMPLATES[$provider] ?? self::STYLE_URL_TEMPLATES['maptiler'];

        return str_replace('{key}', $key, $template);
    }
}
