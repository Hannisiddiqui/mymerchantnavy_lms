<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomPage extends Model
{
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
    ];

    protected $casts = [
        'blocks' => 'array',
        'is_published' => 'boolean',
        'show_in_sidebar' => 'boolean',
        'sort_order' => 'integer',
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
