<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Consts;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_filters', function (Blueprint $table) {
			$table->id();
			$table->unsignedInteger('admin_id');
			$table->string('domain')->unique();
			$table->enum('type', [Consts::TYPE_WHITELIST, Consts::TYPE_BLACKLIST]);
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
        Schema::dropIfExists('email_filters');
    }
};
