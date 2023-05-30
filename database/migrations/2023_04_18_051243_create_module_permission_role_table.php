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
        Schema::create('module_permission_role', function (Blueprint $table) {
            $table->char('id',36)->primary();
            $table->string('module_code',128);
            $table->string('permission_code',36);
            $table->char('role_id',36);
            $table->boolean('has_access')->default(1)->comment('0:False,1:True');
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
        Schema::dropIfExists('module_permission_role');
    }
};
