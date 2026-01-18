<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\AmlSettingCreateRequest;
use App\Http\Requests\AmlSettingUpdateRequest;
use App\Http\Resources\AmlSettingResource;
use App\Http\Services\AmlSettingService;
use Illuminate\Http\Request;

class AmlSettingController extends AppBaseController
{
    private $service;

    public function __construct(AmlSettingService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $input = $request->all();
        $data = $this->service->index($input);
        return $this->sendResponse($data);
    }
    public function update(AmlSettingUpdateRequest $request, $id)
    {
        $input = $request->all();
        $data = $this->service->update($input, $id);
        return $this->sendResponse($data);
    }
}
