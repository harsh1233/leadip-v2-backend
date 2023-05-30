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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('google_sync_count')->default(0);
            $table->integer('linkdin_sync_count')->default(0);
            $table->integer('outlook_sync_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_sync_count');
            $table->dropColumn('linkdin_sync_count');
            $table->dropColumn('outlook_sync_count');
        });
    }
};
