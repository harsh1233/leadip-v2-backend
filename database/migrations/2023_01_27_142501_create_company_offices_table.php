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
        Schema::create('company_offices', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('company_id', 36);
            $table->enum('type', ['B', 'H'])->comment('B: Branch, H: Headquarters');
            $table->string('address', 512)->nullable();
            $table->char('country_code', 2);
            $table->char('city_id', 36);
            $table->timestamps();
            $table->char('created_by', 36);
            $table->char('updated_by', 36)->nullable();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('country_code')->references('code')->on('countries')->onDelete('cascade');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('company_offices');
    }
};
