<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Services\GeetestService;
use App\Http\Controllers\AppBaseController;

class GeetestAPIController extends AppBaseController
{
    public function preVerify()
    {
        return GeetestService::preVerify();
    }
}
