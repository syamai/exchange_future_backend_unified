<?php

namespace App\Http\Controllers\Admin;

use App\Events\TransactionSettingEvent;
use App\Http\Controllers\AppBaseController;
use App\Models\CoinsConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Services\MasterdataService;

class CoinsConfirmationController extends AppBaseController
{
    public function index(Request $request)
    {
        try {
            $input = $request->all();
            $data = CoinsConfirmation::filter($input)
            ->orderBy(request('sort', 'id'), request('sort_type', 'asc'))->get();
            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $input = $request->all();
            $coinsConfirmation = CoinsConfirmation::find($id);
            if (empty($coinsConfirmation)) {
                return $this->sendError('id not found', 401);
            }
            $data = CoinsConfirmation::where('id', $id)
                ->update($input);

            MasterdataService::clearCacheOneTable('coins_confirmation');

            $this->socket();

            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function updateAll()
    {
        try {
            $data = CoinsConfirmation::where('id', '>', 0)
                ->update(\request()->all());

            MasterdataService::clearCacheOneTable('coins_confirmation');

            $this->socket();

            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    private function socket()
    {
        $data = MasterdataService::getOneTable('coins_confirmation');
        logger($data);

        event(new TransactionSettingEvent($data));
    }
}
