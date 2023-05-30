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
        Schema::create('contacts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('company_id', 36);
            $table->string('profile_picture', 512)->nullable();
            $table->enum('type', ['G', 'P', 'CL'])->default('G')->comment('G:General Contact, P:Prospect, CL:Client');
            $table->enum('section', ['A', 'M', 'L'])->default('A')->comment('A:All, M:My, L:Lost');
            $table->enum('sub_type', ['C', 'P'])->default('C')->comment('C:Company, P:People');
            $table->enum('priority', ['H', 'M', 'L'])->default('L')->comment('H:Company, M:People, L:People');
            $table->enum('role', ['H', 'R'])->nullable()->comment('H:Holder, R:Representative');
            $table->string('email', 128)->unique();
            $table->string('phone_number', 15);
            $table->string('client_since', 15)->nullable();
            $table->string('first_name', 128);
            $table->string('last_name', 128);
            $table->char('country_code', 2);
            $table->char('city_id', 36);
            $table->longText('recently_contacted_by')->nullable();
            $table->json('areas_of_expertise')->nullable();
            $table->json('covered_regions')->nullable();
            $table->json('ongoing_work')->nullable();
            $table->json('potencial_for')->nullable();
            $table->json('industry')->nullable();
            $table->enum('marketing', ['LC', 'LCPF', 'O', 'EN', 'CM'])->nullable();
            $table->string('slag', 255)->nullable();

            $table->timestamps();
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('country_code')->references('code')->on('countries')->onDelete('cascade');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('contacts');
    }
};
