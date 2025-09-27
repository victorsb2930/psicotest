<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ScanSecurityEvents extends Command
{
    protected $signature = 'security:scan-events';
    protected $description = 'Scan logs for suspicious security events and notify admins.';

    public function handle()
    {
        $this->info('Scanning logs for suspicious events...');
        // Naive implementation: search storage/logs/laravel.log for token_reuse_suspicious
        $path = storage_path('logs/laravel.log');
        if (!file_exists($path)) {
            $this->info('No log file found.');
            return 0;
        }
        $content = file_get_contents($path);
        $count = substr_count($content, 'user_login.token_reuse_suspicious');
        if ($count > 0) {
            $this->info("Found {$count} suspicious events.");
            $admins = (array) config('app.admin_emails', []);
            foreach ($admins as $a) {
                try {
                    Mail::raw("Se detectaron {$count} intentos sospechosos de reutilización de token. Revisa /user/devices.", function($m) use ($a){ $m->to($a)->subject('Security events detected'); });
                } catch (\Throwable $_) { }
            }
        } else {
            $this->info('No suspicious events found.');
        }
        return 0;
    }
}
