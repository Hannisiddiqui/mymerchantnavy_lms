<?php

namespace App\Models;

use App\Models\Concerns\SanitizesHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomPage extends Model
{
    use SanitizesHtml;

    /** HTML columns that are cleaned on save. */
    protected array $htmlFields = ['content'];

    /** Blocks hold HTML in "body" and web addresses in the *url fields: both are cleaned. */
    /** Pages that are published and inside their schedule. */
    public function scopeLive($query)
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('unpublish_at')->orWhere('unpublish_at', '>', now()));
    }

    protected function sanitizeExtra(): void
    {
        $this->cover_image_url = $this->cover_image_url ? (\App\Support\HtmlSanitizer::url($this->cover_image_url, ['http', 'https']) ?: null) : null;
        $clean = function (array $blocks) use (&$clean): array {
            foreach ($blocks as $key => $value) {
                if (is_array($value)) {
                    $blocks[$key] = $clean($value);
                } elseif (is_string($value) && $key === 'body') {
                    $blocks[$key] = \App\Support\HtmlSanitizer::clean($value);
                } elseif (is_string($value) && is_string($key) && preg_match('/(^|_)(url|href|link|src)$/i', $key)) {
                    $blocks[$key] = \App\Support\HtmlSanitizer::url($value, ['http', 'https', 'mailto', 'tel']) ?? '';
                }
            }

            return $blocks;
        };
        if (is_array($this->blocks)) {
            $this->blocks = $clean($this->blocks);
        }
    }

    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'icon',
        'badge_text',
        'content',
        'blocks',
        'meta_description',
        'is_published',
        'show_in_sidebar',
        'sort_order',
        'theme',
        'width',
        'show_header',
        'cover_image_url',
        'access',
        'access_course_ids',
        'publish_at',
        'unpublish_at',
    ];

    protected $casts = [
        'blocks' => 'array',
        'is_published' => 'boolean',
        'show_in_sidebar' => 'boolean',
        'sort_order' => 'integer',
        'show_header' => 'boolean',
        'access_course_ids' => 'array',
        'publish_at' => 'datetime',
        'unpublish_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomPage $page) {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
        });
    }
}

