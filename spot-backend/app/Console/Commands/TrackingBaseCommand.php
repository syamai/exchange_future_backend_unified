<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\App;
use App\Models\Process;
use Illuminate\Support\Facades\DB;
use Exception;

class TrackingBaseCommand extends BaseLogCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracking:base_command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Base command';

    protected $process;

    static string $trackee = 'positions_history';

    protected int $batchSize = 1000;



    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        $this->process = Process::firstOrCreate(['key' => $this->getProcessKey()]);
        $this->doInitJob();

        while (true) {
            $this->doJob();
            if (App::runningUnitTests()) {
                break;
            } else {
                usleep(100000);
            }
        }
    }

    protected function getProcessKey(): string
    {
        throw new Exception('Method getProcessKey must be overrided in sub class.');
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
        $shouldContinue = true;
        while ($shouldContinue) {
            DB::transaction(function () use (&$shouldContinue) {
                $process = Process::lockForUpdate()->find($this->process->id);
                $records = $this->getNextRecords($process);

                if (count($records) === 0) {
                    $shouldContinue = false;
                    return;
                }

                $this->processMissingRecords($process, $records[0]);

                foreach ($records as $record) {
                    for ($i = $process->processed_id + 1; $i < $record->id; $i++) {
                        $this->addMissingRecord($process, $record->id, $i);
                    }

                    $this->processRecord($record);

                    $process->processed_id = $record->id;
                }

                $process->save();
                $this->process = $process;
            });
        }
    }

    private function processMissingRecords($process, $record)
    {
        if ($process->data) {
            $ids = explode(',', $process->data);
            $missingIds = [];
            foreach ($ids as $id) {
                $missingRecord = DB::table(static::$trackee)->find($id);
                if ($missingRecord) {
                    $this->processRecord($missingRecord);
                } else {
                    if (!is_numeric($record->id)) return false;
                    // if $id is too 'old', ignore it
                    if (!$record || (int)$record->id - (int)$id < 1000) {
                        $missingIds[] = $id;
                    }
                }
            }
            $process->data = implode(',', $missingIds);
            $process->save();
        }
    }

    private function addMissingRecord($process, $processingId, $id)
    {
        if ($process->data) {
            if ($processingId - $id < 1000) {
                $process->data = $process->data . ',' . $id;
            }
        } else {
            $process->data = $id;
        }
    }

    protected function processRecord($record)
    {
        throw new Exception('Method processRecord must be overrided in sub class.');
    }

    protected function getNextRecords($process)
    {
        $query = DB::table(static::$trackee)->where('id', '>', $process->processed_id);
        $query = $this->trackeeFilter($query);
        return $query->orderBy('id', 'asc')->limit($this->batchSize)->get();
    }

    protected function trackeeFilter($query)
    {
        return $query;
    }
}
