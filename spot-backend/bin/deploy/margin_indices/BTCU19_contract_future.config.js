module.exports = {
    apps: [
        {
            name: 'LAST_BTCU19',
            script: 'php',
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:last_price BTCU19',
            instances: 1,
            autorestart: true,
            watch: false,
            max_memory_restart: '100M',
            env: {
                NODE_ENV: 'development'
            },
            env_production: {
                NODE_ENV: 'production'
            }
    },
        {
            name: 'MARK_PRICE_BTCU19',
            script: 'php',
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:impact_price BTCU19',
            instances: 1,
            autorestart: true,
            watch: false,
            max_memory_restart: '100M',
            env: {
                NODE_ENV: 'development'
            },
            env_production: {
                NODE_ENV: 'production'
            }
    },
        {
            name: 'TRIGGER_SETTLE_BTCU19',
            script: 'php',
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:settle_create_settlement BTCU19',
            instances: 1,
            autorestart: true,
            watch: false,
            max_memory_restart: '100M',
            env: {
                NODE_ENV: 'development'
            },
            env_production: {
                NODE_ENV: 'production'
            }
    },
        {
            name: 'CLEAN_ORDER_BTCU19',
            script: 'php',
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:settle_cancel_orders BTCU19',
            instances: 1,
            autorestart: true,
            watch: false,
            max_memory_restart: '100M',
            env: {
                NODE_ENV: 'development'
            },
            env_production: {
                NODE_ENV: 'production'
            }
    },
        {
            name: 'DO_SETTLE_BTCU19',
            script: 'php',
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:settle_close_positions BTCU19',
            instances: 1,
            autorestart: true,
            watch: false,
            max_memory_restart: '100M',
            env: {
                NODE_ENV: 'development'
            },
            env_production: {
                NODE_ENV: 'production'
            }
    },
    ],

    deploy: {
        production: {
            // user: 'node',
            // host: '212.83.163.1',
            // ref: 'origin/master',
            // repo: 'git@github.com:repo.git',
            // path: '/var/www/production',
            // 'post-deploy': 'npm install && pm2 reload price_index.js --env production'
        }
    }
};
