module.exports = {
    apps: [
    {
        name: 'MAM_ROLLOVER_DAILY',
        script: 'php',
        args: 'artisan mam:rollover_daily',
        instances: 1,
        cron_restart: '0 0 * * *',
        autorestart: false,
        watch: false,
    },
    {
        name: 'MAM_ROLLOVER_MONTHLY',
        script: 'php',
        args: 'artisan mam:rollover_monthly',
        instances: 1,
        cron_restart: '1 0 1 * *',
        autorestart: false,
        watch: false,
    },
    ],
};
