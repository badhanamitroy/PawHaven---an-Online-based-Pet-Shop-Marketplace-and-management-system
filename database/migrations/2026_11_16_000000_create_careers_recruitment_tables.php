<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 150);
            $table->string('slug', 180)->unique();
            $table->string('position_type', 20)->index();
            $table->string('summary', 500);
            $table->longText('description');
            $table->longText('responsibilities')->nullable();
            $table->longText('requirements')->nullable();
            $table->string('employment_type', 60)->nullable();
            $table->unsignedSmallInteger('openings')->default(1);
            $table->date('application_deadline')->nullable()->index();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'application_deadline']);
            $table->unique(['id', 'company_id'], 'job_postings_id_company_unique');
        });

        Schema::create('job_posting_branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['job_posting_id', 'branch_id'], 'job_posting_branches_unique');
            $table->foreign(['job_posting_id', 'company_id'], 'job_posting_branches_posting_company_fk')
                ->references(['id', 'company_id'])->on('job_postings')->cascadeOnDelete();
            $table->foreign(['branch_id', 'company_id'], 'job_posting_branches_branch_company_fk')
                ->references(['id', 'company_id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('job_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->restrictOnDelete();
            $table->foreignId('preferred_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('full_name', 150);
            $table->string('email')->index();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->longText('experience')->nullable();
            $table->longText('motivation')->nullable();
            $table->string('cv_path', 500)->nullable();
            $table->string('status', 32)->default('submitted')->index();
            $table->longText('internal_notes')->nullable();
            $table->char('active_submission_key', 64)->nullable()->unique();
            $table->timestamp('submitted_at')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['job_posting_id', 'status', 'submitted_at']);
            $table->foreign(['job_posting_id', 'preferred_branch_id'], 'job_applications_posting_preferred_branch_fk')
                ->references(['job_posting_id', 'branch_id'])->on('job_posting_branches')->restrictOnDelete();
        });

        Schema::create('employment_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_application_id')->constrained('job_applications')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('offered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('employee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('active_application_id')->nullable()->constrained('job_applications')->nullOnDelete();
            $table->unique('active_application_id', 'employment_offers_one_active_per_application_unique');
            $table->string('position_type', 20);
            $table->string('candidate_email');
            $table->longText('employment_terms');
            $table->longText('compensation')->nullable();
            $table->timestamp('acceptance_deadline')->index();
            $table->string('status', 32)->default('offered')->index();
            $table->char('token_hash', 64)->unique();
            $table->unsignedSmallInteger('resend_count')->default(0);
            $table->timestamp('issued_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['job_application_id', 'status']);
        });

        Schema::create('employment_offer_otp_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offer_id')->constrained('employment_offers')->cascadeOnDelete();
            $table->foreignId('active_offer_id')->nullable()->constrained('employment_offers')->nullOnDelete();
            $table->unique('active_offer_id', 'offer_otp_one_active_challenge_unique');
            $table->string('email');
            $table->string('otp_hash', 255);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();
            $table->index(['offer_id', 'expires_at', 'consumed_at', 'invalidated_at']);
        });

        Schema::table('employment_offers', function (Blueprint $table): void {
            $table->foreign(['branch_id', 'company_id'], 'employment_offers_branch_company_fk')
                ->references(['id', 'company_id'])->on('branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_offer_otp_challenges');
        Schema::dropIfExists('employment_offers');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_posting_branches');
        Schema::dropIfExists('job_postings');
    }
};
