<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use App\Policies\BannerPolicy;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * An advertising creative slotted into one inventory position. Started
 * minimal for analytics to record `banner_impression` / `banner_click`
 * events against a real subject model; extended here with the targeting,
 * creative upload, and translation wiring the back-office resource needs.
 * Selection — which eligible banner actually serves for a given request —
 * belongs to a later, separate concern; this model only stores what a
 * banner is and where it may appear.
 *
 * @property int $id
 * @property string $name internal, administrator-facing only — not translated
 * @property-read ?string $link_text virtual, proxied through the active translation
 */
#[UsePolicy(BannerPolicy::class)]
final class Banner extends Model implements HasMedia, TranslatableContract
{
    use InteractsWithMedia;
    use SoftDeletes;
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = null;

    /** @var list<string> */
    public array $translatedAttributes = ['link_text'];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    /**
     * Desktop and mobile creatives are independent uploads — one may be
     * replaced without touching the other — so each gets its own single-file
     * collection rather than sharing one collection distinguished by a
     * custom property.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('desktop_creative')->singleFile();
        $this->addMediaCollection('mobile_creative')->singleFile();
    }

    /**
     * @return BelongsTo<BannerSlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(BannerSlot::class, 'banner_slot_id');
    }

    /**
     * Territories this banner is eligible on. No rows means untargeted —
     * eligible everywhere — not "eligible nowhere"; that reading, and the
     * ancestor-to-descendant subtree walk it implies, belongs to the
     * selection service that consumes this relation, not to the model.
     *
     * `banner_targets` inverts the column shape a "taggable" pivot usually
     * has: `banner_id` is the plain foreign key naming this model's own row,
     * while `target_type`/`target_id` are the polymorphic pair naming which
     * concrete model (Territory, ObjectType, or Language) a row targets.
     * `morphedByMany()` is the call whose defaults match that shape —
     * `$this->getForeignKey()` resolves to `banner_id`, and it writes the
     * *related* model's class into `target_type`, which is what a stored row
     * must distinguish between the three dimensions.
     *
     * @return MorphToMany<Territory, $this>
     */
    public function territories(): MorphToMany
    {
        return $this->morphedByMany(Territory::class, 'target', 'banner_targets');
    }

    /**
     * Object categories this banner is eligible on. No rows means
     * untargeted — every category.
     *
     * @return MorphToMany<ObjectType, $this>
     */
    public function categories(): MorphToMany
    {
        return $this->morphedByMany(ObjectType::class, 'target', 'banner_targets');
    }

    /**
     * Interface languages this banner is eligible for. No rows means
     * untargeted — every language. Named `targetLanguages()` rather than
     * `languages()` to read unambiguously as a targeting dimension, distinct
     * from a `link_text` translation's own locale.
     *
     * @return MorphToMany<Language, $this>
     */
    public function targetLanguages(): MorphToMany
    {
        return $this->morphedByMany(Language::class, 'target', 'banner_targets');
    }
}
