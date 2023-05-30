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
        Schema::create('notes', function (Blueprint $table) {
            $table->char('id',36)->primary();
            $table->enum('sub_type', ['C', 'P'])->default('C')->comment('C:Company, P:People');
            $table->char('company_id',36);
            $table->char('contact_id',36);
            $table->string('subject',100)->nullable();
            $table->char('note_type_id',36)->nullable();
            $table->text('note_content');
            $table->char('created_by',36)->nullable();
            $table->char('updated_by',36)->nullable();
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
        Schema::dropIfExists('notes');
    }
};
