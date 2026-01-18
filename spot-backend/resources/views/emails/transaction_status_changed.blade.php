@extends('emails.template')
@section('content')
    <div>
        <p style="margin-bottom:15px">
            {!! __('emails.deposit_withdraw_usd_alerts.hello', ['email' => $email], $locale) !!},
        </p>

        @if ($type == 'deposit')
            @if ($result == 'approved')
                <p style="margin-bottom:15px">
                    {{ __('emails.deposit_withdraw_usd_alerts.deposit.approved.line_1', [], $locale) }}<br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.deposit.approved.line_2', [], $locale) }}
                </p>
                <p style="margin-bottom:0">
                    {{ __('emails.deposit_withdraw_usd_alerts.deposit.approved.amount', [], $locale) }}: <strong>{{ $amount }} {{ $coin }}</strong><br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.time', [], $locale) }}: {{ $date }} (UTC)
                </p>
            @else
                <p style="margin-bottom:15px">
                    {{ __('emails.deposit_withdraw_usd_alerts.deposit.rejected.line_1', [], $locale) }}<br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.deposit.rejected.line_2', [], $locale) }}
                </p>
                <p style="margin-bottom:0">
                    {{ __('emails.deposit_withdraw_usd_alerts.deposit.rejected.amount', [], $locale) }}: <strong>{{ $amount }} {{ $coin }}</strong><br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.time', [], $locale) }}: {{ $date }} (UTC)
                </p>
            @endif

        @else
            @if ($result == 'approved')
                <p style="margin-bottom:15px">
                    {{ __('emails.deposit_withdraw_usd_alerts.withdrawal.approved.line_1', [], $locale) }}<br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.withdrawal.approved.amount', [], $locale) }}: <strong>{{ $amount }} {{ $coin }}</strong><br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.withdrawal.approved.account_bank', [], $locale) }}: {{ $transaction->bank_name ?? '-' }}<br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.withdrawal.approved.account_number', [], $locale) }}: {{ $transaction->account_no ?? '-' }}<br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.withdrawal.approved.account_holder', [], $locale) }}: {{ $transaction->account_name ?? '-' }}<br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.time', [], $locale) }}: {{ $date }} (UTC)<br/>
                </p>
            @else
                <p style="margin-bottom:15px">
                    {{ __('emails.deposit_withdraw_usd_alerts.withdrawal.rejected.line_1', [], $locale) }}<br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.withdrawal.rejected.line_2', [], $locale) }}<br/>
                </p>
                <p>
                    {{ __('emails.deposit_withdraw_usd_alerts.withdrawal.rejected.amount', [], $locale) }}: <strong>{{ $amount }} {{ $coin }}</strong><br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.withdrawal.rejected.account_bank', [], $locale) }}: {{ $transaction->bank_name ?? '-' }}<br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.withdrawal.rejected.account_number', [], $locale) }}: {{ $transaction->account_no ?? '-' }}<br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.withdrawal.rejected.account_holder', [], $locale) }}: {{ $transaction->account_name ?? '-' }}<br/>
                    {{ __('emails.deposit_withdraw_usd_alerts.time', [], $locale) }}: {{ $date }} (UTC)
                </p>
            @endif

        @endif

        <p style="margin-bottom:15px; margin-top: 5px;">

        </p>
    </div>
@endsection
