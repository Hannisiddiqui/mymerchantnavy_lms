<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'admin_name',
        'admin_email',
        'action_type',
        'model_type',
        'model_id',
        'title',
        'proposed_changes',
        'status',
        'reviewed_by_admin_id',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'proposed_changes' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }
}
