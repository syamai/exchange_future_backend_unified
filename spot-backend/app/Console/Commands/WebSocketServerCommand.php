<?php

namespace App\Console\Commands;

use App\Services\WebSocket\SwooleWebSocketServer;
use Illuminate\Console\Command;

class WebSocketServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websocket:serve
                            {--host=0.0.0.0 : The host to bind to}
                            {--port=9502 : The port to listen on}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Swoole WebSocket server for real-time spot trading data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $host = $this->option('host');
        $port = (int) $this->option('port');

        $this->info("Starting Swoole WebSocket Server...");
        $this->info("Host: {$host}");
        $this->info("Port: {$port}");
        $this->newLine();

        $this->info("Channels available:");
        $this->line("  - orderbook:{symbol}  : Order book updates");
        $this->line("  - trades:{symbol}     : Trade executions");
        $this->line("  - ticker:{symbol}     : Price ticker updates");
        $this->line("  - kline:{symbol}:{interval} : Candlestick data");
        $this->line("  - user:{userId}       : User-specific updates (requires auth)");
        $this->newLine();

        $this->info("Client message format:");
        $this->line('  Subscribe:   {"action":"subscribe","channels":["orderbook:BTC/USDT","ticker:BTC/USDT"]}');
        $this->line('  Unsubscribe: {"action":"unsubscribe","channels":["orderbook:BTC/USDT"]}');
        $this->line('  Auth:        {"action":"auth","token":"your-api-token"}');
        $this->line('  Ping:        {"action":"ping"}');
        $this->newLine();

        try {
            $server = new SwooleWebSocketServer($host, $port);
            $server->start();
        } catch (\Exception $e) {
            $this->error("Failed to start WebSocket server: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
