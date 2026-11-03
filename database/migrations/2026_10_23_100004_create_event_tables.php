<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // venue/component/description are additive to Schema v1.0 (PRD F2.1 requires place + component).
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coordinator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('venue', 200)->nullable();
            $table->string('component', 20)->nullable(); // CWTS | LTS | ROTC
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('draft'); // draft|published|cancelled|completed
            $table->timestamps();
            $table->index(['section_id', 'status', 'starts_at']);
        });

        Schema::create('event_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('scheduled'); // scheduled|open|closed
            $table->timestamps();
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('registered'); // registered|cancelled
            $table->timestamps();
            $table->unique(['event_id', 'student_id']);
            $table->index(['student_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('event_sessions');
        Schema::dropIfExists('events');
    }
};
