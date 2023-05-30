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
        Schema::create('company_meets', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('company_id', 36);
            $table->string('link', 128)->nullable();
            $table->timestamp('date')->nullable();
            $table->string('name', 128);
            $table->char('country_code', 2);
            $table->string('image', 512)->nullable();
            $table->timestamps();
            $table->char('created_by', 36);
            $table->char('updated_by', 36)->nullable();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('country_code')->references('code')->on('countries')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('company_meets');
    }
};
