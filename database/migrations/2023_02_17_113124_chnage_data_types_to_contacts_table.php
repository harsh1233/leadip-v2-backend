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
            $table->string('company_name', 128);
            $table->string('email', 128)->unique(false)->change();
            $table->longText('areas_of_expertise')->nullable()->change();
            $table->longText('covered_regions')->nullable()->change();
            $table->longText('ongoing_work')->nullable()->change();
            $table->longText('potencial_for')->nullable()->change();
            $table->longText('industry')->nullable()->change();
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
            //
        });
    }
};
