<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // на что жалуются: вакансия, резюме, компания или пользователь
            $table->enum('target_type', ['vacancy', 'resume', 'company', 'user']);
            $table->unsignedBigInteger('target_id');

            $table->enum('reason', ['fraud', 'spam', 'fake', 'offensive', 'discrimination', 'other']);
            $table->text('message')->nullable();

            $table->enum('status', ['new', 'in_review', 'resolved', 'rejected'])->default('new');
            $table->text('admin_comment')->nullable();
            $table->dateTime('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
