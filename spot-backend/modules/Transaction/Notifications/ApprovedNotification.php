<?php
/**
 * Created by PhpStorm.
 * Date: 5/2/19
 * Time: 3:53 PM
 */

namespace Transaction\Notifications;

use Transaction\Models\Transaction;
use App\Models\User;
use App\Utils;
use App\Utils\BigNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;

class ApprovedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $transactionId;

    public function __construct($transactionId)
    {
        $this->transactionId = $transactionId;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $transaction = Transaction::find($this->transactionId);
        $user = User::find($transaction->user_id);
        $locale = $user->getLocale();
        $date = (string)Carbon::now('UTC');
        $subject = trans('emails.approved_notification.subject', [
            'app_name' => env('APP_NAME'),
            'date' => "$date (UTC)"
        ], $locale);

        return  $this->view('emails.approved_notification')
            ->subject("$subject")
            ->to($user->email)
            ->with([
                'name' => $user->name,
                'currency' => $transaction->currency,
                'amount' => BigNumber::new(-1)->mul($transaction->amount)->sub($transaction->fee)->toString(),
                'withdrawAddress' => $transaction->to_address,
                'createdAt' => Utils::millisecondsToDateTime($transaction->created_at, 0, 'Y-m-d h:i:s'),
                'locale' => $locale,
                'user' => $user
            ]);
    }
}
