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
        Schema::create('global_files', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('company_id',36)->nullable();
            $table->string('uploaded_file',512);
            $table->string('file_name',255)->nullable();
            $table->text('message')->nullable();
            $table->char('company_related', 36)->nullable();
            $table->char('contact_related', 36)->nullable();
            $table->enum('file_type',['png','jpeg','jpg','PDF','CSV','XLS','doc','docx','txt'])->nullable();
            $table->char('created_by',36)->nullable();
            $table->char('updated_by',36)->nullable();
            $table->timestamps();
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
        Schema::dropIfExists('global_files');
    }
};
