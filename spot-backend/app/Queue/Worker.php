<?php

namespace App\Queue;

use Illuminate\Queue\Worker as LaravelWorker;

class Worker extends LaravelWorker
{
    /**
     * Sleep the script for a given number of seconds.
     *
     * @param  int   $seconds
     * @return void
     */
    public function sleep($seconds)
    {
        usleep($seconds * 1000000);
    }
}
