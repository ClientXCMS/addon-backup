<?php
/*
 * This file is part of the CLIENTXCMS project.
 * It is the property of the CLIENTXCMS association.
 */
?>
@extends('admin/settings/sidebar')

@section('title', __('backup::lang.index.title'))

@section('setting')
<div class="container mx-auto">
    <div class="flex flex-col gap-6">
        @if(isset($recentErrors) && count($recentErrors) > 0)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 dark:bg-red-900/20 dark:border-red-800">
            <div class="flex items-start gap-3">
                <i class="bi bi-exclamation-triangle-fill text-red-600 dark:text-red-400 text-xl"></i>
                <div class="flex-1">
                    <h4 class="text-sm font-semibold text-red-800 dark:text-red-200">{{ __('backup::lang.recent_errors') }}</h4>
                    <ul class="mt-2 space-y-1">
                        @foreach($recentErrors as $error)
                        <li class="text-sm text-red-700 dark:text-red-300">
                            <span class="font-medium">{{ $error->provider->name ?? 'Unknown' }}</span> -
                            {{ $error->created_at->format('d/m H:i') }}:
                            {{ Str::limit($error->error_message, 80) }}
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        @endif
        <div class="card">
            <div class="card-heading flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ __('backup::lang.index.title') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('backup::lang.index.description') }}</p>
                </div>
                <div>
                    <select onchange="window.location.href = this.value ? '?provider=' + this.value : '{{ route('admin.backups.index') }}'" class="input-text text-sm">
                        <option value="">{{ __('backup::lang.index.all_providers') }}</option>
                        @foreach($providers as $id => $name)
                        <option value="{{ $id }}" {{ ($currentProvider ?? null) == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="border rounded-lg overflow-x-auto dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase">{{ __('backup::lang.index.table.created_at') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase">{{ __('backup::lang.index.table.type') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase">{{ __('backup::lang.index.table.size') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase">{{ __('backup::lang.index.table.provider') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase">{{ __('backup::lang.index.table.disk') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase">{{ __('backup::lang.index.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($backups as $backup)
                        <tr class="bg-white dark:bg-slate-900">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200">
                                {{ $backup->createdAt->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200">
                                {{ __('backup::lang.types.'.$backup->type) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200">
                                {{ format_bytes($backup->size) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200">
                                {{ $backup->provider_name ?? $backup->provider ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200">
                                {{ $backup->disk }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.backups.download', $backup->identifier) }}" class="py-1 px-2 inline-flex justify-center items-center gap-2 rounded-lg border font-medium bg-white text-gray-700 shadow-sm align-middle hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white focus:ring-blue-600 transition-all text-sm dark:bg-slate-900 dark:hover:bg-slate-800 dark:border-gray-700 dark:text-gray-400 dark:hover:text-white dark:focus:ring-offset-gray-800">
                                        <i class="bi bi-download"></i>
                                        {{ __('backup::lang.index.download') }}
                                    </a>

                                    <div class="hs-dropdown relative inline-flex">
                                        <button type="button" class="hs-dropdown-toggle py-1 px-2 inline-flex justify-center items-center gap-2 rounded-lg border font-medium bg-white text-gray-700 shadow-sm align-middle hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white focus:ring-blue-600 transition-all text-sm dark:bg-slate-900 dark:hover:bg-slate-800 dark:border-gray-700 dark:text-gray-400 dark:hover:text-white dark:focus:ring-offset-gray-800">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                            {{ __('backup::lang.index.restore') }}
                                            <svg class="hs-dropdown-open:rotate-180 w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="m6 9 6 6 6-6" />
                                            </svg>
                                        </button>
                                        <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-[12rem] bg-white shadow-md rounded-lg dark:bg-gray-800 dark:border dark:border-gray-700 dark:divide-gray-700 after:h-4 after:absolute after:-bottom-4 after:start-0 after:w-full before:h-4 before:absolute before:-top-4 before:start-0 before:w-full z-50">
                                            <form method="POST" action="{{ route('admin.backups.restore.db', $backup->identifier) }}" class="confirmation-popup" data-confirm-button-text="{{ __('backup::lang.index.restore') }}" data-text="{{ __('backup::lang.confirm_restore') }}" data-confirm-button-color="#4F46E5">
                                                @csrf
                                                <button type="submit" class="w-full flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300 dark:focus:bg-gray-700">
                                                    <i class="bi bi-database"></i>
                                                    {{ __('backup::lang.index.restore_db') }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.backups.restore.storage', $backup->identifier) }}" class="confirmation-popup" data-confirm-button-text="{{ __('backup::lang.index.restore') }}" data-text="{{ __('backup::lang.confirm_restore') }}" data-confirm-button-color="#4F46E5">
                                                @csrf
                                                <button type="submit" class="w-full flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300 dark:focus:bg-gray-700">
                                                    <i class="bi bi-folder"></i>
                                                    {{ __('backup::lang.index.restore_storage') }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.backups.restore.all', $backup->identifier) }}" class="confirmation-popup" data-confirm-button-text="{{ __('backup::lang.index.restore') }}" data-text="{{ __('backup::lang.confirm_restore') }}" data-confirm-button-color="#4F46E5">
                                                @csrf
                                                <button type="submit" class="w-full flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300 dark:focus:bg-gray-700">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                    {{ __('backup::lang.index.restore_all') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('admin.backups.destroy', $backup->identifier) }}" class="confirmation-popup inline" data-confirm-button-text="{{ __('global.delete') }}" data-text="{{ __('backup::lang.confirm_delete') }}" data-confirm-button-color="#EF4444">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="bi bi-trash"></i>
                                            {{ __('global.delete') }}

                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-300">
                                {{ __('backup::lang.index.empty') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-heading">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ __('backup::lang.index.run_now') }}</h3>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.backups.run') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('backup::providers.title') }}</label>
                        <select name="provider_id" class="input-text w-full">
                            @foreach($providers as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex gap-6 flex-wrap">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="include_database" value="1" class="rounded" checked>
                        {{ __('backup::lang.index.include_database') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="include_storage" value="1" class="rounded" checked>
                        {{ __('backup::lang.index.include_storage') }}
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <span>{{ __('backup::lang.index.run_now') }}</span>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection