<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('incident_reports')) {
            Schema::create('incident_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patrol_log_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('guard_id')->nullable()->constrained('guards')->nullOnDelete();
                $table->foreignId('checkpoint_id')->nullable()->constrained()->nullOnDelete();
                $table->string('title')->nullable();
                $table->string('incident_type')->nullable();
                $table->string('category');
                $table->string('priority')->default('normal');
                $table->string('severity')->default('medium');
                $table->string('location')->nullable();
                $table->timestamp('incident_at');
                $table->timestamp('occurred_at')->nullable();
                $table->timestamp('reported_at')->nullable();
                $table->text('description');
                $table->string('status')->default('submitted');
                $table->text('admin_notes')->nullable();
                $table->text('action_taken')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('incident_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('incident_reports', 'checkpoint_id')) {
                $table->foreignId('checkpoint_id')->nullable()->after('patrol_log_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('incident_reports', 'category')) {
                $table->string('category')->nullable()->after('checkpoint_id');
            }

            if (! Schema::hasColumn('incident_reports', 'priority')) {
                $table->string('priority')->default('normal')->after('category');
            }

            if (! Schema::hasColumn('incident_reports', 'incident_at')) {
                $table->timestamp('incident_at')->nullable()->after('priority');
            }

            if (! Schema::hasColumn('incident_reports', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_reports');
    }
};
