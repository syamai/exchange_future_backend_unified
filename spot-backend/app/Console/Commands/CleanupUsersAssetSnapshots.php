<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupUsersAssetSnapshots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:user_asset_snapshots';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Snapshot cleanup: rows older than 30 days';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $deleted = DB::table('user_asset_snapshots')
            ->where('snapshotted_at', '<', now()->subDays(30)->timestamp * 1000)
            ->delete();

        Log::channel('spot_overview')->debug(
            "Snapshot cleanup: deleted {$deleted} rows older than 30 days",
            ['deleted' => $deleted]
        );

        Log::info("Snapshot cleanup: deleted {$deleted} rows older than 30 days");
    }
}
