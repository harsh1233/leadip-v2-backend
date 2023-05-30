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
        Schema::create('user_preference_industry', function (Blueprint $table) {
            $table->char('industry_id', 255)->nullable();
            $table->char('user_detail_id', 255)->nullable();
            $table->foreign('industry_id')->references('id')->on('industries')->onDelete('cascade');
            $table->foreign('user_detail_id')->references('id')->on('user_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_preference_industry');

    }
};
