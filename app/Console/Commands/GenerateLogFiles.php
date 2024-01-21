<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateLogFiles extends Command
{
    protected $signature = 'generate:log-file';
    protected $description = 'Generate a single log file with specific format and permissions';

    public function handle()
    {
        $logDirectory = storage_path('logs');

        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0777, true);
        }

        $logFileName = 'laravel-' . now()->addDay()->format('Y-m-d') . '.log';
        $logFilePath = $logDirectory . '/' . $logFileName;

        if (!file_exists($logFilePath)) {
            file_put_contents($logFilePath, '');
            // Set the ownership and permissions
            chown($logFilePath, 'www-data');
            chgrp($logFilePath, 'www-data');
            chmod($logFilePath, 0777);

            $this->info("Created log file: {$logFileName}");
        } else {
            $this->warn("Log file already exists: {$logFileName}");
        }

        $this->info('Log file generation complete.');
    }
}
