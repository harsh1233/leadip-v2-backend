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
        Schema::table('users', function (Blueprint $table) {
           DB::statement("ALTER TABLE `users` CHANGE `onboarding_status` `onboarding_status` ENUM('YC','AT','SC','I','CO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'YC:YourCompany, AT:AddTeam, SC:SyncContacts, I:Invited,CO:Completed'");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            DB::statement("ALTER TABLE `users` CHANGE `onboarding_status` `onboarding_status` ENUM('YC','AT','SC','I') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'YC:YourCompany, AT:AddTeam, SC:SyncContacts, I:Invited'");
        });
    }
};
