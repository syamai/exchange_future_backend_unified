module.exports = {
    apps: [
    {
        name: 'ENTRY_UNREALISE_PNL_UPDATE',
        script: 'php',
        args: 'artisan margin:entry_unrealised_pnl_update',
        instances: 1,
        cron_restart: '0 3 * * *',
        autorestart: false,
        watch: false,
    }
    ],
};
