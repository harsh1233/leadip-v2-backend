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
            $table->string('company_name', 128)->nullable()->change();
            $table->string('role', 128)->after('category')->nullable();
            $table->string('point_of_contact', 128)->after('role')->nullable();
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
            $table->string('company_name', 128);
            $table->dropColumn('role');
            $table->dropColumn('point_of_contact');
        });
    }
};
