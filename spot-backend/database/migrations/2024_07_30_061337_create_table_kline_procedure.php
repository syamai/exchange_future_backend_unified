<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS create_table_kline');
        DB::unprepared("
            CREATE PROCEDURE create_table_kline (IN TableName VARCHAR(20))
            BEGIN
                SET
                    @SQL := CONCAT(
                        CONCAT('CREATE TABLE ', TableName),
                        '(`time` BIGINT NOT NULL,
                        `interval` VARCHAR(4) NOT NULL,
                        `opening_time` BIGINT NOT NULL,
                        `closing_time` BIGINT NOT NULL,
                        `open` DECIMAL(32,10) UNSIGNED NOT NULL,
                        `close` DECIMAL(32,10) UNSIGNED NOT NULL,
                        `high` DECIMAL(32,10) UNSIGNED NOT NULL,
                        `low` DECIMAL(32,10) UNSIGNED NOT NULL,
                        `volume` DECIMAL(32,10) UNSIGNED NOT NULL,
                        `quote_volume` DECIMAL(32,10) UNSIGNED NOT NULL,
                        `trade_count_crawled` INT UNSIGNED NOT NULL DEFAULT 0,
                        `trade_count` INT UNSIGNED NOT NULL DEFAULT 0,
                        PRIMARY KEY(`time`,`interval`),
                        INDEX(`time`),
                        INDEX(`interval`),
                        INDEX(`opening_time`)
                        ) ENGINE = InnoDB;'
                    );
                    
                PREPARE stmt
                FROM
                    @SQL;  
                EXECUTE stmt;
                DEALLOCATE PREPARE stmt;
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
        DB::unprepared('DROP PROCEDURE IF EXISTS create_table_kline');
    }
};
