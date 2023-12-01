<?php

namespace App\Console\Commands\Hive;

use Illuminate\Console\Command;

class GetAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hive:get_account';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Accounts';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        return Command::SUCCESS;
    }
}
