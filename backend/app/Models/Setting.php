<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting.{$key}", 300, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            return ($setting !== null && $setting->value !== null && $setting->value !== '') ? $setting->value : $default;
        });
    }

    public static function set(string $key, mixed $value, ?string $description = null): static
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                ...($description !== null ? ['description' => $description] : []),
            ]
        );

        Cache::forget("setting.{$key}");

        return $setting;
    }
}
