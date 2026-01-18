<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMamMastersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mam_masters', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->decimal('max_drawdown', 30, 15)->default(0);
            $table->decimal('fund_balance', 30, 15)->default(0);
            $table->decimal('init_fund_balance', 30, 15)->default(0);
            $table->decimal('fund_capital', 30, 15)->default(0);
            $table->decimal('fund_gain', 30, 15)->default(0);
            $table->decimal('performance_rate', 30, 15)->default(0);
            $table->decimal('next_performance_rate', 30, 15)->default(0);
            $table->decimal('revokable_amount', 30, 15)->default(0);
            $table->string('updated_interval')->nullable();
            $table->decimal('total_revoke_amount', 30, 15)->default(0);
            $table->decimal('unrealised_commission', 30, 15)->default(0);
            $table->enum('status', ['opened', 'closing', 'closed'])->default('opened');
            $table->unsignedInteger('closing_step')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mam_masters');
    }
}
