<?php

namespace App\Addons\Backup\Console\Commands;

use App\Addons\Backup\Models\BackupLog;
use App\Addons\Backup\Models\BackupProvider;
use App\Addons\Backup\Services\BackupService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunBackupCommand extends Command
{
    protected $signature = 'backup:run {--force : Run even if the interval has not elapsed} {--provider= : Run only for a specific provider ID}';

    protected $description = 'Run the configured backup routine for all enabled providers.';

    public function handle(): int
    {
        $this->info('Running backup:run at ' . now()->format('Y-m-d H:i:s'));
        $providerId = $this->option('provider');
        $query = BackupProvider::where('enabled', true);

        if ($providerId) {
            $query->where('id', $providerId);
        }

        $providers = $query->get();

        if ($providers->isEmpty()) {
            $this->info('No enabled backup providers configured.');
            return self::SUCCESS;
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($providers as $provider) {
            if ($this->processProvider($provider)) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("Backup run completed: {$successCount} successful, {$errorCount} failed.");

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function processProvider(BackupProvider $provider): bool
    {
        $this->line("Processing provider: {$provider->name} ({$provider->driver})");

        if (!$this->option('force') && !$this->shouldRunNow($provider)) {
            $nextRun = Carbon::parse($provider->last_run_at)->addHours($provider->frequency_hours);
            $this->line("  Skipping: next run scheduled at {$nextRun->format('Y-m-d H:i')}");
            return true;
        }

        // Create log entry
        $log = BackupLog::create([
            'provider_id' => $provider->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            // Create service and configure for this provider
            $backupService = new BackupService();
            $backupService->forProvider($provider);

            // Run the backup with provider's retention setting
            $result = $backupService->runBackup(true, true, $provider->retention_days, false);

            // Update log with success
            $log->update([
                'status' => 'success',
                'identifier' => $result->identifier,
                'type' => $result->type,
                'completed_at' => now(),
            ]);

            // Update last run timestamp
            $provider->update(['last_run_at' => now()]);

            $this->info("  ✓ Backup created: {$result->identifier}");

            return true;
        } catch (\Throwable $e) {
            // Update log with failure
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            Log::error("Backup failed for provider {$provider->name}: " . $e->getMessage(), [
                'provider_id' => $provider->id,
                'exception' => $e,
            ]);
            $this->error("  ✗ Backup failed: {$e->getMessage()}");
            return false;
        }
    }

    protected function shouldRunNow(BackupProvider $provider): bool
    {
        $frequency = max((int) $provider->frequency_hours, 1);
        $lastRun = $provider->last_run_at;

        if (!$lastRun) {
            return true;
        }

        return Carbon::parse($lastRun)->addHours($frequency)->isPast();
    }
}
