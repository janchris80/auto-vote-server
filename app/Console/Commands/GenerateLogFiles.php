<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateLogFiles extends Command
{
    protected $signature = 'generate:log-files {number? : The number of log files to generate}';
    protected $description = 'Generate log files with specific format and permissions';

    public function handle()
    {
        $logDirectory = storage_path('logs');
        $numberOfFiles = $this->argument('number') ?? 2;

        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0777, true);
        }

        for ($i = 0; $i < $numberOfFiles; $i++) {
            $logFileName = 'laravel-' . now()->addDays($i)->format('Y-m-d') . '.log';
            $logFilePath = $logDirectory . '/' . $logFileName;

            if (!file_exists($logFilePath)) {
                file_put_contents($logFilePath, '');
                chown($logFilePath, 'www-data');
                chgrp($logFilePath, 'www-data');
                chmod($logFilePath, 0777);

                $this->info("Created log file: {$logFileName}");
            } else {
                $this->warn("Log file already exists: {$logFileName}");
            }
        }

        $this->info('Log files generation complete.');
    }
}
