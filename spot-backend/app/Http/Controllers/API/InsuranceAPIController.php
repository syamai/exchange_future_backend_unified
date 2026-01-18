<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\InsuranceService;
use JetBrains\PhpStorm\Pure;

/**
 * Class InsuranceAPIController
 * @package App\Http\Controllers\API
 */
class InsuranceAPIController extends AppBaseController
{

    private InsuranceService $insuranceService;

    #[Pure] public function __construct()
    {
        $this->insuranceService = new InsuranceService();
    }

    public function getInsuranceFund()
    {
        $data = $this->insuranceService->getInsuranceFund();

        return $this->sendResponse($data);
    }
}
