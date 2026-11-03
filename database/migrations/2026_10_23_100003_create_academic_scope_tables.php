<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->timestamps();
            $table->unique(['program_id', 'term_id', 'code']);
        });

        // scope_role: student | coordinator | signer
        Schema::create('user_section_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('scope_role', 20);
            $table->timestamps();
            $table->unique(['user_id', 'section_id', 'scope_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_section_assignments');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('terms');
    }
};
