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
        Schema::create('companies', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 128)->nullable();
            $table->string('headline', 128)->nullable();
            $table->string('description',512)->nullable();
            $table->string('email', 128)->nullable();
            $table->string('phone', 55)->nullable();
            $table->string('website', 128)->nullable();
            $table->string('profile_picture', 128)->nullable();
            $table->string('linkedin_profile', 128)->nullable();
            $table->string('facebook_profile', 128)->nullable();
            $table->string('other_profile', 128)->nullable();
            $table->json('extra_channels')->nullable();
            $table->json('services')->nullable();
            $table->json('expertises')->nullable();
            $table->json('regions')->nullable();
            $table->json('languages')->nullable();
            $table->timestamps();
            $table->char('created_by', 36)->nullable();
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
        Schema::dropIfExists('companies');
    }
};
