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
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('employers')->onDelete('cascade');
            $table->foreignId('applicant_id')->constrained('applicants')->onDelete('cascade');
            $table->foreignId('vacancy_id')->nullable()->constrained('vacancies')->nullOnDelete();
            $table->foreignId('vacancy_response_id')->nullable()->constrained('vacancy_responses')->nullOnDelete();
            $table->foreignId('resume_response_id')->nullable()->constrained('resume_responses')->nullOnDelete();

            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('room')->unique();
            $table->text('note')->nullable();
            $table->enum('status', ['scheduled', 'confirmed', 'declined', 'canceled', 'finished'])
                ->default('scheduled');

            $table->timestamps();

            $table->index(['employer_id', 'scheduled_at']);
            $table->index(['applicant_id', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
