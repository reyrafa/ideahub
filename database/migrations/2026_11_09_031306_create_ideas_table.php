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
        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title', 120);
            $table->text('problem');
            $table->text('proposal');
            $table->text('expected_impact')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->string('status', 20)->default('new')->index();
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['votes_count', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ideas');
    }
};
