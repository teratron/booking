<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use App\Policies\ErrorPagePolicy;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Administrator-editable copy for one HTTP error response — currently only
 * the 404 the public shell renders for an unresolved route. Title and body
 * are translated per active language; a status code with no row here falls
 * back to the portal's own static copy, so every error response still
 * renders correctly before an administrator has ever touched this table.
 *
 * @property-read ?string $title virtual, proxied through the active translation
 * @property-read ?string $body virtual, proxied through the active translation
 */
#[UsePolicy(ErrorPagePolicy::class)]
final class ErrorPage extends Model implements TranslatableContract
{
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = ErrorPageTranslation::class;

    /** @var list<string> */
    public array $translatedAttributes = ['title', 'body'];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['status_code' => 'integer'];
    }
}
