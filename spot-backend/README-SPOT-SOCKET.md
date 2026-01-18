****# Amanpuri Margin Websocket APIs

### WebSocket Connections

**Hosts**

Below is the hosts information
- TESTNET: `wss://socket-testnet.amanpuri.io:2083`
- PRODUCTION: `wss://socket.amanpuri.io:2053`

**Join Channel**

- Emit `subcribe` event to request join in the channel.
- With channel name like `private-App.User.{userId}`. `{userId}` can get from [this api](https://api-testnet.amanpuri.io/api/docs/#private-get-the-current-user-id).


##### Connection Example:
```
const io = require('socket.io-client');
const socket = io('wss://socket-testnet.amanpuri.io:2083');

const API_KEY = '';
const CHANNEL = 'private-App.User.1';
const EVENT = 'App\\Events\\BalanceUpdated';

socket.on('disconnect', function () {
    console.log('disconnected');
});
socket.on('reconnect', function () {
    console.log('reconnect');
});
socket.on('reconnect_error', function () {
    console.log('reconnect error');
});
socket.on('connect', function () {
    console.log('connected');
    socket.emit('subscribe', {
        channel: CHANNEL,
        auth: {
            headers: {'Authorization': `Bearer ${API_KEY}`}
        }
    }).on(EVENT, function(channel, data) {
        console.log(data);
    });
});
```



### WebSocket Streams

##### 1. Main Balance
- Return individual Main Balance updates.
- Private channel ```private-App.User.{userId}```
- Event ```App\\Events\\MainBalanceUpdated```

Received Payload:
```
{
  "data": {
    "id":6,
    "balance":"99.992499999",
    "unrealised_pnl":"-9.9985680828",
    "cross_balance":"99.992499999",
    "isolated_balance":"0",
    "cross_equity":"89.9939319161",
    "cross_margin":"0.1075",
    "order_margin":"0",
    "available_balance":"89.8864319161",
    "max_available_balance":"99.884999999",
    "manager_id":6,
    "owner_id":6,
    "is_mam":0
  },
  "socket": null
}
```



##### 2. Spot Balance
- Return individual Spot Exchange Balance updates.
- Private channel ```private-App.User.{userId}```
- Event ```App\\Events\\SpotBalanceUpdated```

Received Payload:
```
{
    "data": {
        "usd": {
            "balance": "7500.0000000000",
            "available_balance": "7480.0000000000",
            "usd_amount": "0.0000000000"
        }
    },
    "socket": null
}
```



##### 3. Airdrop Balance
- Return individual Airdrop Balance updates.
- Private channel ```private-App.User.{userId}```
- Event ```App\\Events\\AirdropBalanceUpdated```

Received Payload:
```
{
    "data": {
        "btc": {
            "balance": 0,
            "available_balance": 0
        },
        "bch": {
            "balance": 0,
            "available_balance": 0
        },
        "eth": {
            "balance": 0,
            "available_balance": 0
        },
        "xrp": {
            "balance": 0,
            "available_balance": 0
        },
        "amal": {
            "balance": "0.0000000000",
            "available_balance": "0.0000000000",
            "last_unlock_date": null,
            "balance_bonus": "0.0000000000",
            "available_balance_bonus": "0.0000000000"
        },
        "ltc": {
            "balance": 0,
            "available_balance": 0
        },
        "eos": {
            "balance": 0,
            "available_balance": 0
        },
        "ada": {
            "balance": 0,
            "available_balance": 0
        },
        "usdt": {
            "balance": 0,
            "available_balance": 0
        },
        "usd": {
            "balance": 0,
            "available_balance": 0
        }
    },
    "socket": null
}
```



##### 4. All Balance
- Return information of All Balance updates.
- Private channel ```private-App.User.{userId}```
- Event ```App\\Events\\BalanceUpdated```

Received Payload:
```
{
    "data": {
        "main": {
            "usd": {
                "balance": "2500.0000000000",
                "available_balance": "2500.0000000000",
                "usd_amount": "0.0000000000"
            }
        },
        "margin": {
            "margin": {
                "btc": {
                    "id": 534,
                    "balance": "0",
                    "unrealised_pnl": "0",
                    "cross_balance": "0",
                    "isolated_balance": "0",
                    "cross_equity": "0",
                    "cross_margin": "0",
                    "order_margin": "0",
                    "available_balance": "0",
                    "max_available_balance": "0",
                    "manager_id": null,
                    "owner_id": 1012,
                    "is_mam": 0,
                    "created_at": "2020-05-05 08:57:43",
                    "updated_at": "2020-05-20 02:06:36"
                },
                "amal": {
                    "balance": "10000.0000000000",
                    "available_balance": "10000.0000000000"
                }
            }
        },
        "mam": {
            "mam": {
                "btc": {
                    "balance": 0,
                    "available_balance": 0
                },
                "bch": {
                    "balance": 0,
                    "available_balance": 0
                },
                "eth": {
                    "balance": 0,
                    "available_balance": 0
                },
                "xrp": {
                    "balance": 0,
                    "available_balance": 0
                },
                "amal": {
                    "balance": 0,
                    "available_balance": 0
                },
                "ltc": {
                    "balance": 0,
                    "available_balance": 0
                },
                "eos": {
                    "balance": 0,
                    "available_balance": 0
                },
                "ada": {
                    "balance": 0,
                    "available_balance": 0
                },
                "usdt": {
                    "balance": 0,
                    "available_balance": 0
                },
                "usd": {
                    "balance": 0,
                    "available_balance": 0
                }
            }
        },
        "spot": {
            "usd": {
                "balance": "7500.0000000000",
                "available_balance": "7500.0000000000",
                "usd_amount": "0.0000000000"
            }
        },
        "airdrop": {
            "btc": {
                "balance": 0,
                "available_balance": 0
            },
            "bch": {
                "balance": 0,
                "available_balance": 0
            },
            "eth": {
                "balance": 0,
                "available_balance": 0
            },
            "xrp": {
                "balance": 0,
                "available_balance": 0
            },
            "amal": {
                "balance": "0.0000000000",
                "available_balance": "0.0000000000",
                "last_unlock_date": null,
                "balance_bonus": "0.0000000000",
                "available_balance_bonus": "0.0000000000"
            },
            "ltc": {
                "balance": 0,
                "available_balance": 0
            },
            "eos": {
                "balance": 0,
                "available_balance": 0
            },
            "ada": {
                "balance": 0,
                "available_balance": 0
            },
            "usdt": {
                "balance": 0,
                "available_balance": 0
            },
            "usd": {
                "balance": 0,
                "available_balance": 0
            }
        }
    },
    "socket": null
}
```

##### 5. Price Updated
- Returns price updates.
- Public channel ```App.Prices```
- Event ```App\\Events\\PricesUpdated```

Received Payload:
```
{
    "data": {
        "btc_eth": {
            "coin": "eth",
            "currency": "btc",
            "price": "0.0400000000",
            "previous_price": "0.0201000000",
            "change": "0",
            "last_24h_price": "0.0400000000",
            "volume": "0",
            "created_at": 1589959965136
        }
    },
    "socket": null
}
```




##### 6. Order List Updated
- Returns Order List Updated information
- Private channel ```private-App.User.{userId}```
- Event ```App\\Events\\OrderListUpdated```

Received Payload:
```
{
    "data": {
        "currency": "btc",
        "action": "matched"
    },
    "socket": null
}
```



##### 7. OrderBook Updated
- Returns OrderBook Updated information
- Public channel ```App.OrderBook```
- Event ```App\\Events\\OrderBookUpdated```

Received Payload:
```
{
    "data": {
        "currency": "btc",
        "coin": "eth",
        "tickerSize": "0.0000000100",
        "isFullOrderBook": true,
        "orderBook": {
            "buy": [{
                "count": 0,
                "quantity": "1.0000000000",
                "price": "0.0400000000"
            }, {
                "count": 0,
                "quantity": "10.0000000000",
                "price": "0.0201000000"
            }, {
                "count": 0,
                "quantity": "1.0000000000",
                "price": "0.0200000000"
            }, {
                "count": 0,
                "quantity": "0.0010000000",
                "price": "0.0000100000"
            }],
            "sell": [],
            "updatedAt": {
                "date": "2020-05-20 07:28:57.102356",
                "timezone_type": 3,
                "timezone": "UTC"
            },
            "meta": {
                "buy": {
                    "min": 0,
                    "max": 9223372036854776000
                },
                "sell": {
                    "min": 0,
                    "max": 9223372036854776000
                },
                "updated_at": 1589959737102
            }
        }
    },
    "socket": null
}
```


##### 8. Order Changed
- Returns Order Changed information
- User places an order on Order Book and order status changed 
- Private channel ```private-App.User.{userId}```
- Event ```App\\Events\\OrderChanged```

Received Payload:
```
{
    "data": {
        "order": {
            "id": 79094624,
            "original_id": null,
            "user_id": 1044,
            "email": "toanthang1988+70@gmail.com",
            "trade_type": "buy",
            "currency": "btc",
            "coin": "eth",
            "type": "limit",
            "ioc": null,
            "quantity": "50.0000000000",
            "price": "0.0400000000",
            "executed_quantity": "1.0000000000",
            "executed_price": "0.0400000000",
            "base_price": null,
            "stop_condition": null,
            "fee": "0.0015000000",
            "status": "executing",
            "created_at": 1589960597278,
            "updated_at": 1589960597278
        },
        "action": "matched",
        "message": null
    },
    "socket": null
}
```


##### 9. OrderTransactionCreated
- Returns Order Transaction Created information
- When order matched
- Public channel ```App.Orders```
- Event ```App\\Events\\OrderTransactionCreated```

Received Payload:
```
{
    "data": {
        "orderTransaction": {
            "buy_order_id": 79094620,
            "sell_order_id": 79094621,
            "quantity": "1",
            "price": "0.0400000000",
            "currency": "btc",
            "coin": "eth",
            "amount": "0.04",
            "btc_amount": 0,
            "status": "executed",
            "created_at": 1589959965136,
            "executed_date": {
                "date": "2020-05-20 07:32:45.135678",
                "timezone_type": 3,
                "timezone": "UTC"
            },
            "sell_fee": "0.00006",
            "buy_fee": "0.0015",
            "buyer_id": 1044,
            "seller_id": 1044,
            "transaction_type": "sell",
            "id": 1068360
        },
        "buyOrder": {
            "id": 79094620,
            "original_id": null,
            "user_id": 1044,
            "trade_type": "buy",
            "currency": "btc",
            "coin": "eth",
            "type": "limit",
            "ioc": null,
            "quantity": "1.0000000000",
            "price": "0.0400000000",
            "executed_quantity": "0.0000000000",
            "executed_price": "0.0000000000",
            "base_price": null,
            "stop_condition": null,
            "fee": "0.0000000000",
            "status": "pending",
            "created_at": 1589959736970,
            "updated_at": 1589959736970
        },
        "sellOrder": {
            "id": 79094621,
            "original_id": null,
            "user_id": 1044,
            "trade_type": "sell",
            "currency": "btc",
            "coin": "eth",
            "type": "limit",
            "ioc": null,
            "quantity": "1.0000000000",
            "price": "0.0400000000",
            "executed_quantity": "0.0000000000",
            "executed_price": "0.0000000000",
            "base_price": null,
            "stop_condition": null,
            "fee": "0.0000000000",
            "status": "pending",
            "created_at": 1589959964769,
            "updated_at": 1589959964769
        }
    },
    "socket": null
}
```



##### 10. Transaction Created
- Returns information of Transaction Created 
- When user create/accepted/rejected Withdrawal/Deposit request
- Private channel ```private-App.User.{userId}```
- Event ```App\\Events\\TransactionCreated```

Received Payload:
```
{
    "data": {
        "id": 2,
        "user_id": 1012,
        "amount": "100000000.0000000000",
        "fee": "0.0000000000",
        "bank_name": "Techcombank",
        "bank_branch": "Techcombank Hoang Cau",
        "account_name": "Admin Amanpuri",
        "account_no": "438914392445",
        "code": "r872wuaAPq",
        "created_at": 1589957478287,
        "updated_at": 1589957478287,
        "status": "success"
    },
    "socket": null
}
```




