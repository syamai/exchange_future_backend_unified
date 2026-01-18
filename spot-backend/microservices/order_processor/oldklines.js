require('dotenv').config();
const log4js = require('log4js');
const logger = require('log4js').getLogger('BotApp');
var fs = require('fs');
var request = require('superagent');

var readlines = [];

log4js.configure({
    appenders: {
        out: {type: 'console'},
    },
    categories: {
        default: {appenders: ['out'], level: 'info'}
    }
});

function createRedisClient() {
    var redis = require("redis");
    if (process.env.REDIS_SCHEME === 'tls') {
        return redis.createClient({
            port: process.env.OP_REDIS_PORT,
            host: process.env.OP_REDIS_HOST,
            tls: true
        });
    }
    return redis.createClient({port: process.env.OP_REDIS_PORT, host: process.env.OP_REDIS_HOST});
}

var redisClient = createRedisClient();
redisClient.select(process.env.REDIS_DB || 1);
var subscribeRedisClient = createRedisClient();
var symbols = [];
var checkingInterval = process.env.OP_CHECKING_INTERVAl_KLINE_OLD || 3000;

subscribeRedisClient.subscribe('StartFakeDataProcessor');

subscribeRedisClient.on('message', (channel, data) => {
    console.log(channel, JSON.stringify(data));
    if (channel === 'StartFakeDataProcessor') {
        const pair = JSON.parse(data);
        console.log('StartFakeDataProcessor', pair);
        startProcessorIfNeed(pair.currency, pair.coin, true);
    }
});

process.nextTick(function () {
    getAllSymbols(function () {
        startAllProcessors(true);
    });
});

function startProcessor(currency, coin) {
    var currentTime = (new Date()).getTime();

    console.log('Start processor old kline currency: ' + currency + ', coin: ' + coin + ', currentTime: ' + currentTime);
    setTimeout(function () {
        runCommand(
            'php',
            ['artisan', 'spot:kline_old_worker_symbol', currency, coin],
            function () {
                console.log('Finish: ' + currency + '-' + coin);
            }
        );
    }, 0);
}

function startProcessorIfNeed(currency, coin, showError, startIfKeyNotFount) {
    var redisKey = 'last_run_kline_old_symbol_' + currency + '_' + coin;
    redisClient.get(redisKey, function (error, reply) {
        if (!reply && !startIfKeyNotFount) {
            console.log('Key not found: ' + redisKey);
            console.log('Sleeping ' + checkingInterval + 'ms before starting processor: ' + coin + '/' + currency);
            setTimeout(function () {
                startProcessorIfNeed(currency, coin, showError, true);
            }, checkingInterval);
            return;
        }
        var lastRun = parseInt(reply || 0);
        var currentTime = (new Date()).getTime();

        if (currentTime > parseInt(checkingInterval) + lastRun) {
            startProcessor(currency, coin);
        } else {
            if (showError) {
                console.error('Processor old kline for symbol: ' + coin + '/' + currency + ' already running');
                var command = 'redis-cli -h ' + process.env.REDIS_HOST + ' -p ' + process.env.REDIS_PORT + ' del ' + redisKey;
                console.error('otherwise, clear processor status by run command: "' + command + '"');
            }
        }
    });
}

function runCommand(command, args, endCallback) {
    var spawn = require('child_process').spawn;
    var child = spawn(command, args);
    var self = this;
    child.stdout.on('end', endCallback);

    var readline = require('readline');
    readline.createInterface({
        input: child.stdout,
        terminal: false
    }).on('line', function (line) {
        console.log(line);
    });
    readline.createInterface({
        input: child.stderr,
        terminal: false
    }).on('line', function (line) {
        console.log(line);
    });
    readlines[JSON.stringify([command, args])] = readline;
}

function getAllSymbols(callback) {
    const configPairs = process.env.MATCHING_SYMBOLS || '';
    if (configPairs) {
        const pairs = configPairs.split(",");
        for (let [k, symbol] of Object.entries(pairs)) {
            let pair = symbol.split('_')
            if (pair.length > 1) {
                let currency = pair[1].trim()
                let coin = pair[0].trim()
                if (currency && coin) {
                    symbols.push({currency,coin })
                }

            }
        }
        callback();
        return;
    }

    request
        .get(getUrl('/symbols'))
        .end(function (err, res) {
            if (err) {
                logger.error(err);
                return
            }

            symbols = res.body.data;
            callback();
        })
}

function startAllProcessors(showError) {
    var currentTime = new Date();
    console.log('Checking processor status at ' + currentTime.getTime() + '(' + currentTime + ')');
    for (var i in symbols) {
        (function (i) {
            var pair = symbols[i];
            setTimeout(function () {
                startProcessorIfNeed(pair.currency, pair.coin, showError);
            }, i * 100);
        })(i);
    }

    setTimeout(function () {
        startAllProcessors(false);
    }, checkingInterval);
}

var getUrl = function (route) {
    return process.env.API_URL + '/api' + route;
};
