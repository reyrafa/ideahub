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
        Schema::table('ideas', function (Blueprint $table) {
            $table->decimal('hours_saved', 8, 1)->unsigned()->nullable();
            $table->decimal('money_saved', 12, 2)->unsigned()->nullable();
            $table->text('results_note')->nullable();
            $table->foreignId('results_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('results_recorded_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('results_recorded_by');
            $table->dropColumn(['hours_saved', 'money_saved', 'results_note', 'results_recorded_at']);
        });
    }
};
