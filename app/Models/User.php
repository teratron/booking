<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Policies\UserPolicy;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
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
