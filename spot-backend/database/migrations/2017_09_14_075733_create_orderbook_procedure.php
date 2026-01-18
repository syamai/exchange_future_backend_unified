<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateOrderbookProcedure extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS update_orderbook_groups');
        DB::unprepared('DROP PROCEDURE IF EXISTS update_orderbook_group');
        DB::unprepared('DROP PROCEDURE IF EXISTS update_user_orderbook_groups');
        DB::unprepared('DROP PROCEDURE IF EXISTS update_user_orderbook_group');

        DB::unprepared("
            CREATE PROCEDURE update_orderbook_groups(trade_type VARCHAR(255), currency VARCHAR(255), coin VARCHAR(255),
                quantity decimal(30, 10), count int,
                price1 decimal(30, 10), ticker1 decimal(30, 10), price2 decimal(30, 10), ticker2 decimal(30, 10),
                price3 decimal(30, 10), ticker3 decimal(30, 10), price4 decimal(30, 10), ticker4 decimal(30, 10))
            BEGIN
                DECLARE row_count INT DEFAULT 0;
                DECLARE updated_at BIGINT DEFAULT 0;
                SELECT ROUND(UNIX_TIMESTAMP(CURTIME(4)) * 1000) INTO updated_at;

                CALL update_orderbook_group(trade_type, currency, coin, quantity, count, price1, ticker1, updated_at);
                CALL update_orderbook_group(trade_type, currency, coin, quantity, count, price2, ticker2, updated_at);
                CALL update_orderbook_group(trade_type, currency, coin, quantity, count, price3, ticker3, updated_at);
                CALL update_orderbook_group(trade_type, currency, coin, quantity, count, price4, ticker4, updated_at);
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE update_orderbook_group(trade_type VARCHAR(255), currency VARCHAR(255), coin VARCHAR(255),
                quantity decimal(30, 10), count int, price decimal(30, 10), ticker decimal(30, 10), updated_at BIGINT)
            BEGIN
                DECLARE row_count INT DEFAULT 0;

                IF ticker > 0 THEN
                    UPDATE orderbooks SET orderbooks.quantity = orderbooks.quantity + quantity,
                        orderbooks.count = orderbooks.count + count, orderbooks.updated_at = updated_at
                    WHERE orderbooks.trade_type = trade_type AND orderbooks.currency = currency
                        AND orderbooks.coin = coin AND orderbooks.price = price AND orderbooks.ticker = ticker;
                    SELECT ROW_COUNT() INTO row_count;
                    IF row_count = 0 THEN
                        INSERT INTO orderbooks (`trade_type`, `currency`, `coin`, `price`, `ticker`, `quantity`, `count`, `updated_at`)
                            VALUES(trade_type, currency, coin, price, ticker, quantity, count, updated_at)
                            ON DUPLICATE KEY UPDATE orderbooks.quantity = orderbooks.quantity + quantity,
                                orderbooks.count = orderbooks.count + count, orderbooks.updated_at = updated_at;
                    END IF;
                END IF;
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE update_user_orderbook_groups(
                user_id INT, trade_type VARCHAR(255), currency VARCHAR(255), coin VARCHAR(255),
                quantity decimal(30, 10), count int,
                price1 decimal(30, 10), ticker1 decimal(30, 10), price2 decimal(30, 10), ticker2 decimal(30, 10),
                price3 decimal(30, 10), ticker3 decimal(30, 10), price4 decimal(30, 10), ticker4 decimal(30, 10))
            BEGIN
                DECLARE row_count INT DEFAULT 0;
                DECLARE updated_at BIGINT DEFAULT 0;
                SELECT ROUND(UNIX_TIMESTAMP(CURTIME(4)) * 1000) INTO updated_at;

                CALL update_user_orderbook_group(user_id, trade_type, currency, coin,
                    quantity, count, price1, ticker1, updated_at);
                CALL update_user_orderbook_group(user_id, trade_type, currency, coin,
                    quantity, count, price2, ticker2, updated_at);
                CALL update_user_orderbook_group(user_id, trade_type, currency, coin,
                    quantity, count, price3, ticker3, updated_at);
                CALL update_user_orderbook_group(user_id, trade_type, currency, coin,
                    quantity, count, price4, ticker4, updated_at);
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE update_user_orderbook_group(
                user_id INT, trade_type VARCHAR(255), currency VARCHAR(255), coin VARCHAR(255),
                quantity decimal(30, 10), count int, price decimal(30, 10), ticker decimal(30, 10), updated_at BIGINT)
            BEGIN
                DECLARE row_count INT DEFAULT 0;

                IF ticker > 0 THEN
                    UPDATE user_orderbooks SET user_orderbooks.quantity = user_orderbooks.quantity + quantity,
                        user_orderbooks.count = user_orderbooks.count + count, user_orderbooks.updated_at = updated_at
                    WHERE user_orderbooks.user_id = user_id AND user_orderbooks.trade_type = trade_type
                        AND user_orderbooks.currency = currency AND user_orderbooks.coin = coin
                        AND user_orderbooks.price = price AND user_orderbooks.ticker = ticker;
                    SELECT ROW_COUNT() INTO row_count;
                    IF row_count = 0 THEN
                        INSERT INTO user_orderbooks (`user_id`, `trade_type`, `currency`, `coin`, `price`, `ticker`, `quantity`, `count`, `updated_at`)
                            VALUES(user_id, trade_type, currency, coin, price, ticker, quantity, count, updated_at)
                            ON DUPLICATE KEY UPDATE user_orderbooks.quantity = user_orderbooks.quantity + quantity, user_orderbooks.count = user_orderbooks.count + count, user_orderbooks.updated_at = updated_at;
                    END IF;
                END IF;
            END
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS update_orderbook_groups');
        DB::unprepared('DROP PROCEDURE IF EXISTS update_orderbook_group');
        DB::unprepared('DROP PROCEDURE IF EXISTS update_user_orderbook_groups');
        DB::unprepared('DROP PROCEDURE IF EXISTS update_user_orderbook_group');
    }
}
