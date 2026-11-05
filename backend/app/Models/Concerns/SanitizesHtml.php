<?php

namespace App\Models\Concerns;

use App\Support\HtmlSanitizer;

/** Cleans the HTML columns listed in $htmlFields every time the model is saved (see HtmlSanitizer). */
trait SanitizesHtml
{
    public static function bootSanitizesHtml(): void
    {
        static::saving(function ($model) {
            foreach ($model->htmlFields ?? [] as $field) {
                if (is_string($model->{$field}) && $model->isDirty($field)) {
                    $model->{$field} = HtmlSanitizer::clean($model->{$field});
                }
            }
            if (method_exists($model, 'sanitizeExtra')) {
                $model->sanitizeExtra();
            }
        });
    }
}
