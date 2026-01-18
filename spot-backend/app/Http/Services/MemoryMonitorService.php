<?php
namespace App\Http\Services;

use App\Consts;
use App\Models\Admin;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Notification;
use App\Notifications\MemoryAlertNotification;

class MemoryMonitorService
{
    public function trackMemoryUsage()
    {
        $usage = [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'timestamp' => now(),
        ];

        Redis::connection(Consts::PROMETHEUS_REDIS)->zadd(Consts::MEMORY_METRICS, now()->timestamp, json_encode($usage));
        $threshold = config('monitor.memory.threshold');

        $enable_send_memory_alert = config('monitor.memory.enable_send_alert');
        if ($usage['memory_usage'] > $threshold * 1024 * 1024 && $enable_send_memory_alert) {
            $this->sendAlert($usage);
        }
    }
    
    private function sendAlert($data)
    {
        $admins = Admin::all()->whereNotIn('email', 'admin@monas.exchange');
        Notification::send($admins, new MemoryAlertNotification($data));
    }
}
