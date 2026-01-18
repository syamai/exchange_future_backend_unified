module.exports = {
    apps: [
        {
            name: 'LAST_ETHBTC',
            script: 'php',
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:last_price ETHBTC',
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
            name: 'MARK_PRICE_ETHBTC',
            script: 'php',
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:impact_price ETHBTC',
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
            name: 'PEMIUM_ETHBTC',
            script: 'php',
            cron_restart: "* * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:premium_index ETHBTC',
            instances: 1,
            autorestart: false,
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
            name: 'PEMIUM_ETHBTC_2H',
            script: 'php',
            cron_restart: "0 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index ETHBTCPI2H ETHBTCPI 2h',
            instances: 1,
            autorestart: false,
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
            name: 'PEMIUM_ETHBTC_8H',
            script: 'php',
            cron_restart: "0 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index ETHBTCPI8H ETHBTCPI2H 8h',
            instances: 1,
            autorestart: false,
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
            name: 'CALCULATE_FUNDING_ETHBTC',
            script: 'php',
            cron_restart: "0 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:calculate_funding_rate ETHBTC',
            instances: 1,
            autorestart: false,
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
            name: 'DO_FUNDING_ETHBTC',
            script: 'php',
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:pay_funding ETHBTC',
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
