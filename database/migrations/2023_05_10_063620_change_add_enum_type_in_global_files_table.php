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
        Schema::table('global_files', function (Blueprint $table) {
            DB::statement("ALTER TABLE `global_files` CHANGE `file_type` `file_type` ENUM('png','jpeg','jpg','PDF','CSV','XLS','XLSX','doc','docx','txt') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('global_files', function (Blueprint $table) {
            DB::statement("ALTER TABLE `global_files` CHANGE `file_type` `file_type` ENUM('png','jpeg','jpg','PDF','CSV','XLS','doc','docx','txt') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL");
        });
    }
};
