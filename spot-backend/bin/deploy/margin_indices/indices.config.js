module.exports = {
    apps: [
        // BTC
        {
            //BTC from Okex
            name: 'OKEX_BTC',
            script: 'php',

            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:start_clone_price BTC OKEX',
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
            name: 'AMI_BTC',
            script: 'php',

            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:start_clone_price BTC AMI',
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
            name: 'BTC',
            script: 'php',
            cron_restart: "* * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index BTC AMI_BTC 1m',
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
            name: 'BTC30M',
            script: 'php',
            cron_restart: "0,30 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index BTC30M BTC 30m',
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
            name: 'BTCBON',
            script: 'php',
            cron_restart: "* * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:constant_index BTCBON 0.0003',
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
            name: 'BTCBON2H',
            script: 'php',
            cron_restart: "0 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index BTCBON2H BTCBON 2h',
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
            name: 'BTCBON8H',
            script: 'php',
            cron_restart: "0 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index BTCBON8H BTCBON2H 8h',
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

        //ETH
        {
            name: 'OKEX_ETH',
            script: 'php',

            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:start_clone_price ETH OKEX',
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
            name: 'AMI_ETH',
            script: 'php',

            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:start_clone_price ETH AMI',
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
            name: 'ETH',
            script: 'php',
            cron_restart: "* * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index ETH AMI_ETH 1m',
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
            name: 'BETH30M',
            script: 'php',
            cron_restart: "0,30 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index ETH30M ETH 30m',
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
            name: 'ETHBON',
            script: 'php',
            cron_restart: "* * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:constant_index ETHBON 0.0003',
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
            name: 'ETHBON2H',
            script: 'php',
            cron_restart: "0 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index ETHBON2H ETHBON 2h',
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
            name: 'ETHBON8H',
            script: 'php',
            cron_restart: "0 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index ETHBON8H ETHBON2H 8h',
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

    // USD

        {
            name: 'USDBON',
            script: 'php',
            cron_restart: "* * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:constant_index USDBON 0.0006',
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
            name: 'USDBON2H',
            script: 'php',
            cron_restart: "0 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index USDBON2H USDBON 2h',
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
            name: 'USDBON8H',
            script: 'php',
            cron_restart: "0 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index USDBON8H USDBON2H 8h',
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
            //BTC from Okex
            name: 'OKEX_ETH_BTC',
            script: 'php',

            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:start_clone_price ETH_BTC OKEX',
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
            name: 'AMI_ETH_BTC',
            script: 'php',

            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:start_clone_price ETH_BTC AMI',
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
            name: 'ETH_BTC',
            script: 'php',
            cron_restart: "* * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index ETH_BTC AMI_ETH_BTC 1m',
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
            name: 'ETH_BTC_30M',
            script: 'php',
            cron_restart: "0,30 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index ETH_BTC30M ETH_BTC 30m',
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

        // ADA
        {
            // ADA from BMI
            name: 'BMI_ADA',
            script: 'php',

            args: 'artisan margin:start_clone_price ADA BMI',
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
            name: 'AMI_ADA',
            script: 'php',

            args: 'artisan margin:start_clone_price ADA AMI',
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
            name: 'ADA',
            script: 'php',
            cron_restart: "* * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index ADA AMI_ADA 1m',
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
            name: 'ADA30M',
            script: 'php',
            cron_restart: "0,30 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index ADA30M ADA 30m',
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

        // BCH
        {
            // BCH from BMI
            name: 'BMI_BCH',
            script: 'php',

            args: 'artisan margin:start_clone_price BCH BMI',
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
            name: 'AMI_BCH',
            script: 'php',

            args: 'artisan margin:start_clone_price BCH AMI',
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
            name: 'BCH',
            script: 'php',
            cron_restart: "* * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index BCH AMI_BCH 1m',
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
            name: 'BCH30M',
            script: 'php',
            cron_restart: "0,30 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index BCH30M BCH 30m',
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

        // EOS
        {
            // EOS from BMI
            name: 'BMI_EOS',
            script: 'php',

            args: 'artisan margin:start_clone_price EOS BMI',
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
            name: 'AMI_EOS',
            script: 'php',

            args: 'artisan margin:start_clone_price EOS AMI',
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
            name: 'EOS',
            script: 'php',
            cron_restart: "* * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index EOS AMI_EOS 1m',
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
            name: 'EOS30M',
            script: 'php',
            cron_restart: "0,30 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index EOS30M EOS 30m',
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

        // LTC
        {
            // LTC from BMI
            name: 'BMI_LTC',
            script: 'php',

            args: 'artisan margin:start_clone_price LTC BMI',
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
            name: 'AMI_LTC',
            script: 'php',

            args: 'artisan margin:start_clone_price LTC AMI',
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
            name: 'LTC',
            script: 'php',
            cron_restart: "* * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index LTC AMI_LTC 1m',
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
            name: 'LTC30M',
            script: 'php',
            cron_restart: "0,30 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index LTC30M LTC 30m',
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

        // TRX
        {
            // TRX from BMI
            name: 'BMI_TRX',
            script: 'php',

            args: 'artisan margin:start_clone_price TRX BMI',
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
            name: 'AMI_TRX',
            script: 'php',

            args: 'artisan margin:start_clone_price TRX AMI',
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
            name: 'TRX',
            script: 'php',
            cron_restart: "* * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index TRX AMI_TRX 1m',
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
            name: 'TRX30M',
            script: 'php',
            cron_restart: "0,30 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index TRX30M TRX 30m',
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

        // XRP
        {
            // XRP from BMI
            name: 'BMI_XRP',
            script: 'php',

            args: 'artisan margin:start_clone_price XRP BMI',
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
            name: 'AMI_XRP',
            script: 'php',

            args: 'artisan margin:start_clone_price XRP AMI',
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
            name: 'XRP',
            script: 'php',
            cron_restart: "* * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index XRP AMI_XRP 1m',
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
            name: 'XRP30M',
            script: 'php',
            cron_restart: "0,30 * * * *",
            // Options reference: https://pm2.io/doc/en/runtime/reference/ecosystem-file/
            args: 'artisan margin:summary_index XRP30M XRP 30m',
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
