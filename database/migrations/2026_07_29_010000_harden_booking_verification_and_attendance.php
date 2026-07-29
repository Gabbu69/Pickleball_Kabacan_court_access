<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('status')->index();
            $table->string('anonymized_reference', 64)->nullable()->after('closed_at')->unique();
        });

        Schema::table('courts', function (Blueprint $table) {
            $table->timestamp('verification_invalidated_at')->nullable()->after('verified_at');
            $table->index(['status', 'verification_status', 'published_at'], 'court_publication_lookup');
            $table->index(['municipality', 'province'], 'court_location_lookup');
        });

        Schema::table('court_verifications', function (Blueprint $table) {
            $table->string('evidence_disk', 32)->default('local')->after('evidence_path');
            $table->string('evidence_mime', 120)->nullable()->after('evidence_disk');
            $table->unsignedBigInteger('evidence_bytes')->nullable()->after('evidence_mime');
        });

        Schema::table('owner_applications', function (Blueprint $table) {
            $table->string('evidence_disk', 32)->default('local')->after('evidence_path');
            $table->string('evidence_url', 2048)->nullable()->after('evidence_disk');
            $table->string('evidence_mime', 120)->nullable()->after('evidence_url');
            $table->unsignedBigInteger('evidence_bytes')->nullable()->after('evidence_mime');
        });

        Schema::create('court_verification_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_verification_id')->constrained()->cascadeOnDelete();
            $table->string('field_key', 48);
            $table->string('status', 24)->default('pending');
            $table->string('value_hash', 64)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidation_reason')->nullable();
            $table->timestamps();
            $table->unique(['court_verification_id', 'field_key'], 'verification_evidence_field_unique');
            $table->index(['court_id', 'field_key', 'status'], 'verification_claim_lookup');
        });

        Schema::table('court_photos', function (Blueprint $table) {
            $table->string('storage_disk', 32)->default('public')->after('path');
            $table->string('storage_url', 2048)->nullable()->after('storage_disk');
            $table->string('mime_type', 120)->nullable()->after('storage_url');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('payment_status')->index();
            $table->timestamp('no_show_at')->nullable()->after('completed_at');
            $table->index(['court_id', 'status', 'starts_at'], 'booking_court_status_start');
            $table->index(['user_id', 'status', 'starts_at'], 'booking_user_status_start');
        });

        Schema::create('booking_slot_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_unit_id')->constrained()->restrictOnDelete();
            $table->dateTime('slot_starts_at');
            $table->dateTime('slot_ends_at');
            $table->timestamps();
            $table->unique(['court_unit_id', 'slot_starts_at'], 'booking_unit_slot_unique');
            $table->index(['booking_id', 'slot_starts_at'], 'booking_slot_claim_lookup');
        });

        Schema::create('booking_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 24)->default('issued')->index();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('scan_ip', 45)->nullable();
            $table->text('scan_user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('waitlist_offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('waitlist_entry_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('offered_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->index(['waitlist_entry_id', 'status'], 'waitlist_offer_entry_status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('proof_disk', 32)->default('local')->after('proof_path');
            $table->string('proof_url', 2048)->nullable()->after('proof_disk');
            $table->string('proof_mime', 120)->nullable()->after('proof_url');
            $table->unsignedBigInteger('proof_bytes')->nullable()->after('proof_mime');
            $table->index(['booking_id', 'status'], 'payment_booking_status');
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_centavos');
            $table->string('reference', 120)->nullable();
            $table->text('reason');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at');
            $table->timestamps();
            $table->index(['booking_id', 'processed_at'], 'refund_booking_date');
        });

        Schema::table('content_posts', function (Blueprint $table) {
            $table->string('image_disk', 32)->default('public')->after('image_path');
            $table->string('image_url', 2048)->nullable()->after('image_disk');
            $table->string('image_mime', 120)->nullable()->after('image_url');
            $table->unsignedBigInteger('image_bytes')->nullable()->after('image_mime');
        });
    }

    public function down(): void
    {
        Schema::table('content_posts', function (Blueprint $table) {
            $table->dropColumn(['image_disk', 'image_url', 'image_mime', 'image_bytes']);
        });

        Schema::dropIfExists('payment_refunds');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payment_booking_status');
            $table->dropColumn(['proof_disk', 'proof_url', 'proof_mime', 'proof_bytes']);
        });

        Schema::dropIfExists('waitlist_offers');
        Schema::dropIfExists('booking_attendances');
        Schema::dropIfExists('booking_slot_claims');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('booking_court_status_start');
            $table->dropIndex('booking_user_status_start');
            $table->dropColumn(['expires_at', 'no_show_at']);
        });

        Schema::table('court_photos', function (Blueprint $table) {
            $table->dropColumn(['storage_disk', 'storage_url', 'mime_type', 'size_bytes']);
        });

        Schema::dropIfExists('court_verification_claims');

        Schema::table('court_verifications', function (Blueprint $table) {
            $table->dropColumn(['evidence_disk', 'evidence_mime', 'evidence_bytes']);
        });

        Schema::table('owner_applications', function (Blueprint $table) {
            $table->dropColumn(['evidence_disk', 'evidence_url', 'evidence_mime', 'evidence_bytes']);
        });

        Schema::table('courts', function (Blueprint $table) {
            $table->dropIndex('court_publication_lookup');
            $table->dropIndex('court_location_lookup');
            $table->dropColumn('verification_invalidated_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['anonymized_reference']);
            $table->dropIndex(['closed_at']);
            $table->dropColumn(['closed_at', 'anonymized_reference']);
        });
    }
};
