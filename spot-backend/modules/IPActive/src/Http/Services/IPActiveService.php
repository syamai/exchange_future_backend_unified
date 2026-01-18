<?php

/**
 * Created by PhpStorm.
 * Date: 7/22/19
 * Time: 11:59 AM
 */

namespace IPActive\Http\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use IPActive\Define;
use IPActive\Events\IPActiveEvent;
use IPActive\Models\IpActiveLog;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IPActiveService
{
    protected $model;
    protected $validTime;
    protected $data;

    public function __construct(IpActiveLog $ipActiveLog)
    {
        $this->model = $ipActiveLog;
    }

    public function checkValidTime($ip, $action)
    {
        $this->data = compact('ip', 'action');

        $time = $this->model->where($this->data)->max('created_at');

        if (empty($time)) {
            return true;
        }

        $now = Carbon::now();
        $this->validTime = Define::getValidTime($action);
        $diffTime = $now->diffInSeconds($time);

        return $diffTime > $this->validTime;
    }

    public function countTime($ip, $action)
    {
        $this->data = compact('ip', 'action');
        $timeOver = Carbon::now()->subMinute()->format('Y-m-d H:i:s');
        $time = $this->model->where($this->data)
            ->where('created_at', '>=', $timeOver)
            ->count();
        $this->validTime = Define::getValidTime($action);
        return $time < $this->validTime;
    }

    public function checkTimeActiveLatest($action)
    {
        $ip = request()->ip();

        if ($action == Define::LOGIN) {
            $result = $this->countTime($ip, $action);
            $message = __('validation.ip-active-count.', ['time' => $this->validTime]);
        } else {
            $result = $this->checkValidTime($ip, $action);
            $message = __('validation.ip-active.', ['time' => $this->validTime]);
        }

        if ($result) {
            event(new IPActiveEvent($this->data));
            return true;
        }

        throw new HttpException(422, $message);
    }
}
