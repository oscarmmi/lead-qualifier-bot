<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name')->nullable();
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('budget_band')->nullable();
            $table->string('authority')->nullable();
            $table->text('need_summary')->nullable();
            $table->string('timeline')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->char('grade', 1)->nullable();
            $table->string('status')->default('chatting');
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('chat_turns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->json('extracted_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_turns');
        Schema::dropIfExists('leads');
    }
};
