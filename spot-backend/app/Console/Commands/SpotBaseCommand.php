<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\App;
use App\Models\Process;
use Exception;
use Illuminate\Console\Command;

class SpotBaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:base_command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Base command';

    protected $process;

	protected $timeSleep = 100000;

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        $this->process = Process::firstOrCreate(['key' => $this->getProcessKey()]);

        while (true) {
            $this->doJob();
            if (App::runningUnitTests()) {
                break;
            } else {
                usleep($this->timeSleep);
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
}
