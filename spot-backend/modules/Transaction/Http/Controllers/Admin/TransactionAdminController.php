<?php
/**
 * Created by PhpStorm.
 * Date: 5/25/19
 * Time: 10:29 AM
 */

namespace Transaction\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Transaction\Http\Services\TransactionService;
use Transaction\Http\Services\WithdrawalManagerService;
use Transaction\Models\Transaction;

class TransactionAdminController extends AppBaseController
{

    /**
     * @var TransactionService
     */
    private $transactionService;
    private $withdrawalManagerService;

    public function __construct(
        TransactionService $transactionService,
        WithdrawalManagerService $withdrawalManagerService
    ) {
        $this->withdrawalManagerService = $withdrawalManagerService;
        $this->transactionService = $transactionService;
    }

    public function getExternalWithdraws(Request $request)
    {
        $data = $this->transactionService->getExternalWithdrawTransaction($request->all());
        return $this->sendResponse($data);
    }

    public function setTransactionStatus(Request $request)
    {
        DB::beginTransaction();
        try {
            $id = $request->id;
            $status = $request->status;

            $order = Transaction::findOrFail($id);
            $order->status = $status;
            $order->save();

            DB::commit();
            return $this->sendResponse($order->status);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            throw $e;
        }
    }

    public function getWithdrawalHistory(Request $request)
    {
        $data = $this->transactionService->getWithdrawalHistory($request->all());
        return $this->sendResponse($data);
    }

    public function getTransaction($transactionId)
    {
        /* @phpstan-ignore-next-line */
        $transaction = Transaction::with(['user.kyc'])
            ->select(
                'transactions.*',
                'blockchain_addresses.path',
                'blockchain_addresses.blockchain_address',
                'deny_person.email as deny_by',
                'approved_person.email as approved_by',
                'sent_person.email as sent_by'
            )
            ->leftJoin('blockchain_addresses', 'blockchain_addresses.blockchain_address', 'transactions.from_address')
            ->leftJoin('admins as deny_person', 'deny_person.id', 'transactions.deny_by')
            ->leftJoin('admins as approved_person', 'approved_person.id', 'transactions.approved_by')
            ->leftJoin('admins as sent_person', 'sent_person.id', 'transactions.sent_by')
            ->where('transaction_id', $transactionId)
            ->first();

        return $this->sendResponse($transaction);
    }

    public function registrationRemittance(Request $request)
    {
        DB::beginTransaction();
        try {
            $transaction = $this->withdrawalManagerService->remittance($request);

            DB::commit();
            return $this->sendResponse($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            throw $e;
        }
    }
}
