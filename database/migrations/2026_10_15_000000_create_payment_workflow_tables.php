<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->unique()->constrained()->restrictOnDelete();
            $table->text('instructions');
            $table->text('instructions_ar');
            $table->text('reference_instructions')->nullable();
            $table->text('reference_instructions_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('supersedes_id')->nullable()->constrained('payment_proofs')->restrictOnDelete();
            $table->string('file_disk', 40)->default('local');
            $table->string('file_path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->char('sha256', 64)->index();
            $table->unsignedBigInteger('claimed_amount_minor');
            $table->char('currency', 3);
            $table->unsignedBigInteger('confirmed_amount_minor')->nullable();
            $table->char('confirmed_currency', 3)->nullable();
            $table->date('payment_date');
            $table->string('payment_method', 100);
            $table->string('payment_reference', 160)->nullable()->index();
            $table->string('status', 20)->default('pending');
            // Terminal rows set this to null. MySQL and SQLite then allow retained history.
            $table->unsignedTinyInteger('pending_slot')->nullable()->default(1);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_reason', 500)->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['enrollment_id', 'pending_slot'], 'payment_proofs_one_pending');
            $table->index(['status', 'created_at'], 'payment_proofs_review_queue');
        });

        Schema::create('payment_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_proof_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20);
            $table->unsignedBigInteger('confirmed_amount_minor')->nullable();
            $table->char('confirmed_currency', 3)->nullable();
            $table->string('reason', 500);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['payment_proof_id', 'created_at']);
        });

        Schema::table('course_entitlements', function (Blueprint $table) {
            $table->unique(['source_type', 'source_id'], 'entitlements_unique_source');
        });

        Schema::table('access_waivers', function (Blueprint $table) {
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->restrictOnDelete();
            $table->string('revocation_reason', 500)->nullable()->after('revoked_by');
        });
    }

    public function down(): void
    {
        Schema::table('access_waivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn('revocation_reason');
        });
        Schema::table('course_entitlements', fn (Blueprint $table) => $table->dropUnique('entitlements_unique_source'));
        Schema::dropIfExists('payment_reviews');
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('payment_instructions');
    }
};
