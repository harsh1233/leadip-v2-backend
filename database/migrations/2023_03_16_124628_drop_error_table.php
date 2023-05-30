<?php

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
        Schema::table('errors', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::dropIfExists('errors');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('errors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('user_id', 255)->nullable();
            $table->string('code')->nullable();
            $table->string('file')->nullable();
            $table->string('line')->nullable();
            $table->longText('message')->nullable();
            $table->text('trace')->nullable();
            $table->timestamps();
        });
    }
};
