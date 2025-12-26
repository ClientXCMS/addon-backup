@php
$logs = \App\Addons\Backup\Models\BackupLog::orderBy('created_at', 'desc')->limit(3)->get();
$lastBackup = \App\Addons\Backup\Models\BackupLog::where('status', 'success')->orderBy('completed_at', 'desc')->first();

// Calculate success rate for last 7 days
$sevenDaysAgo = now()->subDays(7);
$totalAttempts = \App\Addons\Backup\Models\BackupLog::where('created_at', '>=', $sevenDaysAgo)->count();
$successfulAttempts = \App\Addons\Backup\Models\BackupLog::where('created_at', '>=', $sevenDaysAgo)->where('status', 'success')->count();
$successRate = $totalAttempts > 0 ? round(($successfulAttempts / $totalAttempts) * 100) : 100;

$healthStatus = 'good';
if ($successRate < 90) $healthStatus='warning' ;
    if ($successRate < 50 || ($totalAttempts> 0 && $successfulAttempts == 0)) $healthStatus = 'danger';

    $statusClasses = [
    'good' => 'text-green-600 dark:text-green-400',
    'warning' => 'text-yellow-600 dark:text-yellow-400',
    'danger' => 'text-red-600 dark:text-red-400',
    ];

    $bgClasses = [
    'good' => 'bg-green-100 dark:bg-green-900/30',
    'warning' => 'bg-yellow-100 dark:bg-yellow-900/30',
    'danger' => 'bg-red-100 dark:bg-red-900/30',
    ];
    @endphp

    <div class="flex flex-col h-full">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                    {{ __('backup::lang.widget.title') }}
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('backup::lang.widget.description') }}
                </p>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $bgClasses[$healthStatus] }} {{ $statusClasses[$healthStatus] }}">
                {{ __('backup::lang.widget.health.' . $healthStatus) }}
            </span>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="p-3 border rounded-lg dark:border-gray-700">
                <p class="text-[10px] uppercase font-bold text-gray-500 dark:text-gray-400 mb-1">
                    {{ __('backup::lang.widget.success_rate') }}
                </p>
                <p class="text-xl font-bold {{ $statusClasses[$healthStatus] }}">
                    {{ $successRate }}%
                </p>
            </div>
            <div class="p-3 border rounded-lg dark:border-gray-700">
                <p class="text-[10px] uppercase font-bold text-gray-500 dark:text-gray-400 mb-1">
                    {{ __('backup::lang.widget.last_backup') }}
                </p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                    @if($lastBackup)
                    {{ $lastBackup->completed_at->diffForHumans() }}
                    @else
                    -
                    @endif
                </p>
            </div>
        </div>

        <div class="space-y-2 flex-grow">
            @forelse($logs as $log)
            <div class="flex items-center justify-between text-xs p-2 rounded bg-gray-50 dark:bg-slate-800/50">
                <div class="flex items-center gap-2">
                    @if($log->status === 'success')
                    <i class="bi bi-check-circle-fill text-green-500"></i>
                    @elseif($log->status === 'failed')
                    <i class="bi bi-x-circle-fill text-red-500"></i>
                    @else
                    <i class="bi bi-arrow-repeat text-yellow-500 animate-spin"></i>
                    @endif
                    <span class="text-gray-700 dark:text-gray-300">{{ $log->created_at->format('H:i') }}</span>
                    <span class="text-gray-500 dark:text-gray-500 italic">({{ $log->type }})</span>
                </div>
                @if($log->status === 'failed')
                <span class="text-red-500 truncate ml-2 max-w-[100px]" title="{{ $log->error_message }}">
                    {{ Str::limit($log->error_message, 15) }}
                </span>
                @endif
            </div>
            @empty
            <p class="text-xs text-center text-gray-500 py-4 italic">
                {{ __('backup::lang.index.empty') }}
            </p>
            @endforelse
        </div>

        <div class="mt-4 pt-4 border-t dark:border-gray-700">
            <a href="{{ route('admin.backups.index') }}" class="text-xs text-primary hover:underline flex items-center justify-center gap-1 font-medium">
                <i class="bi bi-gear"></i>
                {{ __('backup::lang.widget.manage') }}
            </a>
        </div>
    </div>