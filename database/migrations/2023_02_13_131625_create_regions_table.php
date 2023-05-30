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
        Schema::create('regions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 155)->comment('Western Europe','Central and Eastern Europe','Asia','Africa','Mediterranean & Middle East','Americas');
            $table->timestamps();
            $table->char('created_by', 36);
            $table->char('updated_by', 36)->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('regions');
    }
};
