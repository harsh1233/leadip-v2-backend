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
        Schema::table('list_contacts', function (Blueprint $table) {
            DB::statement("ALTER TABLE `list_contacts` CHANGE `type` `type` ENUM('L','P','CL','C','LC') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'L:Lead,P:Prospect, CL:Custom List, C:Client, LC:Lost Contacts'");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('list_contacts', function (Blueprint $table) {
            DB::statement("ALTER TABLE `list_contacts` CHANGE `type` `type` ENUM('L','CL','C','LC') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'L:Lead, CL:Custom List, C:Client, LC:Lost Contacts'");
        });
    }
};
