<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\Network;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Services\MasterdataService;
use Illuminate\Support\Arr;
use App\Consts;
use Illuminate\Support\Facades\Log;

class NetworkController extends AppBaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getNetworks(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $data = Network::filter($input)->orderBy('created_at', 'desc')->paginate($limit);

        return $this->sendResponse($data);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getNetworkById($id)
    {
        $network = Network::find($id);

        if (!$network) {
            return $this->sendError('Network not found');
        }

        return $this->sendResponse($network);
    }

    /**
     * Update the specified resource in storage or create if not exists.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function createNewOrUpdateNetwork(Request $request, $id = null)
    {
        try{
            // Validate the request
            $validator = Validator::make($request->all(), [
                'symbol' => 'sometimes|string|max:20',
                'name' => 'sometimes|string|max:191',
                'currency' => 'sometimes|string|max:20',
                'network_code' => 'sometimes|string|max:20',
                'chain_id' => 'nullable|integer',
                'network_deposit_enable' => 'sometimes|integer',
                'network_withdraw_enable' => 'sometimes|integer',
                'deposit_confirmation' => 'sometimes|integer',
                'explorer_url' => 'nullable|string',
                'enable' => 'sometimes|integer',
            ]);

            // Check if validation fails
            if ($validator->fails()) {
                return $this->sendError($validator->errors());
            }

            if ($request->has('currency')) {
                $request->merge([
                    'currency' => strtolower($request->input('currency'))
                ]);
            }

            if ($request->has('symbol')) {
                $request->merge([
                    'symbol' => strtolower($request->input('symbol'))
                ]);
            }

            if ($request->has('network_code')) {
                $request->merge([
                    'network_code' => strtolower($request->input('network_code'))
                ]);
            }

            if ($id) {
                // Update the existing network
                $network = Network::find($id);
                if ($network) {
                    $network->update($request->except('symbol'));
                    return $this->sendResponse($network);
                } else {
                    return $this->sendError('Network not found');
                }
            } else {
                // Create a new network
                $network = Network::create($request->all());
                return $this->sendResponse($network);
            }
        }
        catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function deleteNetwork($id)
    {
        $network = Network::find($id);

        if (!$network) {
            return $this->sendError('Network not found');

        }

        $network->delete();
        return $this->sendResponse(null);
    }
}
