<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\VoucherRequest;
use App\Http\Services\VoucherService;
use App\Models\Voucher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\CreateUserVolumeByVoucher;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VoucherController extends AppBaseController
{
    protected $voucherService;
    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            return $this->sendResponse($this->voucherService->getList($request));
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(VoucherRequest $request)
    {
        try {
            $data = $request->only([
                'name',
                'type',
                'currency',
                'amount',
                'number',
                'conditions_use',
                'expires_date_number',
            ]);

            $voucherType = Voucher::where('type', $data['type'])->first();
            if ($voucherType) {
                throw new HttpException(422, 'voucher.no.create');
            }
            $voucher = Voucher::create($data);
            return $this->sendResponse($voucher);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            return $this->sendResponse(Voucher::findOrFail($id));
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(VoucherRequest $request, $id)
    {
        try {
            return $this->sendResponse($this->voucherService->update($id, $request));
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            return $this->sendResponse($this->voucherService->destroy($id));
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }
}
