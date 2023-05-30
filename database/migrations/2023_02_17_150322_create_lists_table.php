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
        Schema::create('lists', function (Blueprint $table) {
            $table->char    ('id',36)->primary();
            $table->char    ('company_id',36);
            $table->enum    ('main_type',['G','P','CL'])->comment('G:General Contacts, P:Prospect, CL:Client');
            $table->enum    ('type',['L','CL','C','LC'])->comment('L:Lead, CL:Custom List, C:Client, LC:Lost Contacts');
            $table->enum    ('sub_type',['C','P'])->default("C")->comment('C:Company , P:People');
            $table->string  ('name',155);
            $table->integer ('size');
            $table->char    ('created_by',36)->nullable();
            $table->char    ('updated_by',36)->nullable();
            $table->foreign ('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lists');
    }
};
