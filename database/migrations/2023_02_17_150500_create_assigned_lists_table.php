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
        Schema::create('assigned_lists', function (Blueprint $table) {
            $table->char('id',36)->primary();
            $table->char('list_id',36);
            $table->char('assigned_to',36)->nullable();
            $table->char('assigned_from',36)->nullable();
            $table->char('owned_by',36)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assigned_lists');
    }
};
