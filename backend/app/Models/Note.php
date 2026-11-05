<?php

namespace App\Models;

use App\Models\Concerns\SanitizesHtml;
use Illuminate\Database\Eloquent\Model;

/** Text notes (HTML + LaTeX). */
class Note extends Model
{
    use SanitizesHtml;

    /** HTML columns that are cleaned on save. */
    protected array $htmlFields = ['body'];

    protected $fillable = ['body'];
}
