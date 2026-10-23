<?php

namespace App\Models;

use App\Enums\ImportMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** One uploaded file and the draft questions read from it. */
class QuestionImport extends Model
{
    protected $fillable = ['admin_id', 'test_id', 'method', 'original_name', 'file_path', 'status', 'progress', 'summary', 'error'];

    /** Deleting an import also removes the uploaded file from the server. */
    protected static function booted(): void
    {
        static::deleted(fn (self $import) => $import->file_path ? Storage::disk('local')->delete($import->file_path) : null);
    }

    protected function casts(): array
    {
        return ['method' => ImportMethod::class, 'summary' => 'array', 'progress' => 'integer'];
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(ImportQuestion::class)->orderBy('position');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function isParsing(): bool
    {
        return $this->status === 'parsing';
    }
}
