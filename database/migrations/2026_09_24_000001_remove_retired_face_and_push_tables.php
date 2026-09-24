<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('face_verification_attempts');
        Schema::dropIfExists('guard_face_descriptors');
        Schema::dropIfExists('push_subscriptions');

        Schema::enableForeignKeyConstraints();

        if (Schema::hasTable('guards') && Schema::hasColumn('guards', 'face_reference')) {
            Schema::table('guards', function (Blueprint $table) {
                $table->dropColumn('face_reference');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('guards') && ! Schema::hasColumn('guards', 'face_reference')) {
            Schema::table('guards', function (Blueprint $table) {
                $table->string('face_reference')->nullable()->after('rfid_uid');
            });
        }

        if (! Schema::hasTable('guard_face_descriptors')) {
            Schema::create('guard_face_descriptors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('guard_id')->constrained('guards')->cascadeOnDelete();
                $table->json('descriptor')->nullable();
                $table->string('model_name')->default('face-api.js');
                $table->string('image_path')->nullable();
                $table->string('capture_type')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('face_verification_attempts')) {
            Schema::create('face_verification_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patrol_log_id')->nullable()->constrained('patrol_logs')->nullOnDelete();
                $table->foreignId('guard_id')->nullable()->constrained('guards')->nullOnDelete();
                $table->string('status')->default('pending');
                $table->decimal('match_distance', 8, 6)->nullable();
                $table->decimal('match_threshold', 8, 6)->default(0.420000);
                $table->string('model_name')->default('face-api.js');
                $table->string('liveness_challenge')->nullable();
                $table->timestamp('liveness_confirmed_at')->nullable();
                $table->string('captured_image_path')->nullable();
                $table->json('captured_descriptor')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('endpoint');
                $table->string('endpoint_hash')->unique();
                $table->text('public_key');
                $table->text('auth_token');
                $table->string('content_encoding')->default('aes128gcm');
                $table->text('user_agent')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }
    }
};
