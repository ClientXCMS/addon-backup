<?php

namespace App\Addons\Backup\Http\Controllers\Admin;

use App\Addons\Backup\Contracts\BackupDestination;
use App\Addons\Backup\Exceptions\BackupException;
use App\Addons\Backup\Services\BackupService;
use App\Addons\Backup\Services\RestoreService;
use App\Http\Controllers\Controller;
use App\Models\Admin\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BackupController extends Controller
{
    protected string $permission = 'admin.manage_backups';

    public function __construct(
        private readonly BackupService $backupService,
        private readonly RestoreService $restoreService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAction();

        $providerId = $request->query('provider');
        $allProviders = \App\Addons\Backup\Models\BackupProvider::where('enabled', true)->get();
        $providers = $allProviders->pluck('name', 'id');

        // Collect backups from all providers or a specific one
        $backups = collect();
        $providersToScan = $providerId
            ? $allProviders->where('id', $providerId)
            : $allProviders;

        foreach ($providersToScan as $provider) {
            try {
                $this->backupService->forProvider($provider);
                $destination = $this->backupService->getDestination();
                $providerBackups = $destination->listBackups();
                // Add provider info to each backup
                foreach ($providerBackups as $backup) {
                    $backup->provider_id = $provider->id;
                    $backup->provider_name = $provider->name;
                    $backups->push($backup);
                }
            } catch (\Exception $e) {
                Log::warning("Could not list backups for provider {$provider->name}: " . $e->getMessage());
            }
        }

        // Sort by date descending
        $backups = $backups->sortByDesc('createdAt')->values();

        // Get recent failed backups (last 24 hours)
        $recentErrors = \App\Addons\Backup\Models\BackupLog::with('provider')
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $currentProvider = $providerId;

        $current_card = app('settings')->getCards()->firstWhere('uuid', 'security');
        if (! $current_card) {
            abort(404);
        }
        $current_item = $current_card->items->firstWhere('uuid', 'backup');

        return view('backup_admin::index', compact('backups', 'providers', 'recentErrors', 'currentProvider', 'current_card',  'current_item'));
    }

    public function run(Request $request): RedirectResponse
    {
        $this->authorizeAction();
        $data = $request->validate([
            'include_database' => ['nullable'],
            'include_storage' => ['nullable'],
            'provider_id' => ['required', 'exists:backup_providers,id'],
        ]);

        $includeDatabase = $request->has('include_database');
        $includeStorage = $request->has('include_storage');
        if (! $request->has('include_database') && ! $request->has('include_storage')) {
            $includeDatabase = true;
            $includeStorage = true;
        }

        if (! $includeDatabase && ! $includeStorage) {
            return back()->with('error', __('backup::lang.messages.no_selection'));
        }

        $provider = \App\Addons\Backup\Models\BackupProvider::findOrFail($request->input('provider_id'));

        $log = \App\Addons\Backup\Models\BackupLog::create([
            'provider_id' => $provider->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $this->backupService->forProvider($provider);
            $result = $this->backupService->runBackup($includeDatabase, $includeStorage, $provider->retention_days, true);
            $log->update([
                'status' => 'success',
                'identifier' => $result->identifier,
                'type' => $result->type,
                'completed_at' => now(),
            ]);

            return back()->with('success', __('backup::lang.messages.run_success', ['id' => $result->identifier]));
        } catch (BackupException | \Exception $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            Log::error('Backup failed', ['exception' => $exception]);
            return back()->with('error', $exception->getMessage());
        }
    }

    public function download(string $identifier)
    {
        $this->authorizeAction();
        $descriptor = $this->backupService->getBackup($identifier);
        if ($descriptor === null) {
            abort(404);
        }

        $tempFile = storage_path('framework/backup/downloads/' . Str::uuid() . '.zip');
        File::ensureDirectoryExists(dirname($tempFile));

        try {
            $this->backupService->getDestination()->downloadBackup($identifier, $tempFile);
            return response()->download($tempFile, $descriptor->filename)->deleteFileAfterSend(true);
        } catch (BackupException $exception) {
            File::delete($tempFile);
            Log::error('Backup download failed', ['exception' => $exception]);
            return back()->with('error', $exception->getMessage());
        }
    }

    public function restoreDatabase(string $identifier): RedirectResponse
    {
        $this->authorizeAction();

        try {
            $this->restoreService->restoreDatabaseFromBackup($identifier);
            return back()->with('success', __('backup::lang.messages.restore_db_success'));
        } catch (BackupException $exception) {
            Log::error('Database restore failed', ['exception' => $exception]);
            return back()->with('error', $exception->getMessage());
        }
    }

    public function restoreStorage(string $identifier): RedirectResponse
    {
        $this->authorizeAction();

        try {
            $this->restoreService->restoreStorageFromBackup($identifier);
            return back()->with('success', __('backup::lang.messages.restore_storage_success'));
        } catch (BackupException $exception) {
            Log::error('Storage restore failed', ['exception' => $exception]);
            return back()->with('error', $exception->getMessage());
        }
    }

    public function restoreAll(string $identifier): RedirectResponse
    {
        $this->authorizeAction();

        try {
            $this->restoreService->restoreAllFromBackup($identifier);
            return back()->with('success', __('backup::lang.messages.restore_all_success'));
        } catch (BackupException $exception) {
            Log::error('Backup restore failed', ['exception' => $exception]);
            return back()->with('error', $exception->getMessage());
        }
    }

    public function destroy(string $identifier): RedirectResponse
    {
        $this->authorizeAction();
        try {
            $descriptor = $this->backupService->getBackup($identifier);
            if ($descriptor === null) {
                return back()->with('error', 'Backup not found');
            }

            $this->backupService->getDestination()->deleteBackup($identifier);

            \App\Addons\Backup\Models\BackupLog::where('identifier', $identifier)->delete();

            return back()->with('success', __('backup::lang.messages.delete_success'));
        } catch (BackupException $exception) {
            Log::error('Backup deletion failed', ['exception' => $exception]);
            return back()->with('error', $exception->getMessage());
        }
    }

    protected function authorizeAction(): void
    {
        staff_aborts_permission($this->permission);
    }
}
