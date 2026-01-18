<?php

namespace App\Console\Commands;

use App\Consts;
use App\Jobs\UpdateAffiliateTrees;
use App\Models\User;
use Illuminate\Console\Command;
use Exception;

class UpdateAffiliateTreesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:affiliate_trees';

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
        $userList = User::doesnthave("affiliateTreeUsers")->get();
        foreach ($userList as $user) {
            UpdateAffiliateTrees::dispatch($user->id)->onQueue(Consts::QUEUE_UPDATE_AFFILIATE_TREE);
        }
    }
}
