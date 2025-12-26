<?php

use App\Addons\Backup\Http\Controllers\Admin\BackupController;
use App\Addons\Backup\Http\Controllers\Admin\BackupProviderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BackupController::class, 'index'])->name('index');
Route::post('/', [BackupController::class, 'run'])->name('run');

Route::resource('providers', BackupProviderController::class);

Route::get('/{identifier}/download', [BackupController::class, 'download'])->name('download');
Route::post('/{identifier}/restore/db', [BackupController::class, 'restoreDatabase'])->name('restore.db');
Route::post('/{identifier}/restore/storage', [BackupController::class, 'restoreStorage'])->name('restore.storage');
Route::post('/{identifier}/restore/all', [BackupController::class, 'restoreAll'])->name('restore.all');
Route::delete('/{identifier}', [BackupController::class, 'destroy'])->name('destroy');
