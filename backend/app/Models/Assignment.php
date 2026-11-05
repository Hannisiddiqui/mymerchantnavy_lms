<?php

namespace App\Models;

use App\Models\Concerns\SanitizesHtml;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use SanitizesHtml;

    /** HTML columns that are cleaned on save. */
    protected array $htmlFields = ['instructions'];

    protected $fillable = ['instructions', 'attachment_path', 'due_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
