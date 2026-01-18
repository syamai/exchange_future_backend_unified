<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

class getPassportFromS3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'passport:s3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get passport from S3';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Storage::disk('local')->put('oauth-public.key', Storage::disk('s3')->get('passport/oauth-public.key'));
        Storage::disk('local')->put('oauth-private.key', Storage::disk('s3')->get('passport/oauth-private.key'));
        return 0;
    }
}
