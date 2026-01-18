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
        return redis.createClient({port: 6379, host: 'redis-drx-spot-nzqjby.serverless.apse1.cache.amazonaws.com',tls: true});
    }
    return redis.createClient({port: 6379, host: 'redis-drx-spot-nzqjby.serverless.apse1.cache.amazonaws.com'});
}

var redisClient = createRedisClient();
redisClient.select(process.env.REDIS_DB || 0);
console.log('lanvotest', redisClient);
console.log('lanvotest:set');
redisClient.set('lanvotest', 'Hello from Redis!' + (new Date()).getTime());
console.log('lanvotest', redisClient.get('lanvotest'));
