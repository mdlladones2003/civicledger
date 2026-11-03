<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // attempts/last_error are additive to Schema v1.0 (queue retry diagnostics, no sensitive payloads).
        Schema::create('blockchain_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_record_version_id')->nullable()->unique()->constrained('service_record_versions')->restrictOnDelete();
            $table->foreignId('certificate_id')->nullable()->unique()->constrained('certificates')->restrictOnDelete();
            $table->string('opaque_record_key', 66)->unique();
            $table->string('commitment', 66);
            $table->unsignedBigInteger('chain_id');
            $table->string('contract_address', 42);
            $table->string('tx_hash', 66)->nullable();
            $table->string('proof_status', 20)->default('queued');     // queued|submitted|confirmed|failed
            $table->string('validity_status', 20)->default('current'); // current|superseded|revoked
            $table->dateTime('confirmed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['proof_status', 'chain_id']);
        });

        // Exactly one subject. RESTRICT FKs keep this CHECK legal on MySQL 8.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE blockchain_proofs ADD CONSTRAINT blockchain_proofs_exactly_one_subject CHECK ((service_record_version_id IS NULL) <> (certificate_id IS NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blockchain_proofs');
    }
};
