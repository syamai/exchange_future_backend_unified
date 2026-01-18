<?php

namespace App\Console\Commands;

use App\Models\User;
use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixFakeNameCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:fake-name';

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
        $userIds = DB::table('users')->whereNull('fake_name')->pluck('id');
        $bar = $this->output->createProgressBar(count($userIds));
        $bar->start();

        foreach ($userIds as $userId) {
            $fake_name = Factory::create()->name;
            DB::table('users')->where('id', $userId)->update(compact('fake_name'));
            $bar->advance();
        }

        $bar->finish();
        $this->info('');
        $this->info('Fix fake name successfully');
    }
}
