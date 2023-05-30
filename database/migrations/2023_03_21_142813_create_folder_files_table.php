
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
        Schema::create('folder_files', function (Blueprint $table) {
            $table->char('folder_id', 255)->nullable();
            $table->char('file_id', 255)->nullable();
            $table->foreign('folder_id')->references('id')->on('folders')->onDelete('cascade');
            $table->foreign('file_id')->references('id')->on('global_files')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('folder_files');
    }
};
