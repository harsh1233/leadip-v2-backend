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
        if ( !Schema::hasTable('apollo_histories') ) {
            Schema::create('apollo_histories', function (Blueprint $table) {
                $table->char('id',36)->primary();
                $table->string('people_id')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('headline')->nullable();
                $table->string('linkedin_url')->nullable();
                $table->string('title')->nullable();
                $table->string('email_status')->nullable();
                $table->string('photo_url')->nullable();
                $table->string('twitter_url')->nullable();
                $table->string('github_url')->nullable();
                $table->string('facebook_url')->nullable();
                $table->text('extrapolated_email_confidence')->nullable();
                $table->string('state')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('seniority')->nullable();
                $table->text('intent_strength')->nullable();
                $table->boolean('show_intent')->default(false);
                $table->boolean('revealed_for_current_team')->default(false);
                $table->json('personal_emails')->nullable();
                $table->json('departments')->nullable();
                $table->json('employment_history')->nullable();
                $table->json('organization')->nullable();
                $table->json('phone_numbers')->nullable();
                $table->json('subdepartments')->nullable();
                $table->json('functions')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('apollo_histories');
    }
};
