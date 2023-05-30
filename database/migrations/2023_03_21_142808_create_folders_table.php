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
        Schema::create('folders', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 128)->nullable();
            $table->string('description', 512)->nullable();
            $table->char('contact_id', 255)->nullable();
            $table->char('folder_type_id', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('folder_type_id')->references('id')->on('folder_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('folders');
    }
};
