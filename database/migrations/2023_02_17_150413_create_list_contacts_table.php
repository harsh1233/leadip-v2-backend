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
        Schema::create('list_contacts', function (Blueprint $table) {
            $table->char('list_id',36);
            $table->char('contact_id',36);
            $table->enum    ('type',['L','CL','C','LC'])->comment('L:Lead, CL:Custom List, C:Client, LC:Lost Contacts');
            $table->foreign ('list_id')->references('id')->on('lists')->onDelete('cascade');
            $table->foreign ('contact_id')->references('id')->on('contacts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('list_contacts');
    }
};
