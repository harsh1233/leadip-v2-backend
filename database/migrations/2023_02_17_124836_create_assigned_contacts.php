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
        Schema::create('assigned_contacts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('contact_id', 255)->nullable();
            $table->char('assigned_to_id', 255)->nullable();
            $table->char('assigned_by_id', 255)->nullable();
            $table->timestamps();
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->softDeletes();

            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('assigned_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assigned_contacts');
    }
};
