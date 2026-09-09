<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Своё состояние очереди для модератора. Поле status ведёт владелец объекта
     * («устранил / не согласен»), и модератору оно ничего не говорит о его
     * собственной работе: разобрал он жалобу или ещё нет.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->enum('review_state', ['new', 'in_progress', 'handled', 'dismissed'])
                ->default('new')
                ->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('review_state');

            $table->index('review_state');
        });

        // жалобы, по которым уже применяли меры, считаем разобранными
        DB::table('complaints')
            ->whereIn('id', fn ($q) => $q->select('complaint_id')->from('complaint_actions'))
            ->update(['review_state' => 'handled', 'reviewed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['review_state']);
            $table->dropColumn(['review_state', 'reviewed_at']);
        });
    }
};
