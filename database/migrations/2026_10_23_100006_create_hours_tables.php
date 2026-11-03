<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->unique()->constrained('attendance')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('approved_minutes')->default(0); // unsigned => >= 0
            $table->string('status', 20)->default('pending');        // pending|approved|rejected|revision_requested
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedSmallInteger('current_version')->default(0); // 0 until first approval
            $table->timestamps();
            $table->index(['student_id', 'status']);
        });

        Schema::create('service_record_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_record_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->json('canonical_payload_json');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['service_record_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_record_versions');
        Schema::dropIfExists('service_records');
    }
};
