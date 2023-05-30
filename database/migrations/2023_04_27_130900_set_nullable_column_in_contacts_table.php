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
        Schema::table('contacts', function (Blueprint $table) {
            $table->char('company_id', 36)->unsigned()->nullable()->change();
            $table->char('country_code', 2)->unsigned()->nullable()->change();
            $table->char('city_id', 36)->unsigned()->nullable()->change();
            $table->text('social_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->char('company_id', 36)->unsigned()->nullable(false)->change();
            $table->char('country_code', 2)->unsigned()->nullable(false)->change();
            $table->char('city_id', 36)->unsigned()->nullable(false)->change();
            $table->string('social_id', 128)->nullable()->change();
        });
    }
};
