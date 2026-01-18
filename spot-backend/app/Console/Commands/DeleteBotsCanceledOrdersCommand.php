<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Process;
use App\Models\User;
use App\Utils;
use Illuminate\Support\Facades\DB;

class DeleteBotsCanceledOrdersCommand extends BaseLogCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:delete_bots_orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete bots orders';

    protected $process;

    protected int $batchSize = 1000;
    protected $sleepTime = 800000;

    protected $bots;



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->process = Process::firstOrCreate(['key' => $this->getProcessKey()]);
        $this->doInitJob();

        while (true) {
            $this->doJob();
            usleep($this->sleepTime);
        }
    }

    protected function getProcessKey(): string
    {
        return 'delete_bots_canceled_orders';
    }

    protected function log($message)
    {
        logger($message);
        $this->info($message);
    }

    protected function doInitJob()
    {
    }

    protected function doJob()
    {
        $count = 0;
        $shouldContinue = true;

        while ($shouldContinue) {
            DB::transaction(function () use (&$shouldContinue, &$count) {
                if ($count > 100) {
                    $count = 0;
                }
                if ($count === 0) {
                    $this->updateBotList();
                }
                $count++;

                $process = Process::lockForUpdate()->find($this->process->id);
                $records = $this->getNextRecords($process);

                $deletingIds = [];
                $deleteTime = Utils::currentMilliseconds() - 43200000;
                foreach ($records as $record) {
                    // $this->log("Processing order: {$record->id} {$record->email} {$record->status} | {$this->isCreatedByBot($record)} | {$record->isCanceled()}");
                    if ($record->updated_at > $deleteTime) {
                        break;
                    }
                    if ($this->shouldDeleteOrder($record)) {
                        $deletingIds[] = $record->id;
                    }
                    $process->processed_id = $record->id;
                }
                // $this->log("Deleting order: " . json_encode($deletingIds));
                Order::whereIn('id', $deletingIds)->delete();

                $process->save();
                $this->process = $process;

                usleep($this->sleepTime);
                $shouldContinue = count($records) === $this->batchSize;
            });
        };
    }

    protected function updateBotList()
    {
        $this->bots = User::where('type', 'bot')->select('email')->get()->keyBy('email')->toArray();
    }

    protected function getNextRecords($process)
    {
        $query = Order::where('id', '>', $process->processed_id);
        return $query->orderBy('id', 'asc')->limit($this->batchSize)->get();
    }

    protected function trackeeFilter($query)
    {
        return $query;
    }

    protected function shouldDeleteOrder($order)
    {
        return $order->isCanceled() && $this->isCreatedByBot($order);
    }

    protected function isCreatedByBot($order)
    {
        return $order->email && array_key_exists($order->email, $this->bots);
    }
}
