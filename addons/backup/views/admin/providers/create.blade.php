<?php
/*
 * This file is part of the CLIENTXCMS project.
 * It is the property of the CLIENTXCMS association.
 *
 * Personal and non-commercial use of this source code is permitted.
 * However, any use in a project that generates profit (directly or indirectly),
 * or any reuse for commercial purposes, requires prior authorization from CLIENTXCMS.
 *
 * To request permission or for more information, please contact our support:
 * https://clientxcms.com/client/support
 *
 * Learn more about CLIENTXCMS License at:
 * https://clientxcms.com/eula
 *
 * Year: 2025
 */
?>

@extends('admin/settings/sidebar')
@section('title', __($translatePrefix . '.create.title'))
@section('setting')
<div class="container mx-auto">
    <form method="POST" action="{{ route($routePath .'.store') }}">
        <div class="flex flex-col">
            <div class="-m-1.5 overflow-x-auto">
                <div class="p-1.5 min-w-full inline-block align-middle">
                    <div class="card">
                        <div class="card-heading">
                            @csrf
                            <div>
                                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                                    {{ __($translatePrefix . '.create.title') }}
                                </h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ __($translatePrefix. '.create.subheading') }}
                                </p>
                            </div>
                            <div class="mt-4 flex items-center space-x-4 sm:mt-0">
                                <button class="btn btn-primary">
                                    {{ __('admin.create') }}
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                @include('admin/shared/input', ['name' => 'name', 'label' => __('global.name'), 'value' => old('name', $item->name)])
                            </div>
                            <div>
                                @include('admin/shared/select', ['name' => 'driver', 'label' => __($translatePrefix . '.driver'), 'value' => old('driver', $item->driver), 'options' => $drivers])
                            </div>
                            <div>
                                @include('admin/shared/input', ['name' => 'frequency_hours', 'type' => 'number', 'label' => __($translatePrefix . '.frequency_hours'), 'value' => old('frequency_hours', $item->frequency_hours ?? 24)])
                            </div>
                            <div>
                                @include('admin/shared/input', ['name' => 'retention_days', 'type' => 'number', 'label' => __($translatePrefix . '.retention_days'), 'value' => old('retention_days', $item->retention_days ?? 7)])
                            </div>
                            <div class="col-span-2">
                                @include('admin/shared/checkbox', ['name' => 'enabled', 'label' => __($translatePrefix . '.enabled'), 'checked' => old('enabled', $item->enabled ?? true)])
                            </div>
                        </div>

                        <div class="mt-4 border-t pt-4 dark:border-gray-700" id="config-section">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">{{ __($translatePrefix . '.configuration') }}</h3>

                            <!-- Local -->
                            <div class="driver-config" id="config-local" style="display:none">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[root]', 'label' => __($translatePrefix . '.config.root'), 'value' => old('configuration.root', $item->configuration['root'] ?? storage_path('backups'))])
                                    </div>
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[base_path]', 'label' => __($translatePrefix . '.config.base_path'), 'value' => old('configuration.base_path', $item->configuration['base_path'] ?? 'backups')])
                                    </div>
                                </div>
                            </div>

                            <!-- FTP/SFTP -->
                            <div class="driver-config" id="config-ftp" style="display:none">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[host]', 'label' => __($translatePrefix . '.config.host'), 'value' => old('configuration.host', $item->configuration['host'] ?? '')])
                                    </div>
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[username]', 'label' => __($translatePrefix . '.config.username'), 'value' => old('configuration.username', $item->configuration['username'] ?? '')])
                                    </div>
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[password]', 'type' => 'password', 'label' => __($translatePrefix . '.config.password'), 'value' => old('configuration.password', $item->configuration['password'] ?? '')])
                                    </div>
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[port]', 'type' => 'number', 'label' => __($translatePrefix . '.config.port'), 'value' => old('configuration.port', $item->configuration['port'] ?? 21)])
                                    </div>
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[root]', 'label' => __($translatePrefix . '.config.root'), 'value' => old('configuration.root', $item->configuration['root'] ?? '/')])
                                    </div>
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[base_path]', 'label' => __($translatePrefix . '.config.base_path'), 'value' => old('configuration.base_path', $item->configuration['base_path'] ?? 'backups')])
                                    </div>
                                    <div class="mt-6">
                                        @include('admin/shared/checkbox', ['name' => 'configuration[ssl]', 'label' => __($translatePrefix . '.config.ssl'), 'checked' => old('configuration.ssl', $item->configuration['ssl'] ?? false)])
                                    </div>
                                </div>
                                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-800 sftp-notice" style="display:none">
                                    <p class="text-sm text-blue-800 dark:text-blue-200">
                                        <i class="bi bi-info-circle-fill mr-1"></i>
                                        SFTP support requires <code>league/flysystem-sftp-v3</code> dependency.
                                    </p>
                                </div>
                            </div>

                            <!-- S3 -->
                            <div class="driver-config" id="config-s3" style="display:none">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[key]', 'label' => __($translatePrefix . '.config.key'), 'value' => old('configuration.key', $item->configuration['key'] ?? '')])
                                    </div>
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[secret]', 'type' => 'password', 'label' => __($translatePrefix . '.config.secret'), 'value' => old('configuration.secret', $item->configuration['secret'] ?? '')])
                                    </div>
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[region]', 'label' => __($translatePrefix . '.config.region'), 'value' => old('configuration.region', $item->configuration['region'] ?? 'us-east-1')])
                                    </div>
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[bucket]', 'label' => __($translatePrefix . '.config.bucket'), 'value' => old('configuration.bucket', $item->configuration['bucket'] ?? '')])
                                    </div>
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[endpoint]', 'label' => __($translatePrefix . '.config.endpoint'), 'value' => old('configuration.endpoint', $item->configuration['endpoint'] ?? '')])
                                    </div>
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[base_path]', 'label' => __($translatePrefix . '.config.base_path'), 'value' => old('configuration.base_path', $item->configuration['base_path'] ?? 'backups')])
                                    </div>
                                    <div class="mt-6">
                                        @include('admin/shared/checkbox', ['name' => 'configuration[use_path_style_endpoint]', 'label' => __($translatePrefix . '.config.use_path_style_endpoint'), 'checked' => old('configuration.use_path_style_endpoint', $item->configuration['use_path_style_endpoint'] ?? false)])
                                    </div>
                                </div>
                            </div>

                            <!-- Google Drive -->
                            <div class="driver-config" id="config-google" style="display:none">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[client_id]', 'label' => __($translatePrefix . '.config.id'), 'value' => old('configuration.client_id', $item->configuration['client_id'] ?? '')])
                                    </div>
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[client_secret]', 'type' => 'password', 'label' => __($translatePrefix . '.config.client_secret'), 'value' => old('configuration.client_secret', $item->configuration['client_secret'] ?? '')])
                                    </div>
                                    <div class="col-span-2">
                                        @include('admin/shared/input', ['name' => 'configuration[refresh_token]', 'label' => __($translatePrefix . '.config.refresh_token'), 'value' => old('configuration.refresh_token', $item->configuration['refresh_token'] ?? '')])
                                    </div>
                                    <div>
                                        @include('admin/shared/input', ['name' => 'configuration[folder]', 'label' => __($translatePrefix . '.config.folder'), 'value' => old('configuration.folder', $item->configuration['folder'] ?? ''), 'help' => 'Optional Google Drive Folder ID'])
                                    </div>
                                </div>
                                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-800">
                                    <p class="text-sm text-blue-800 dark:text-blue-200">
                                        <i class="bi bi-info-circle-fill mr-1"></i>
                                        Google Drive support requires <code>masbug/flysystem-google-drive-ext</code> and <code>google/apiclient</code> dependencies.
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const driverSelect = document.querySelector('select[name="driver"]');
        const form = document.querySelector('form');

        function updateConfigVisibility() {
            const driver = driverSelect.value;

            // Hide all and disable their inputs
            document.querySelectorAll('.driver-config').forEach(el => {
                el.style.display = 'none';
                el.querySelectorAll('input, select, textarea').forEach(input => {
                    input.disabled = true;
                });
            });

            // Hide all notices
            document.querySelectorAll('.sftp-notice').forEach(el => {
                el.style.display = 'none';
            });

            // Show selected driver config and enable its inputs
            let target = 'config-' + driver;
            if (driver === 'sftp') {
                target = 'config-ftp';
                document.querySelector('.sftp-notice').style.display = 'block';
            }

            const el = document.getElementById(target);
            if (el) {
                el.style.display = 'block';
                el.querySelectorAll('input, select, textarea').forEach(input => {
                    input.disabled = false;
                });
            }
        }

        driverSelect.addEventListener('change', updateConfigVisibility);
        updateConfigVisibility();
    });
</script>
@endsection