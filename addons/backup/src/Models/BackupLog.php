<?php

namespace App\Addons\Backup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupLog extends Model
{
    protected $fillable = [
        'provider_id',
        'identifier',
        'status',
        'type',
        'size',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(BackupProvider::class, 'provider_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        return $this->completed_at->diffInSeconds($this->started_at);
    }
}
