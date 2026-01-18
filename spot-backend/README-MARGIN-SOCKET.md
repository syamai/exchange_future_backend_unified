# Amanpuri Margin Websocket APIs

### WebSocket Connections

**Hosts**

Below is the hosts information
- TESTNET: `wss://socket-testnet.amanpuri.io:2083`

**Join Channel**

- Emit `subcribe` event to request join in the channel.
- With channel name like `private-App.User.{userId}`. `{userId}` can get from [this api](https://api-testnet.amanpuri.io/api/docs/#private-get-the-current-user-id).
- With channel name like `App.(...).{symbol}`. `{symbol}` can get from [this api](https://api-testnet.amanpuri.io/api/docs/#public-get-all-instruments).

##### Connection Example:
```
const io = require('socket.io-client');
const socket = io('wss://socket-testnet.amanpuri.io:2083');

const API_KEY = '';
const CHANNEL = 'private-App.User.1';
const EVENT = 'App\\Events\\MarginBalanceUpdated';

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

**Private WebSocket**

##### 1. Margin Balance
- Return individual margin balance updates.
- Private channel ```private-App.User.{userId}```
- Event ```App\\Events\\MarginBalanceUpdated```

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

##### 2. Position
- Return individual position updates.
- Private channel ```private-App.User.{userId}```
- Event ```App\\Events\\MarginPositionUpdated```

Received Payload:
```
{
  "data": {
    "id": 190,
    "account_id": 6,
    "owner_email": "bot6@gmail.com",
    "manager_email": "bot6@gmail.com",
    "symbol": "BTCH20",
    "leverage": "66.6666666667",
    "unrealised_pnl": "-9.998568082833920",
    "current_qty": -10,
    "risk_limit": "100.0000000000",
    "risk_value": "10.0000000000",
    "init_margin": "0.157500000000000",
    "maint_margin": "0.057500000000000",
    "extra_margin": "0.000000000000000",
    "required_init_margin_percent": "0.0150000000",
    "required_maint_margin_percent": "0.0050000000",
    "liquidation_price": "1000000.0000000000",
    "bankrupt_price": "1000000.0000000000",
    "entry_price": "1.0000000000",
    "entry_value": "10.000000000000000",
    "open_order_buy_qty": "3.0000000000",
    "open_order_sell_qty": "0.0000000000",
    "open_order_buy_value": "3.000000000000000",
    "open_order_sell_value": "0.000000000000000",
    "multiplier": "-1.0000000000",
    "liquidation_progress": 0,
    "liquidation_order_id": null,
    "is_cross": 1,
    "pnl_ranking": "-0.9928741643",
  },
  "socket": null
}
```

##### 3. Margin Order
- Returns individual margin order updates.
- Private channel ```private-App.User.{userId}```
- Event ```App\\Events\\MarginOrderUpdated```

Received Payload:
```
{
  "data": {
    "action": "created",
    "order": {
      "account_id": 6,
      "instrument_symbol": "BTCH20",
      "owner_email": "bot6@gmail.com",
      "manager_email": "bot6@gmail.com",
      "side": "buy",
      "type": "limit",
      "stop_type": null,
      "quantity": "1",
      "remaining": "1",
      "price": "1",
      "stop_price": null,
      "stop_condition": null,
      "trigger": null,
      "trail_value": null,
      "is_post_only": false,
      "is_hidden": false,
      "is_reduce_only": false,
      "time_in_force": "gtc",
      "status": "new",
      "note": null,
      "created_at": 1577950187272,
      "updated_at": 1577950187272,
      "id": 214145
    }
  },
  "socket": null
}
```
**Public WebSocket**

##### 4. Margin Orderbook
- Returns margin orderbook updates.
- Public channel ```App.User.MarginOrderbook.{symbol}```
- Event ```App\\Events\\MarginOrderbookUpdated```

Received Payload:
```
{
  "data": {
    "buy":[
      { 
        "quantity": "1",
        "count": "1",
        "price": "7000.0000000000"
      }
    ],
    "sell": [],
    "meta": {
      "updated_at": 1577953591903,
      "prev_updated_at": "1577953588468"
    }
  },
  "socket":null
}
```

##### 5. Margin Trade
- Returns margin trade updates.
- Public channel ```App.MarginTrade.{symbol}```
- Event ```App\\Events\\MarginTradesCreated```

Received Payload:
```
{
  "data": [
    {
      "buy_order_id":8,
      "sell_order_id":9,
      "instrument_symbol":"BTCUSD",
      "price":"7000.0000000000",
      "quantity":"1.0000000000",
      "amount":"7000",
      "trade_type":"sell",
      "buy_account_id":"2",
      "sell_account_id":"2",
      "buy_owner_email":"bot2@gmail.com",
      "buy_manager_email":"bot2@gmail.com",
      "sell_owner_email":"bot2@gmail.com",
      "sell_manager_email":"bot2@gmail.com",
      "created_at":1577953794012,
      "buy_fee":"-0.000000035714286",
      "sell_fee":"0.000000107142857"
    }
  ],
  "socket":null
}
```

##### 6. Margin Instrument Extra Information
- Return margin instrument extra information updates.
- Public channel ```App.Instrument```
- Event ```App\\Events\\InstrumentExtraInformationsUpdated```

Received Payload:
```
{
  "data": {
    "symbol": "BTCUSD",
    "data": {
      "impact_ask_price": 0,
      "ask_price": null,
      "impact_bid_price": 0,
      "bid_price": null,
      "impact_mid_price": "0"
    }
  },
  "socket": null
}
```

##### 7. Margin Index
- Return margin index updates.
- Public channel ```App.Index```
- Event ```App\\Events\\MarginIndexUpdated```

Received Payload:
```
{
  "data": {
    "id": 110,
    "symbol": "ETH",
    "value": "129.2883218352",
    "created_at": "2020-01-02 08:20:00",
    "updated_at": "2020-01-02 08:20:00"
  },
  "socket": null
}
```