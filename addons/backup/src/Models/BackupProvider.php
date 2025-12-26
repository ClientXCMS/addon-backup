<?php

namespace App\Addons\Backup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupProvider extends Model
{
    protected $fillable = [
        'name',
        'driver',
        'configuration',
        'enabled',
        'frequency_hours',
        'retention_days',
        'last_run_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'frequency_hours' => 'integer',
        'retention_days' => 'integer',
        'last_run_at' => 'datetime',
        'configuration' => 'encrypted:array',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(BackupLog::class, 'provider_id')->orderByDesc('created_at');
    }

    public function getSuccessRateAttribute(): ?float
    {
        $total = $this->logs()->whereIn('status', ['success', 'failed'])->count();
        if ($total === 0) {
            return null;
        }
        $success = $this->logs()->where('status', 'success')->count();
        return round(($success / $total) * 100, 1);
    }

    public function getLastErrorAttribute(): ?BackupLog
    {
        return $this->logs()->where('status', 'failed')->first();
    }
}
