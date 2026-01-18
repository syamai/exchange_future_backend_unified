<?php
/**
 * Created by PhpStorm.

 * Date: 5/27/19
 * Time: 4:39 PM
 */

namespace Transaction\Http\Services;

use App\Consts;
use App\Utils;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Transaction\Models\Transaction;
use Transaction\Notifications\ApprovedNotification;
use Transaction\Utils\BalanceCalculate;

class WithdrawalManagerService
{

    /**
     * @var WalletService
     */
    private $walletService;

    /**
     * @var TransactionService
     */
    private $transactionService;

    public function __construct(WalletService $walletService, TransactionService $transactionService)
    {
        $this->walletService = $walletService;
        $this->transactionService = $transactionService;
    }

    public function approve($params)
    {
        $action = \Arr::get($params, 'action');

        $transactionId = \Arr::get($params, 'transaction_id');
        $transaction = $this->transactionService->getWithdrawTransactionById($transactionId);

        switch ($action) {
            case 'verified':
                return $this->verifyApprove($transaction, $params);
            case 'rejected':
                return $this->rejectApprove($transaction, $params);
        }

        return $transaction;
    }

    private function verifyApprove($transaction, $params)
    {
        $transaction->update([
            'approve_at' => Utils::currentMilliseconds(),
            'approved_by' => auth()->id(),
            'status' => Transaction::APPROVED_STATUS,
            'remarks' => \Arr::get($params, 'remarks')
        ]);

        Mail::queue((new ApprovedNotification($transaction->id))->onQueue(Consts::ADMIN_QUEUE));

        return $transaction;
    }

    private function rejectApprove($transaction, $params)
    {
        $transaction->update([
            'deny_at' => Utils::currentMilliseconds(),
            'deny_by' => auth()->id(),
            'status' => Transaction::REJECTED_STATUS,
            'remarks' => \Arr::get($params, 'remarks')
        ]);

        $this->walletService->updateUserBalanceRaw(
            $transaction->currency,
            $transaction->user_id,
            0,
            BalanceCalculate::rejectWithdraw($transaction)
        );

        return $transaction;
    }

    public function remittance($request)
    {
        $transaction = Transaction::where('transaction_id', $request->input('transaction_id'))
            ->where('status', Transaction::APPROVED_STATUS)->first();

        if (is_null($transaction)) {
            throw new HttpException(404, __('exception.transaction_not_found'));
        }

        $transaction->sent_by = Auth::id();
        $transaction->sent_at = Utils::currentMilliseconds();
        $transaction->tx_hash = $request->input('tx_hash');
        $transaction->remarks = $request->input('remarks');
        $transaction->send_confirmer1 = $request->input('send_confirmer1');
        $transaction->send_confirmer2 = $request->input('send_confirmer2');
        $transaction->status = Consts::TRANSACTION_STATUS_SUCCESS;
        $transaction->save();

        $balanceTransaction = BalanceCalculate::approvedWithdraw($transaction);
        $this->walletService->updateUserBalanceRaw($transaction->currency, $transaction->user_id, $balanceTransaction, 0);
        return $transaction;
    }
}
