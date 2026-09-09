<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Уведомления и приватность пользователя.
     */
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->boolean('call_sound')->default(true);
            $table->boolean('call_notify')->default(true);
            $table->boolean('mail_responses')->default(true);
            $table->boolean('mail_messages')->default(true);

            $table->boolean('hide_profile')->default(false);
            $table->boolean('hide_contacts')->default(false);
            $table->boolean('hide_company')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
