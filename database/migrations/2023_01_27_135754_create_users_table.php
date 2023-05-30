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
        Schema::create('users', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('company_id', 36);
            $table->char('role_id', 36);
            $table->string('first_name', 128);
            $table->string('last_name', 128);
            $table->string('email', 128)->unique();
            $table->string('password', 251);
            $table->enum('social_type',['G','F','L'])->nullable()->comment('G:Google, F:Facebook, L:Linkedin');
            $table->string('social_id', 128)->nullable();
            $table->timestamp('is_email_verified')->nullable();
            $table->string('verification_token', 128)->nullable();
            $table->timestamp('verification_token_expiry')->nullable();
            $table->string('profile_picture', 512)->nullable();
            $table->string('position', 128)->nullable();
            $table->enum('onboarding_status',['YC','AT','SC','I','YP'])->nullable()->comment('YC:YourCompany, AT:AddTeam, SC:SyncContacts, I:Invited, YP:YourPreference');
            $table->boolean('is_onboarded')->default(0)->comment('0:Blocked,1:Active');;
            $table->boolean('sync_with_gmail')->default(0)->comment('0:Blocked,1:Active');
            $table->boolean('sync_with_outlook')->default(0)->comment('0:Blocked,1:Active');
            $table->boolean('sync_with_linkedin')->default(0)->comment('0:Blocked,1:Active');
            $table->boolean('is_active')->default(1)->comment('0:Blocked,1:Active');
            $table->timestamps();
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->softDeletes();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
