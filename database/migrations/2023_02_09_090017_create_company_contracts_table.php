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
        Schema::create('company_contracts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('contact_type', 55);
            $table->string('profile_picture', 512)->nullable();
            $table->string('name', 55);
            $table->string('email', 128)->unique();
            $table->string('phone_number', 15)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->char('city_id', 36)->nullable();
            $table->string('client_since', 15)->nullable();
            $table->string('priority', 15)->nullable();
            $table->string('type', 15)->nullable();
            $table->longText('recently_contacted_by')->nullable();
            $table->longText('ongoing_work')->nullable();
            $table->longText('potencial_for')->nullable();
            $table->longText('industry')->nullable();
            $table->timestamps();
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->softDeletes();

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
        Schema::dropIfExists('company_contracts');
    }
};
