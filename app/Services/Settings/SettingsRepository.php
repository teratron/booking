<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Exceptions\CriticalSettingException;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reads and writes runtime portal configuration.
 *
 * Settings are read on nearly every request — the date format, the ordering
 * tiebreaker, the moderation mode — so reads are cached and the cache is
 * dropped on write rather than expiring on a timer: a setting an administrator
 * changed but that takes effect in five minutes is a setting they will change
 * again, twice.
 *
 * Every write is journalled with its previous value, and writes to a critical
 * setting are refused here rather than hidden in the screen.
 */
final class SettingsRepository
{
    private const CACHE_KEY = 'portal.settings';

    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly AuditJournal $journal,
    ) {}

    /**
     * The declared value of $key, cast to its declared type, falling back to
     * its declared default when no administrator has set it.
     *
     * @throws InvalidArgumentException when $key is not a declared setting — an
     *                                  undeclared key is a typo, and returning null for it would
     *                                  silently substitute the typo's absence for a real value
     */
    public function get(string $key): mixed
    {
        $definition = $this->registry->get($key);

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }

        return $definition->cast($this->stored()[$key] ?? null);
    }

    /** @return array<string, mixed> every declared setting, resolved */
    public function all(): array
    {
        $stored = $this->stored();
        $resolved = [];

        foreach ($this->registry->all() as $key => $definition) {
            $resolved[$key] = $definition->cast($stored[$key] ?? null);
        }

        return $resolved;
    }

    /**
     * Writes $value to $key on behalf of $actor.
     *
     * @throws InvalidArgumentException when $key is not declared
     * @throws CriticalSettingException when the setting is critical and $actor is not the chief administrator
     */
    public function set(string $key, mixed $value, ?User $actor = null): void
    {
        $definition = $this->registry->get($key);

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }

        $actor ??= auth()->user();

        if ($definition->isCritical && ! $this->mayWriteCritical($actor)) {
            throw CriticalSettingException::forKey($key);
        }

        $previous = $this->get($key);
        $next = $definition->cast($value);

        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($next), 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget(self::CACHE_KEY);

        if ($previous === $next) {
            return;
        }

        if ($actor instanceof User) {
            $this->journal->record(
                event: 'setting_changed',
                target: $actor,
                oldValues: [$key => $definition->isCritical ? '[redacted]' : $previous],
                newValues: [$key => $definition->isCritical ? '[redacted]' : $next],
                actor: $actor,
                tags: ['settings'],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values, ?User $actor = null): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $actor);
        }
    }

    private function mayWriteCritical(mixed $actor): bool
    {
        return $actor instanceof User && $actor->can('settings_management') && $actor->hasRole('chief_administrator');
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        /** @var array<string, mixed> $stored */
        $stored = Cache::rememberForever(self::CACHE_KEY, static function (): array {
            $rows = [];

            foreach (DB::table('settings')->get(['key', 'value']) as $row) {
                $rows[$row->key] = json_decode((string) $row->value, true);
            }

            return $rows;
        });

        return $stored;
    }
}
