<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->string('public_token', 64)->unique();
            $table->string('certificate_number', 40)->unique();
            $table->unsignedInteger('approved_minutes');
            $table->string('status', 20)->default('issued'); // issued | revoked | superseded
            $table->dateTime('issued_at');
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('supersedes_certificate_id')->nullable()->constrained('certificates')->restrictOnDelete();
            $table->timestamps();
            $table->index(['public_token', 'status']);
            $table->index(['student_id', 'term_id', 'status']);
        });

        Schema::create('certificate_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 30); // issued | revoked | superseded
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_events');
        Schema::dropIfExists('certificates');
    }
};
