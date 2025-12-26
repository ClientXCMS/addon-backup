<?php

namespace App\Addons\Backup;

use App\Addons\Backup\Console\Commands\RunBackupCommand;
use App\Addons\Backup\Http\Controllers\Admin\BackupController;
use App\Core\Admin\Dashboard\AdminCardWidget;
use App\Extensions\BaseAddonServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;

class BackupServiceProvider extends BaseAddonServiceProvider
{
    protected string $uuid = 'backup';

    public function register()
    {
        if (app()->runningInConsole()) {
            $this->registerSchedule();
            $this->commands([
                RunBackupCommand::class
            ]);
        }
    }

    public function schedule(Schedule $schedule): void
    {
        $schedule->command('backup:run')->hourly()->name('backup:run')->sendOutputTo(storage_path('logs/backup_run.log'))->evenInMaintenanceMode();
    }

    public function boot()
    {
        $this->loadRoutes();
        $this->loadTranslations();
        $this->loadViews();
        $this->loadMigrations();
        $this->registerSettingsCard();
        $this->registerWidget();
    }

    protected function configureDefaultDisk()
    {
        config(['filesystems.disks.backups' => [
            'driver' => 'local',
            'root' => storage_path('backups'),
            'throw' => false,
        ]]);
    }

    public function loadRoutes(): void
    {
        Route::middleware(['web', 'admin'])
            ->prefix(admin_prefix('backups'))
            ->name('admin.backups.')
            ->group($this->addonPath('routes/admin.php'));
    }

    protected function registerWidget(): void
    {
        $this->app['extension']->addAdminCardsWidget(new AdminCardWidget('backup_health', function () {
            return view('backup_admin::dashboard.cards.health');
        }, 'admin.manage_backups', 1, 'support'));
    }

    protected function registerSettingsCard(): void
    {
        if (! $this->app->bound('settings')) {
            return;
        }

        $settings = $this->app['settings'];
        $settings->setDefaultValue('backup_last_run', null);

        $settings->addCardItem(
            'security',
            'backup',
            'backup::lang.index.title',
            'backup::lang.index.description',
            'bi bi-cloud-arrow-down',
            [BackupController::class, 'index'],
            'admin.manage_backups',
        );
        $settings->addCardItem(
            'security',
            'backup_providers',
            'backup::providers.title',
            'backup::providers.subheading',
            'bi bi-hdd-stack',
            route('admin.backups.providers.index'),
            'admin.manage_backups',
        );
    }
}
