<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('event_sessions')->cascadeOnDelete();
            $table->string('nonce_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index('expires_at');
        });

        // Table name is singular per Schema v1.0 ("attendance").
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('event_sessions')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('check_in_at');
            $table->dateTime('check_out_at')->nullable();
            $table->string('source', 10)->default('qr');           // qr | manual
            $table->string('status', 20)->default('checked_in');   // checked_in | completed | rejected
            $table->timestamps();
            $table->unique(['session_id', 'student_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE attendance ADD CONSTRAINT attendance_checkout_after_checkin CHECK (check_out_at IS NULL OR check_out_at >= check_in_at)');
        }

        Schema::create('attendance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->json('old_json')->nullable();
            $table->json('new_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_adjustments');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('qr_challenges');
    }
};
