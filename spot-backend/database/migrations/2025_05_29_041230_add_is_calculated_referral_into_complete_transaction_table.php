<?php

use App\Models\CompleteTransaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('complete_transactions', function (Blueprint $table) {
            $table->tinyInteger('is_calculated_client_ref')->default(0)->index();
        });

        CompleteTransaction::query()->update(['is_calculated_client_ref' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('complete_transactions', function (Blueprint $table) {
            $table->dropColumn('is_calculated_client_ref');
        });
    }
};
