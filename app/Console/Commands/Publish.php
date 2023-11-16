<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class Publish extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publish:post';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Log a message to indicate the command is running
        Log::info('Publishing post...');

        // Make an HTTP request using Laravel's HttpClient
        $response = Http::get('http://localhost:3000/faq'); // Replace with the URL you want to access

        // Log the response or handle it as needed
        Log::info('HTTP Response: ' . $response->status());

        // You can also log the response content if needed
        Log::info('Response Content: ' . $response->body());

        // Return a success status
        return Command::SUCCESS;
    }
}