<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Scopes\ModerationScope;
use App\Policies\UserPolicy;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Override;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property-read ?TwoFactorSecret $twoFactorSecret
 */
// blocked_at / blocked_by are deliberately excluded from mass assignment:
// account status is only ever changed through OwnerAccountService's
// block()/restore(), never through a form field, mirroring the accountability
// columns already treated this way elsewhere in this schema (deleted_by,
// availability_changed_by).
#[Fillable(['name', 'email', 'password', 'company', 'phone', 'country_id'])]
#[Hidden(['password', 'remember_token'])]
#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'blocked_at' => 'datetime',
        ];
    }

    /** @return HasOne<TwoFactorSecret, $this> */
    public function twoFactorSecret(): HasOne
    {
        return $this->hasOne(TwoFactorSecret::class);
    }

    /** @return HasMany<Object_, $this> */
    public function objects(): HasMany
    {
        return $this->hasMany(Object_::class, 'owner_id');
    }

    /**
     * Objects this account manages as limited staff — the inverse of
     * {@see Object_::staff()} — distinct from `objects()`, the objects it
     * owns outright. Both feed the owner cabinet's tenant list; only
     * ownership carries the portal-wide `object_owner` permission set, so
     * the pivot's own `permissions` column is what a staff member's row
     * grants, not this relation.
     *
     * @return BelongsToMany<Object_, $this>
     */
    public function staffedObjects(): BelongsToMany
    {
        return $this->belongsToMany(Object_::class, 'object_user', 'user_id', 'object_id')->withPivot('permissions');
    }

    /** @return HasMany<NotificationPreference, $this> */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * The owner-management screen's own axis: unlike an object, an account
     * carries only a country, no territory or category of its own.
     *
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Panel admission is a permission, not a role list. Roles are data an
     * administrator may add, rename, or restructure at any time; a check
     * written against role names would silently admit or exclude accounts the
     * moment someone does. Both panels therefore gate on a permission whose
     * only purpose is naming this gate.
     *
     * A blocked account is refused before that permission check runs, ahead
     * of the panel branch, so the rule applies uniformly to staff and
     * owners alike. Filament re-evaluates this method on every panel
     * request, not only at login, which is what keeps a just-blocked
     * account out on its very next navigation without any extra
     * session-invalidation machinery.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->blocked_at !== null) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->can('admin_panel_access'),
            'cabinet' => $this->can('cabinet_access'),
            default => false,
        };
    }

    /**
     * The owner cabinet's Filament tenant is an {@see Object_}, not a team or
     * organization — the panel switches between the objects this account
     * manages, owned or staffed, rather than between separate businesses.
     *
     * Queried explicitly through the relation methods, not the magic `objects`/
     * `staffedObjects` properties: strict mode (`Model::shouldBeStrict()`,
     * enabled outside production) throws on an unloaded relation accessed via
     * a property, and an authenticated `User` instance never arrives with
     * these eager-loaded — a fresh session-guard lookup has no reason to.
     *
     * Both queries strip `ModerationScope`: it exists to keep an unapproved
     * object off public pages, and would otherwise make a draft, rejected, or
     * revision-requested object — the exact state an owner most needs to
     * reach to act on it — invisible in their own tenant switcher.
     *
     * @return Collection<int, Object_>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->objects()->withoutGlobalScope(ModerationScope::class)->get()
            ->concat($this->staffedObjects()->withoutGlobalScope(ModerationScope::class)->get());
    }

    /**
     * The same ownership-or-staff-membership question `getTenants()`
     * answers for the whole switcher, asked about one object — a direct
     * query rather than routed through `getTenants()`, since this is called
     * per tenant-resolution request (Filament's `IdentifyTenant` middleware)
     * and does not need the full collection either call builds. Strips
     * `ModerationScope` for the same reason `getTenants()` does.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Object_
            && ((int) $tenant->owner_id === $this->id
                || $this->staffedObjects()->withoutGlobalScope(ModerationScope::class)->whereKey($tenant->id)->exists());
    }

    /**
     * An owner or staff member with exactly one object lands in it directly
     * rather than on an empty switcher — the common case, since most owners
     * manage a single property.
     */
    public function getDefaultTenant(Panel $panel): ?Object_
    {
        return $this->getTenants($panel)->first();
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->twoFactorSecret?->secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        if ($secret === null) {
            $this->twoFactorSecret()->delete();
            $this->unsetRelation('twoFactorSecret');

            return;
        }

        $this->twoFactorSecret()->updateOrCreate([], [
            'secret' => $secret,
            'confirmed_at' => now(),
        ]);

        $this->unsetRelation('twoFactorSecret');
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return ?array<string> */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->twoFactorSecret?->recovery_codes;
    }

    /** @param  ?array<string>  $codes */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->twoFactorSecret()->updateOrCreate([], ['recovery_codes' => $codes]);

        $this->unsetRelation('twoFactorSecret');
    }
}
