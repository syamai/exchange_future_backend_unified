<?php
/**
 * Created by PhpStorm.
 * Date: 7/12/19
 * Time: 4:34 PM
 */

namespace App\Http\Controllers\API;

use App\Facades\CheckFa;
use App\Http\Controllers\AppBaseController;
use Illuminate\Http\Request;

class ValidateController extends AppBaseController
{
    public function blockchainAddress(Request $request)
    {
        try {
            $data = CheckFa::blockchainAddress($request->currency, $request->blockchain_address, $request->network_id);

            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function testEncryptData (Request $request)
    {
       return $this->sendResponse(true);
    }
}

