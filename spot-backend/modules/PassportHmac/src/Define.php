<?php


namespace PassportHmac;

class Define
{
    const READ = '1';
    const TRADE = '2';
    const WITHDRAW = '3';
    const READ_TRADE_WITHDRAW = '123';
    const READ_WITHDRAW = '13';
    const READ_TRADE = '12';
    const TRADE_WITHDRAW = '23';
    const FUTURE_TRADING = '4';
    const READ_FUTURE_TRADING = '14';
    const READ_TRADE_FUTURE_TRADING = '124';
    const READ_WITHDRAW_FUTURE_TRADING = '134';
    const TRADE_FUTURE_TRADING = '24';
    const TRADE_WITHDRAW_FUTURE_TRADING = '234';
    const WITHDRAW_FUTURE_TRADING = '34';
    const ALL_PERMS = '1234';

    const SCOPES = [
        self::READ, self::TRADE, self::WITHDRAW, self::READ_TRADE_WITHDRAW, self::READ_WITHDRAW, self::READ_TRADE, self::TRADE_WITHDRAW, self::FUTURE_TRADING, self::READ_FUTURE_TRADING, self::READ_TRADE_FUTURE_TRADING, self::READ_WITHDRAW_FUTURE_TRADING, self::TRADE_FUTURE_TRADING, self::TRADE_WITHDRAW_FUTURE_TRADING, self::WITHDRAW_FUTURE_TRADING, self::ALL_PERMS
    ];

    const SCOPE_GROUP = [
        self::READ => [self::READ, self::READ_WITHDRAW, self::READ_TRADE, self::READ_TRADE_WITHDRAW, self::ALL_PERMS],
        self::TRADE => [self::TRADE, self::TRADE_WITHDRAW, self::READ_TRADE, self::READ_TRADE_WITHDRAW, self::ALL_PERMS],
        self::WITHDRAW => [self::WITHDRAW, self::TRADE_WITHDRAW, self::READ_WITHDRAW, self::READ_TRADE_WITHDRAW, self::ALL_PERMS],
    ];

    const TOKENS_CAN = [
        self::READ => 'READ',
        self::TRADE => 'TRADE',
        self::WITHDRAW => 'WITHDRAW',
        self::READ_TRADE_WITHDRAW => 'READ TRADE WITHDRAW',
        self::READ_WITHDRAW => 'READ WITHDRAW',
        self::READ_TRADE => 'READ TRADE',
        self::TRADE_WITHDRAW => 'TRADE WITHDRAW',
        self::FUTURE_TRADING => 'FUTURE TRADING',
        self::READ_FUTURE_TRADING => 'READ FUTURE TRADING',
        self::READ_TRADE_FUTURE_TRADING => 'READ TRADE FUTURE TRADING',
        self::READ_WITHDRAW_FUTURE_TRADING => 'READ WITHDRAW FUTURE TRADING',
        self::TRADE_FUTURE_TRADING => 'TRADE FUTURE TRADING',
        self::TRADE_WITHDRAW_FUTURE_TRADING => 'TRADE WITHDRAW FUTURE TRADING',
        self::WITHDRAW_FUTURE_TRADING => 'WITHDRAW FUTURE TRADING',
        self::ALL_PERMS => 'ALL',
    ];

    const LIST_SCOPE = [
        self::READ => 'READ',
        self::TRADE => 'TRADE',
        self::WITHDRAW => 'WITHDRAW',
        self::FUTURE_TRADING => 'FUTURE_TRADING',
    ];

    const ROUTE_GROUP = [
        self::TRADE => [
            'orders.cancel-all',
            'orders.cancel-by-type',
            'orders.cancel',
            'orders.store',
            'margin.trade'
        ],
        self::WITHDRAW => [
            'transfer-balance',
            'withdraw',
            'verify.withdraw'
        ]
    ];
}
