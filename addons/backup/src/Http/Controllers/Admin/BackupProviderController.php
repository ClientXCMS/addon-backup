<?php

namespace App\Addons\Backup\Http\Controllers\Admin;

use App\Addons\Backup\Models\BackupProvider;
use App\Http\Controllers\Admin\AbstractCrudController;
use Illuminate\Http\Request;

class BackupProviderController extends AbstractCrudController
{
    protected string $model = BackupProvider::class;
    protected string $viewPath = 'backup_admin::providers';
    protected string $translatePrefix = 'backup::providers';
    protected string $searchField = 'name';
    protected ?string $managedPermission = 'admin.manage_backups';
    protected string $routePath = 'admin.backups.providers';

    protected function getSearchFields()
    {
        return [
            'name' => __('global.name'),
            'driver' => __('backup::providers.driver'),
        ];
    }

    protected function getIndexParams($items, string $translatePrefix)
    {
        $data = parent::getIndexParams($items, $translatePrefix);

        $card = app('settings')->getCards()->firstWhere('uuid', 'security');
        if (! $card) {
            abort(404);
        }
        $item = $card->items->firstWhere('uuid', 'backup_providers');
        $data['current_card'] = $card;
        $data['current_item'] = $item;
        return $data;
    }

    protected function getCreateParams()
    {
        $data = parent::getCreateParams();
        $data['drivers'] = $this->getDrivers();

        $card = app('settings')->getCards()->firstWhere('uuid', 'security');
        if (! $card) {
            abort(404);
        }
        $item = $card->items->firstWhere('uuid', 'backup_providers');
        $data['current_card'] = $card;
        $data['current_item'] = $item;
        return $data;
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'driver' => 'required|string|in:local,ftp,sftp,s3,google',
            'configuration' => 'required|array',
            'enabled' => 'nullable',
            'frequency_hours' => 'required|integer|min:1',
            'retention_days' => 'required|integer|min:0',
        ]);

        $validated['enabled'] = $request->has('enabled');
        
        $provider = BackupProvider::create($validated);
        return $this->storeRedirect($provider);
    }

    public function show(BackupProvider $provider)
    {
        $params = [
            'item' => $provider,
            'drivers' => $this->getDrivers(),
            'logs' => $provider->logs()->limit(20)->get(),
        ];
        $card = app('settings')->getCards()->firstWhere('uuid', 'security');
        if (! $card) {
            abort(404);
        }
        $item = $card->items->firstWhere('uuid', 'backup_providers');
        $params['current_card'] = $card;
        $params['current_item'] = $item;
        return $this->showView($params);
    }

    public function update(Request $request, BackupProvider $provider)
    {
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'driver' => 'required|string|in:local,ftp,sftp,s3,google',
            'configuration' => 'required|array',
            'enabled' => 'nullable',
            'frequency_hours' => 'required|integer|min:1',
            'retention_days' => 'required|integer|min:0',
        ]);

        $validated['enabled'] = $request->has('enabled');

        $provider->update($validated);
        return $this->updateRedirect($provider);
    }
    
    public function destroy(BackupProvider $provider)
    {
        $provider->delete();
        return $this->destroyRedirect($provider);
    }

    private function getDrivers()
    {
        return [
            'local' => 'Local',
            'ftp' => 'FTP',
            'sftp' => 'SFTP',
            's3' => 'S3',
            'google' => 'Google Drive',
        ];
    }
}
