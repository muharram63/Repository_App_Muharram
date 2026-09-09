<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('description');
            $table->foreignId('employer_id')->constrained('employers');
            $table->integer('salary_from');
            $table->integer('salary_to');
            $table->string('currency');
            $table->enum('employment_type', ['full-time', 'part-time','project_work','internship']);
            $table->enum('work_schedule' , ['full_day' , 'flexible_schedule','remote_work']);
            $table->enum('experience_required',['not','year' ,'3_years' , '3-6years' , 'more_6years']);
            $table->string('skill');
            $table->enum('status' , ['active' , 'inactive','closed' ,'in_archived'])->default('active');
            $table->foreignId('city_id')->constrained('cities');
            $table->foreignId('created_by')->nullable()->constrained('employers');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacancies');
    }
};
