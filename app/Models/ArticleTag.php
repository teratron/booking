<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\ArticleTagPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Override;

/**
 * A short, largely language-neutral label attached to articles. Unlike
 * `ArticleCategory`, it carries no translations sibling table — `name` is a
 * plain column here, per the source inventory's own asymmetry between the
 * two registries. Administrator-managed: a new tag requires a new row,
 * never a code change.
 */
#[UsePolicy(ArticleTagPolicy::class)]
class ArticleTag extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsToMany<Article, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_tag', 'article_tag_id', 'article_id');
    }
}
