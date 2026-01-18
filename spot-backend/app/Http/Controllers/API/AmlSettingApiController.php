<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\AmlSettingService;

class AmlSettingApiController extends AppBaseController
{
    private $service;

    public function __construct(AmlSettingService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $amlSetting = $this->service->index();
        return $this->sendResponse($amlSetting);
    }

    // public function getBonus()
    // {
    //     try {
    //         $data = \Blockchain::getBonusAll();
    //         return $this->sendResponse($data);
    //     } catch (\Exception $exception) {
    //         return $this->sendError($exception->getMessage());
    //     }
    // }
}
