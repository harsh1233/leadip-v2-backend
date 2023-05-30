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
           DB::statement("ALTER TABLE `contacts` CHANGE `priority` `priority` ENUM('H','M','L') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'L' COMMENT 'H:Company, M:People, L:People'");
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
            DB::statement("ALTER TABLE `contacts` CHANGE `priority` `priority` ENUM('H','M','L') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'L' COMMENT 'H:Company, M:People, L:People'");
         });
    }
};
