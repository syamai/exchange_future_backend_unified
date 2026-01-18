<?php

namespace App\Console\Commands;

use App\Models\Process;
use Illuminate\Support\Facades\DB;

class SpotTradeBaseCommand extends SpotBaseCommand
{
    /**
     * Do not run this command directly.
     *
     * @var string
     */
    protected $signature = 'spot:update_by_trade';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update by trade';

    protected $process;

    protected function doJob()
    {
        $shouldContinue = true;
        while ($shouldContinue) {
            DB::connection('master')->transaction(function () use (&$shouldContinue) {
                $process = Process::on('master')->lockForUpdate()->find($this->process->id);
                $trade = $this->getNextTrade($process);

                $this->processMissingTrades($process, $trade);

                if (!$trade) {
                    $shouldContinue = false;
                    return;
                }

                for ($i = $process->processed_id + 1; $i < $trade->id; $i++) {
                    $this->addMissingTrade($process, $i);
                }

                $this->processTrade($trade);

                $process->processed_id = $trade->id;
                $process->save();
                $this->process = $process;
            });
        }
    }

    private function processMissingTrades($process, $transaction)
    {
        if ($process->data) {
            $ids = explode(',', $process->data);
            $missingIds = [];
            foreach ($ids as $id) {
                $trade = DB::table('order_transactions')->find($id);
                if ($trade) {
                    $this->processTrade($trade);
                } else {
                    // if $id is too 'old', ignore it
                    if (!$transaction || (int)$transaction->id - (int)$id < 10000) {
                        $missingIds[] = $id;
                    }
                }
            }
            $process->data = implode(',', $missingIds);
            $process->save();
        }
    }

    private function addMissingTrade($process, $id)
    {
        if ($process->data) {
            $process->data = $process->data . ',' . $id;
        } else {
            $process->data = $id;
        }
    }

    protected function processTrade($trade)
    {
        throw new \Exception('Method processTrade must be overrided in sub class.');
    }

    protected function getNextTrade($process)
    {
        return DB::table('order_transactions')
            ->where('id', '>', $process->processed_id)
            ->orderBy('id', 'asc')
            ->first();
    }
}
