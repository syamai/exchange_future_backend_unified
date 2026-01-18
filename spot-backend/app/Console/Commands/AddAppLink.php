<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\Settings;
use Illuminate\Console\Command;

class AddAppLink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setting:update';

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
        $siteSetting = Settings::where(['key' => Consts::APP_LINK])->first();
        if (!$siteSetting) {
            Settings::create([
                'key' => Consts::APP_LINK
            ]);
            echo "Add app_link to setting success! \n";
            return;
        }
        echo "app_link existed! \n";
        return;
    }
}
