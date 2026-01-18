<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateUseFakeNameCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'use_fake_name:update {value}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update field use_fake_name in user_security_settings table. You can pass argument value into command. By default, value will be 1';
    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        $value = $this->argument('value');
        DB::table('user_security_settings')->update(['use_fake_name' => $value]);
    }
}
