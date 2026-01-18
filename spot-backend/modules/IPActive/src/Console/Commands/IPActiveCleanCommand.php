<?php

namespace IPActive\Console\Commands;

use Illuminate\Console\Command;
use IPActive\Models\IpActiveLog;

class IPActiveCleanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ip-active:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        IpActiveLog::truncate();
    }
}
