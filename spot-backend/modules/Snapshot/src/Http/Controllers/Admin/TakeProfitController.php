<?php

namespace Snapshot\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use Illuminate\Http\Request;
use Snapshot\Http\Requests\TakeProfitCreateRequest;
use Snapshot\Http\Services\TakeProfitService;

class TakeProfitController extends AppBaseController
{
    protected $service;

    public function __construct(TakeProfitService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $input = $request->all();

        try {
            $data = $this->service->myPaginate($input);

            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function statistic()
    {
        try {
            $data = $this->service->statistic();

            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function store(TakeProfitCreateRequest $request)
    {
        try {
            $input = $request->only(['currency', 'amount']);

            $data = $this->service->store($input);

            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }
}
