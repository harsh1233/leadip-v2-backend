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
            $table->integer('prospect_google_sync_count')->default(0);
            $table->integer('client_google_sync_count')->default(0);
            $table->integer('prospect_linkdin_sync_count')->default(0);
            $table->integer('client_linkdin_sync_count')->default(0);
            $table->integer('prospect_outlook_sync_count')->default(0);
            $table->integer('client_outlook_sync_count')->default(0);
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
            $table->dropColumn('prospect_google_sync_count');
            $table->dropColumn('client_google_sync_count');
            $table->dropColumn('prospect_linkdin_sync_count');
            $table->dropColumn('client_linkdin_sync_count');
            $table->dropColumn('prospect_outlook_sync_count');
            $table->dropColumn('client_outlook_sync_count');
        });
    }
};
