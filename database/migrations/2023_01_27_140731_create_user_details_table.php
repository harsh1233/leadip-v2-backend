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
        Schema::create('user_details', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36);
            $table->enum('point_of_contact',['M','L','HR','F','BD'])->nullable()->comment('M:Management, L:Legal, HR, F:Finance, BD:Bisiness Development');
            $table->string('description', 512)->nullable();
            $table->string('phone_number', 15)->nullable();
            $table->string('whatsapp_number', 15)->nullable();
            $table->string('profile_completed_percentage', 15)->nullable();
            $table->string('linkedin_profile', 128)->nullable();
            $table->string('facebook_profile', 128)->nullable();
            $table->string('other_profile', 128)->nullable();
            $table->json('extra_channels')->nullable();
            $table->json('expertises')->nullable();
            $table->json('interests')->nullable();
            $table->timestamps();
            $table->char('created_by', 36);
            $table->char('updated_by', 36)->nullable();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_details');
    }
};
