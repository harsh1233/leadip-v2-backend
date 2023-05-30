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
        Schema::create('user_certifications', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36);
            $table->string('name', 128);
            $table->string('issuing_organization', 128)->nullable();
            $table->timestamp('issue_date')->nullable();
            $table->string('image', 512)->nullable();
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
        Schema::dropIfExists('user_certifications');
    }
};
