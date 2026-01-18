<?php

namespace App\Mail;

use App\Consts;
use App\Http\Services\UserService;
use App\Models\UserAntiPhishing;
use App\Utils\BigNumber;
use Carbon\Carbon;
use http\Client\Curl\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FutureOrderLiquidated extends Mailable
{
    use Queueable, SerializesModels;

    private $user;
    private $data;

	/**
	 * Create a new message instance.
	 *
	 * @param $user
	 * @param $data
	 */
    public function __construct($user, $data)
    {
		$this->queue = Consts::QUEUE_FUTURE_MAIL;
        $this->user = $user;
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $userService = new UserService();
		$locale = $userService->getUserLocale($this->user->id);

		$orderType = strtolower($this->data['orderType']);
		$positionType = ucfirst(strtolower($this->data['positionType']));
		$symbol = strtolower($this->data['symbol']);
		$symbols = explode('/', $symbol);
		$coin = strtoupper($symbols[0]);
        $currency = strtoupper(isset($symbols[1]) ? $symbols[1] : '');
        $leverage = $this->data['leverage'];

		$price = $this->data['price'];

        $template = 'emails.future_order_trade_liquidated';
		$title = __('emails.future_order_trade_liquidated.title_create', ['APP_NAME' => config('app.name'), 'order_type' => $orderType], $locale);
		$antiPhishingCode = UserAntiPhishing::where([
			'is_active' => true,
			'user_id' => $this->user->id,
		])->first();

        return  $this->view($template)
            ->subject($title)
            ->to($this->user->email)
            ->with([
				'locale' => $locale,
                'email' => $this->user->email,
				'position_type' => $positionType,
				'leverage' => $leverage,
				'order_type' => $orderType,
				'date' => date('Y-m-d H:i:s', strtotime($this->data['time'])),
                'symbol' => strtoupper($symbol),
                'currency' => $currency,
                'coin' => $coin,
                'price' => BigNumber::new($price)->toString(),
                'anti_phishing_code' => $antiPhishingCode,
            ]);
    }
}
