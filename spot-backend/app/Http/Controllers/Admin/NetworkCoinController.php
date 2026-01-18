<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\NetworkCoin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Services\MasterdataService;
use Illuminate\Support\Arr;
use App\Consts;

class NetworkCoinController extends AppBaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getNetworkCoins(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $data = NetworkCoin::filter($input)->paginate($limit);

        return $this->sendResponse($data);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getNetworkCoinById($id)
    {
        $networkCoin = NetworkCoin::find($id);

        if (!$networkCoin) {
            return $this->sendError('Network Coin not found');
        }
        return $this->sendResponse($networkCoin);
    }

    /**
     * Update the specified resource in storage or create if not exists.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function createNewOrUpdateNetworkCoin(Request $request, $id = null)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'network_id' => 'sometimes|integer',
            'coin_id' => 'sometimes|integer',
            'contract_address' => 'sometimes|string|max:191',
            'network_deposit_enable' => 'sometimes|integer',
            'network_withdraw_enable' => 'nullable|integer',
            'network_enable' => 'sometimes|integer',
            'token_explorer_url' => 'sometimes|string',
            'withdraw_fee' => 'sometimes|numeric',
            //'min_deposit' => 'nullable|numeric',
            'min_withdraw' => 'sometimes|numeric',
        ]);

        $data = $request->all();
        $data['min_deposit'] = 0;
        // Check if validation fails
        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }
    
        if ($id) {
            // Update the existing network
            $networkCoin = NetworkCoin::find($id);
            if ($networkCoin) {
                $networkCoin->update($data);
                return $this->sendResponse($networkCoin);
            } else {
                return $this->sendError('Network Coin not found');
            }
        } else {
            // Create a new network
            $networkCoin = NetworkCoin::create($data);
            return $this->sendResponse($networkCoin);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function deleteNetworkCoin($id)
    {
        $network = NetworkCoin::find($id);

        if (!$network) {
            return $this->sendError('Network Coin not found');
        }

        $network->delete();
        return $this->sendResponse(null);
    }
}
