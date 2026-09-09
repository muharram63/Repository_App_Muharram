<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Помощник собирает список навыков из всего разговора, и 255 символов
     * ему тесно: длинный перечень обрезался бы посреди слова.
     */
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->text('skills')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->string('skills')->nullable()->change();
        });
    }
};
