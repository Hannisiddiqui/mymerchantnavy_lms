<?php

namespace App\Filament\Widgets;

use App\Models\ActivityLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

/**
 * Activity Tracker Header Summary Cards.
 */
class WebsiteActivityStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        if (! Schema::hasTable('activity_logs')) {
            return [];
        }

        $total24h = ActivityLog::where('created_at', '>=', now()->subHours(24))->count();
        $admin24h = ActivityLog::where('actor_type', 'admin')->where('created_at', '>=', now()->subHours(24))->count();
        $student24h = ActivityLog::where('actor_type', 'student')->where('created_at', '>=', now()->subHours(24))->count();
        $critical24h = ActivityLog::where(function ($q) {
            $q->where('event', 'like', '%deleted%')
              ->orWhere('event', 'like', '%role%')
              ->orWhere('event', 'like', '%limit%')
              ->orWhere('event', 'like', '%deactivate%');
        })->where('created_at', '>=', now()->subHours(24))->count();

        return [
            Stat::make('Total Activity (24h)', number_format($total24h))
                ->description('All monitored website events')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Stat::make('Admin Actions (24h)', number_format($admin24h))
                ->description('Staff updates, logins & config')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Student Activity (24h)', number_format($student24h))
                ->description('Logins, attempts & notes')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('success'),

            Stat::make('Security & Critical (24h)', number_format($critical24h))
                ->description('Deletions, role edits & limit updates')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color('danger'),
        ];
    }
}
