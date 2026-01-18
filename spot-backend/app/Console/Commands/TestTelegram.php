<?php

namespace App\Console\Commands;

use App\Jobs\SendNotifyTelegram;
use Illuminate\Console\Command;

class TestTelegram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:run-test {type?}';

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
		$type = $this->argument('type') ?? '';
		SendNotifyTelegram::dispatch($type, 'test '.$type);
    }
}
