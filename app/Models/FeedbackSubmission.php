<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A raw message a visitor left through the shared feedback overlay,
 * captured from any public page. Not translatable — this is a visitor's
 * own words in whatever language they typed them, not portal content.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $message
 * @property string $page_url the page the visitor was on when they submitted
 * @property string $locale the interface language active at submission time
 */
final class FeedbackSubmission extends Model
{
    protected $guarded = ['id'];
}
