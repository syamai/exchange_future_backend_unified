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
var checkingInterval = process.env.OP_CHECKING_INTERVAl_FAKE || 30000;

subscribeRedisClient.subscribe('StartFakeDataProcessor');

subscribeRedisClient.on('message', (channel, data) => {
    console.log(channel, JSON.stringify(data));
    if (channel === 'StartFakeDataProcessor') {
        const pair = JSON.parse(data);
        console.log('StartFakeDataProcessor', pair);
        startProcessorIfNeed(pair.type);
    }
});

process.nextTick(function () {
    getAllSymbols(function () {
        startAllProcessors(true);
    });
});

function startProcessor(type, currency, coin) {
    var currentTime = (new Date()).getTime();

    console.log('Start processor spot command result type: ' + type + ' - ' + currency + ', coin: ' + coin + ', currentTime: ' + currentTime);
    setTimeout(function () {
        runCommand(
            'php',
            ['artisan', 'spot:process_command_result', type, currency, coin],
            function () {
                console.log('Finish: ' + type);
            }
        );
    }, 0);
}

function startProcessorIfNeed(type, currency, coin, showError, startIfKeyNotFount) {
    var redisKey = 'process_spot_command_result_me_' + type;
    if (currency && coin) {
        redisKey = 'process_spot_command_result_me_' + type + '_' + currency + '_' + coin;
    }
    redisClient.get(redisKey, function (error, reply) {
        if (!reply && !startIfKeyNotFount) {
            console.log('Key not found: ' + redisKey);
            console.log('Sleeping ' + checkingInterval + 'ms before starting processor: ' + type + ' - ' + coin + '/' + currency);
            setTimeout(function () {
                startProcessorIfNeed(type, currency, coin, showError, true);
            }, checkingInterval);
            return;
        }
        var lastRun = parseInt(reply || 0);
        var currentTime = (new Date()).getTime();

        if (currentTime > parseInt(checkingInterval) + lastRun) {
            startProcessor(type, currency, coin);
        } else {
            if (showError) {
                console.error('Processor for type: ' + type + ' - ' + coin + '/' + currency + ' already running');
                var command = 'redis-cli -h ' + process.env.REDIS_HOST + ' -p ' + process.env.REDIS_PORT + ' del ' + redisKey;
                console.error('otherwise, clear processor status by run command: "' + command + '"');
            }
        }
    });
}

function runCommand(command, args, endCallback) {
    var currentTime = (new Date()).getTime();
    console.log('runCommand - currentTime: ' + currentTime);

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
    const configTypes = process.env.MATCHING_JAVA_TYPE_RESULT || 'order,cancel,deposit,withdrawal';
    const configPairs = process.env.MATCHING_JAVA_SYMBOLS || '';
    if (configPairs) {
        if (configTypes) {
            const types = configTypes.split(",");
            for (let [k, typevalue] of Object.entries(types)) {
                let type = typevalue.trim()
                let coin = '';
                let currency = '';
                if (type) {
                    if (type == 'cancel' || type == 'order') {

                        if (configPairs) {
                            const pairs = configPairs.split(",");
                            for (let [k, symbol] of Object.entries(pairs)) {
                                let pair = symbol.split('_')
                                if (pair.length > 1) {
                                    let currency = pair[1].trim()
                                    let coin = pair[0].trim()
                                    if (currency && coin) {
                                        symbols.push({type, currency,coin })
                                    }

                                }
                            }
                        } else {
                            console.log("lanvo");
                            //symbols.push({type, currency, coin})
                        }
                    } else {
                        symbols.push({type, currency, coin})
                    }
                }
            }
            callback();
            return;
        }
    } else {
        if (configTypes) {
            request
                .get(getUrl('/symbols'))
                .end(function (err, res) {
                    if (err) {
                        logger.error(err);
                        return
                    }

                    const resSymbols = res.body.data;
                    const types = configTypes.split(",");
                    for (let [k, typevalue] of Object.entries(types)) {
                        let type = typevalue.trim()
                        let coin = '';
                        let currency = '';
                        if (type) {
                            if (type == 'cancel' || type == 'order') {
                                for (let [k, symbol] of Object.entries(resSymbols)) {
                                    if (symbol.is_enable) {
                                        coin = symbol.coin;
                                        currency = symbol.currency;
                                        if (currency && coin) {
                                            symbols.push({type, currency, coin})
                                        }
                                    }
                                }
                            } else {
                                symbols.push({type, currency, coin})
                            }
                        }
                    }

                    //console.log("symbols", symbols)
                    callback();
                })
        }
    }
}

function startAllProcessors(showError) {
    var currentTime = new Date();
    console.log('Checking processor status at ' + currentTime.getTime() + '(' + currentTime + ')');
    for (var i in symbols) {
        (function (i) {
            var pair = symbols[i];
            setTimeout(function () {
                startProcessorIfNeed(pair.type, pair.currency, pair.coin, showError);
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
