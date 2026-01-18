# Setup BitGo service for development environment

### Create Bitgo access token
In order to use BitGo API to send and receive cryptography currencies (BTC, ETH, ...), you need a BitGo access token:
- Create BitGo access token at: https://test.bitgo.com/settings#developerOptions, and add to your .env file
```
BITGO_TOKEN=your_token
```

### Run BitGo Express service
If you are using docker, you can ignore this step because Bitgo Express service is already started, otherwise you have to start it manually:
- Install BitGoJS: https://github.com/BitGo/BitGoJS/
- Run BitGo Express service in development mode
```
./bin/bitgo-express --debug --port 3080 --env test --bind localhost
```

### Create BitGo wallet
- Run BitGo Express service in development mode
- Create wallet for (test) BTC:
```
$ ACCESS_TOKEN="your access token"
$ LABEL="your wallet label"
$ PASSPHASE="your passphase"
$ COIN=tbtc

$ curl -X POST \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -d "{ \"label\": \"$LABEL\", \"passphrase\": \"$PASSPHASE\" }" \
    http://localhost:3080/api/v2/$COIN/wallet/generate
```
- Add wallet id and password you created in previous step to your .env file:
```
BITGO_BTC_WALLET_ID="your tbtc wallet id"
BITGO_BTC_WALLET_PASSWORD="your tbtc wallet password"
```
- Repeat previous steps for other currencies by change COIN parameter to: tbch, teth, txrp, tltc, then add wallet ids and passwords to .env file
```
BITGO_BCH_WALLET_ID="your tbch wallet id"
BITGO_BCH_WALLET_PASSWORD="your tbch wallet password"
BITGO_ETH_WALLET_ID="your teth wallet id"
BITGO_ETH_WALLET_PASSWORD="your teth wallet password"
BITGO_XRP_WALLET_ID="your txrp wallet id"
BITGO_XRP_WALLET_PASSWORD="your txrp wallet password"
BITGO_LTC_WALLET_ID="your tltc wallet id"
BITGO_LTC_WALLET_PASSWORD="your tltc wallet password"
```

### Add wallet webhook
In order to receive notification when new transactions are created, you need add a webhook for each currency
```
$ URL=http://your_server_address/api/webhook/bitgo
$ COIN=tbtc
$ WALLET="wallet id"
$ NO_CONFIMATION=1 // 1 is OK for development environment

$ curl -X POST \
-H "Content-Type: application/json" \
-H "Authorization: Bearer $ACCESS_TOKEN" \
-d "{ \"url\": \"$URL\", \"type\": \"transfer\", \"numConfirmations\": \"$NO_CONFIMATION\" }" \
https://test.bitgo.com/api/v2/$COIN/wallet/$WALLET/webhooks
```
- Repeat previous steps for other currencies: tbch, teth, txrp, tltc.
