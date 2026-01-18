<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class putMasterDataToS3 extends Command
{
    /**
     * Put masterdata to s3.
     *
     * @var string
     */
    protected $signature = 'masterdata:putS3';

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
        Storage::disk('s3')->put('masterdata/latest.json', Storage::disk('local')->get('masterdata/latest.json'));
        return Command::SUCCESS;
    }
}
