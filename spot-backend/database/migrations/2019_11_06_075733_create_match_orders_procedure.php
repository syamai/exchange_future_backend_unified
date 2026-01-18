<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateMatchOrdersProcedure extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS update_executed_price_and_quantity');
        DB::unprepared('DROP PROCEDURE IF EXISTS update_balances');
        DB::unprepared('DROP PROCEDURE IF EXISTS match_and_complete_sell_order');
        DB::unprepared('DROP PROCEDURE IF EXISTS match_and_complete_both_orders');
        DB::unprepared('DROP PROCEDURE IF EXISTS match_and_complete_buy_order');

        DB::unprepared("
            CREATE PROCEDURE update_executed_price_and_quantity(
                original_buy_id INT, original_sell_id INT, price decimal(30, 10), quantity decimal(30, 10)
                )
            BEGIN
                UPDATE orders SET
                    orders.executed_price = (orders.executed_quantity * orders.executed_price + quantity * price) / (orders.executed_quantity + quantity), 
                    orders.executed_quantity = orders.executed_quantity + quantity
                    WHERE orders.id = original_buy_id or orders.id = original_sell_id;
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE update_balances(
                /* balance tables */
                currency_name VARCHAR(255), coin_name VARCHAR(255),
                /* buyer */
                buyer_id int, buy_currency_change decimal(30, 10), available_change decimal(30, 10), buy_coin_change decimal(30, 10),
                /* seller */
                seller_id int, sell_currency_change decimal(30, 10), sell_coin_change decimal(30, 10)
                )
            BEGIN
                -- update buyer currency balance
                SET @update_buyer_currency_sql = CONCAT('UPDATE ', currency_name, ' SET usd_amount = usd_amount + ', buy_currency_change, ' * usd_amount / balance, available_balance = available_balance + ', available_change,
                    ', balance = balance + ' , buy_currency_change, ' WHERE id = ', buyer_id);
                PREPARE update_buyer_currency_stmt FROM @update_buyer_currency_sql;
                EXECUTE update_buyer_currency_stmt;
                DEALLOCATE PREPARE update_buyer_currency_stmt;
                
                -- update buyer coin balance
                SET @update_buyer_coin_sql = CONCAT('UPDATE ', coin_name, ' SET balance = balance + ' , buy_coin_change, ', available_balance = available_balance + ', buy_coin_change, ' WHERE id = ', buyer_id);
                PREPARE update_buyer_coin_stmt FROM @update_buyer_coin_sql;
                EXECUTE update_buyer_coin_stmt;
                DEALLOCATE PREPARE update_buyer_coin_stmt;
                
                -- update seller currency balance
                SET @update_seller_currency_sql = CONCAT('UPDATE ', currency_name, ' SET balance = balance + ' , sell_currency_change, ', available_balance = available_balance + ', sell_currency_change, ' WHERE id = ', seller_id);
                PREPARE update_seller_currency_stmt FROM @update_seller_currency_sql;
                EXECUTE update_seller_currency_stmt;
                DEALLOCATE PREPARE update_seller_currency_stmt;
                
                -- update seller coin balance
                SET @update_seller_coin_sql = CONCAT('UPDATE ', coin_name, ' SET usd_amount = usd_amount + ', sell_coin_change, ' * usd_amount / balance, balance = balance + ' , sell_coin_change, ' WHERE id = ', seller_id);
                PREPARE update_seller_coin_stmt FROM @update_seller_coin_sql;
                EXECUTE update_seller_coin_stmt;
                DEALLOCATE PREPARE update_seller_coin_stmt;
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE match_and_complete_sell_order(
                /* orders data */
                buy_id INT, sell_id INT, buy_order_status VARCHAR(255),
                price decimal(30, 10), quantity decimal(30, 10), buy_fee decimal(30, 10), sell_fee decimal(30, 10),
                /* balance tables */
                currency_name VARCHAR(255), coin_name VARCHAR(255),
                /* buyer */
                buyer_id int, buy_currency_change decimal(30, 10), available_change decimal(30, 10), buy_coin_change decimal(30, 10),
                /* seller */
                seller_id int, sell_currency_change decimal(30, 10), sell_coin_change decimal(30, 10)
                )
            BEGIN
                -- complete both sell order and original order
                UPDATE orders SET orders.status = buy_order_status, orders.fee=orders.fee + buy_fee WHERE orders.id = buy_id;
                UPDATE orders SET orders.status = 'executed', orders.fee = orders.fee+sell_fee WHERE orders.id = sell_id;
                
                CALL update_executed_price_and_quantity(buy_id, sell_id, price, quantity);
                
                CALL update_balances(
                    /* balance tables */
                    currency_name, coin_name,
                    /* buyer */
                    buyer_id, buy_currency_change, available_change, buy_coin_change,
                    /* seller */
                    seller_id, sell_currency_change, sell_coin_change
                );
                
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE match_and_complete_both_orders(
                /* orders data */
                buy_id INT, sell_id INT,
                price decimal(30, 10), quantity decimal(30, 10), buy_fee decimal(30, 10), sell_fee decimal(30, 10),
                /* balance tables */
                currency_name VARCHAR(255), coin_name VARCHAR(255),
                /* buyer */
                buyer_id int, buy_currency_change decimal(30, 10), available_change decimal(30, 10), buy_coin_change decimal(30, 10),
                /* seller */
                seller_id int, sell_currency_change decimal(30, 10), sell_coin_change decimal(30, 10)
                )
            BEGIN
                -- complete both orders
                UPDATE orders SET orders.status = 'executed', orders.fee = orders.fee+buy_fee WHERE orders.id = buy_id;
                UPDATE orders SET orders.status = 'executed', orders.fee = orders.fee+sell_fee WHERE orders.id = sell_id;
                
                CALL update_executed_price_and_quantity(buy_id, sell_id, price, quantity);
                
                CALL update_balances(
                    /* balance tables */
                    currency_name, coin_name,
                    /* buyer */
                    buyer_id, buy_currency_change, available_change, buy_coin_change,
                    /* seller */
                    seller_id, sell_currency_change, sell_coin_change
                );
                
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE match_and_complete_buy_order(
                /* orders data */
                buy_id INT, sell_id INT, buy_order_status VARCHAR(255), sell_order_status VARCHAR(255),
                price decimal(30, 10), quantity decimal(30, 10), buy_fee decimal(30, 10), sell_fee decimal(30, 10),
                /* balance tables */
                currency_name VARCHAR(255), coin_name VARCHAR(255),
                /* buyer */
                buyer_id int, buy_currency_change decimal(30, 10), available_change decimal(30, 10), buy_coin_change decimal(30, 10),
                /* seller */
                seller_id int, sell_currency_change decimal(30, 10), sell_coin_change decimal(30, 10)
                )
            BEGIN
                -- complete both buy order and original order
                UPDATE orders SET orders.status = buy_order_status, orders.fee = orders.fee+buy_fee WHERE orders.id = buy_id;
                UPDATE orders SET orders.fee=orders.fee + sell_fee, orders.status = sell_order_status WHERE  orders.id = sell_id;
                
                CALL update_executed_price_and_quantity(buy_id, sell_id, price, quantity);
                
                CALL update_balances(
                    /* balance tables */
                    currency_name, coin_name,
                    /* buyer */
                    buyer_id, buy_currency_change, available_change, buy_coin_change,
                    /* seller */
                    seller_id, sell_currency_change, sell_coin_change
                );
                
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
        DB::unprepared('DROP PROCEDURE IF EXISTS update_executed_price_and_quantity');
        DB::unprepared('DROP PROCEDURE IF EXISTS update_balances');
        DB::unprepared('DROP PROCEDURE IF EXISTS match_and_complete_sell_order');
        DB::unprepared('DROP PROCEDURE IF EXISTS match_and_complete_both_orders');
        DB::unprepared('DROP PROCEDURE IF EXISTS match_and_complete_buy_order');
    }
}
