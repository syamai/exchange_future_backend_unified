require('dotenv').config()
const log4js = require('log4js');
const logger = require('log4js').getLogger('BotApp');
var fs       = require('fs');
var request  = require('superagent')
const pm2    = require('pm2');


log4js.configure({
    appenders: {
        out: { type: 'console' },
    },
    categories: {
        default: { appenders: ['out'], level: 'info' }
            }
            });

            function createRedisClient()
            {
                        var redis = require('bluebird').promisifyAll(require('redis'));
                        redis = redis.createClient({port: process.env.OP_REDIS_PORT, host: process.env.OP_REDIS_HOST});
                        redis.select(1);
                        return redis;
            }

            var listenerClient = createRedisClient();
            listenerClient.on('message', (channel, data) => {
                    console.log(channel, JSON.stringify(data));

            });
            listenerClient.subscribe('MarginMatchingEngine');

            var redisClient = createRedisClient();
            var symbols = [];
            var checkingInterval = process.env.OP_CHECKING_INTERVAl || 3000;

            process.nextTick(function () {
                        getAllSymbols(function () {
                            removeOldProcessors().then(function () {
                                startAllProcessors(true);
                            });
                        });
            })

            async function startProcessor(symbol)
            {
                        var lastRunKey = 'margin_engine_last_run_' + symbol;
                        let lastRun = await redisClient.getAsync(lastRunKey);
                        let currentTime = await getRedisTime();

                        var diff = (currentTime - lastRun) / 1000;
                if (lastRun && diff < 10) {
                    console.log('Another process is running: ' + symbol + ', last run: ' + diff + 's ago. Wating ...');
                    setTimeout(function () {
                                  startProcessor(symbol);
                    }, 1000);
                } else {
                    console.log('Start processor symbol: ' + symbol + ', currentTime: ' + currentTime);

                    try {
                        await callPm2Function('start', {
                            name: 'margin_matching_engine_' + symbol,
                            script : 'php',
                            args: ['artisan', 'margin:process_order', symbol],
                        });
                    } catch (e) {
                        console.log('Start error: ' + symbol);
                    }
                }
            }

            async function getRedisTime()
            {
                    let data = await redisClient.timeAsync();
                    return data[0] * 1000 + Math.round(data[1] / 1000);
            }

            function runCommand(command, args, dataCallback, endCallback)
            {
                var spawn = require('child_process').spawn;
                var child = spawn(command, args);
                var self = this;
                child.stdout.on('data', function (buffer) {
                    dataCallback(self, buffer) });
                        child.stdout.on('end', endCallback);
            }

            function getAllSymbols(callback)
            {
                  request
                    .get(getUrl('/instrument/active'))
                    .end(function (err, res) {
                        if (err) {
                            logger.error(err)
                            return
                        }

                        symbols = res.body.data;
                        callback();
                    });
                  // symbols = [
                  //   { symbol: 'BTCUSD',},
                  //   { symbol: 'BTCU19', expiry: '2019-09-27 12:00:00'},
                  // ];
                  callback();
            }

            function startAllProcessors()
            {
                for (var i in symbols) {
                    (function (i) {
                        var symbol = symbols[i];
                        setTimeout(function () {
                                    startProcessor(symbol.symbol);
                        }, i * 100);
                    })(i);
                }
            }

            async function removeOldProcessors(callback)
            {
                for (let instrument of symbols) {
                    let name = 'margin_matching_engine_' + instrument.symbol;
                    console.log('Removing old processor: ' + name);

                    try {
                            await callPm2Function('delete', name);
                    } catch (e) {
                        console.log('Remove error: ' + name);
                    }
                }
            }

            function callPm2Function(func, params)
            {
                return new Promise((resolve, reject) => {
                    pm2.connect(function (err) {
                        if (err) {
                            reject(err);
                        }
                        pm2[func](params, function (err, apps) {
                            pm2.disconnect();
                            if (err) {
                                reject(err);
                            } else {
                                resolve();
                            }
                        });
                    });
                });
            }

            var getUrl = function (route) {
                return process.env.API_URL + '/api/v1/margin' + route;
            }