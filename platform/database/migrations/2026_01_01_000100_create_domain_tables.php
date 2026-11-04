<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------- accounts
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->string('type', 20); // individual | organization
            $t->string('name');
            $t->string('national_id')->nullable();          // الهوية الوطنية / الإقامة
            $t->string('unified_number')->nullable();       // الرقم الوطني الموحد (700)
            $t->string('commercial_registration')->nullable();
            $t->text('address')->nullable();
            $t->string('national_address')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->text('internal_notes')->nullable();
            $t->boolean('is_synthetic')->default(false);
            $t->timestampTz('archived_at')->nullable();
            $t->timestampsTz();
            $t->index('name');
        });

        Schema::create('client_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->boolean('can_view')->default(true);
            $t->boolean('can_submit_claims')->default(false);
            $t->timestampTz('revoked_at')->nullable();
            $t->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampsTz();
            $t->unique(['client_id', 'user_id']);
        });

        Schema::create('invitations', function (Blueprint $t) {
            $t->id();
            $t->string('email');
            $t->string('name');
            $t->string('role', 20);
            $t->string('purpose', 20)->default('invite'); // invite | reset
            $t->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $t->boolean('can_view')->default(true);
            $t->boolean('can_submit_claims')->default(false);
            $t->string('token_hash', 64)->unique();
            $t->timestampTz('expires_at');
            $t->timestampTz('accepted_at')->nullable();
            $t->timestampTz('revoked_at')->nullable();
            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampsTz();
        });

        // ---------------------------------------------------------------- documents
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $t->nullableMorphs('documentable');
            $t->string('category', 40);
            $t->string('party', 20)->default('client'); // client | opponent | office | court
            $t->string('label')->nullable();
            $t->text('original_name');                 // مشفّر على مستوى النموذج
            $t->string('extension', 10);
            $t->string('mime', 100);
            $t->unsignedBigInteger('size');
            $t->string('sha256', 64);
            $t->string('storage_path');
            $t->string('key_id', 16);
            $t->string('scan_status', 20)->default('pending'); // pending | clean | infected | rejected | error
            $t->string('scan_engine')->nullable();
            $t->unsignedSmallInteger('scan_attempts')->default(0);
            $t->timestampTz('scanned_at')->nullable();
            $t->boolean('shared_with_client')->default(false);
            $t->timestampTz('shared_at')->nullable();
            $t->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('retention_class', 20)->default('standard');
            $t->timestampTz('deleted_at')->nullable();
            $t->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('deletion_reason')->nullable();
            $t->timestampsTz();
            $t->index(['scan_status']);
        });

        Schema::create('powers_of_attorney', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained()->restrictOnDelete();
            $t->string('number');
            $t->date('issued_on')->nullable();
            $t->date('expires_on')->nullable();
            $t->string('status', 20)->default('active'); // active | revoked
            $t->date('revoked_on')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $t->timestampsTz();
            $t->index(['client_id', 'expires_on']);
        });

        // ---------------------------------------------------------------- cases
        Schema::create('case_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('is_active')->default(true);
            $t->timestampsTz();
        });

        Schema::create('legal_cases', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->foreignId('client_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->foreignId('category_id')->nullable()->constrained('case_categories')->nullOnDelete();
            $t->string('client_role', 20)->default('plaintiff'); // plaintiff | defendant | other
            $t->string('stage', 30)->default('intake');
            $t->string('work_status', 30)->default('draft');
            $t->string('outcome', 30)->nullable();
            $t->decimal('claim_amount', 18, 2)->nullable();
            $t->char('currency', 3)->default('SAR');
            $t->foreignId('power_of_attorney_id')->nullable()->constrained('powers_of_attorney')->nullOnDelete();
            $t->text('client_summary')->nullable();
            $t->timestampTz('client_summary_updated_at')->nullable();
            $t->text('internal_notes')->nullable();
            $t->unsignedBigInteger('source_claim_id')->nullable()->unique();
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestampTz('archived_at')->nullable();
            $t->timestampsTz();
            $t->index(['client_id', 'work_status']);
        });

        Schema::create('case_parties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('legal_case_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('role', 20); // plaintiff | defendant | other
            $t->string('party_type', 20)->default('individual');
            $t->string('identifier')->nullable();
            $t->string('notes')->nullable();
            $t->timestampsTz();
        });

        Schema::create('reconciliation_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('legal_case_id')->constrained()->cascadeOnDelete();
            $t->string('request_number')->nullable();
            $t->date('submitted_on')->nullable();
            // submitted | in_progress | settled | failed | other_party_absent | closed
            $t->string('status', 30)->default('submitted');
            $t->date('outcome_on')->nullable();
            $t->decimal('agreed_amount', 18, 2)->nullable();
            $t->char('currency', 3)->default('SAR');
            $t->foreignId('report_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestampTz('moved_to_court_at')->nullable();
            $t->timestampsTz();
        });

        Schema::create('court_filings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('legal_case_id')->constrained()->cascadeOnDelete();
            $t->string('filing_request_number')->nullable(); // رقم طلب التقييد
            $t->date('submitted_on')->nullable();
            // draft | submitted | returned | resubmitted | registered | rejected
            $t->string('status', 30)->default('draft');
            $t->string('case_number')->nullable();           // رقم القضية
            $t->date('registered_on')->nullable();
            $t->date('referred_on')->nullable();
            $t->string('court')->nullable();
            $t->string('circuit')->nullable();
            $t->text('notes')->nullable();
            $t->timestampsTz();
            $t->index('case_number');
            $t->index('filing_request_number');
        });

        Schema::create('hearings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('legal_case_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 20); // reconciliation | court
            $t->foreignId('reconciliation_request_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('court_filing_id')->nullable()->constrained()->nullOnDelete();
            $t->timestampTz('scheduled_at')->nullable();
            $t->string('authority')->nullable();
            $t->string('meeting_url')->nullable();
            // scheduled | completed | postponed | cancelled
            $t->string('status', 20)->default('scheduled');
            $t->json('attendance')->nullable();
            $t->text('result')->nullable();
            $t->text('directives')->nullable();
            $t->foreignId('minutes_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $t->foreignId('next_hearing_id')->nullable()->constrained('hearings')->nullOnDelete();
            $t->string('change_reason')->nullable();
            $t->timestampTz('completed_at')->nullable();
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestampsTz();
            $t->index(['status', 'scheduled_at']);
        });

        Schema::create('memos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('legal_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('hearing_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 30); // statement_of_claim | response | reply | other
            $t->string('title');
            $t->string('status', 30)->default('draft'); // draft | ready_for_review | approved | filed
            $t->date('due_on')->nullable();
            $t->timestampTz('approved_at')->nullable();
            $t->date('filed_on')->nullable();
            $t->foreignId('filed_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $t->unsignedInteger('current_version')->default(0);
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestampsTz();
        });

        Schema::create('memo_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('memo_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->longText('body_html');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampTz('created_at');
            $t->unique(['memo_id', 'version']);
        });

        Schema::create('judgments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('legal_case_id')->unique()->constrained()->cascadeOnDelete();
            // full_win | partial_win | dismissed | inadmissible | struck_off | suspended
            $t->string('outcome', 30);
            $t->date('pronounced_on')->nullable();
            $t->string('deed_status', 20)->default('awaiting'); // awaiting | issued
            $t->string('deed_number')->nullable();
            $t->date('deed_date')->nullable();
            $t->foreignId('deed_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $t->string('objection_status', 30)->nullable(); // none | objected | waived | not_applicable
            $t->date('objection_filed_on')->nullable();
            $t->string('finality_status', 20)->default('not_final'); // not_final | final
            $t->timestampTz('finality_confirmed_at')->nullable();
            $t->foreignId('finality_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('finality_document_id')->nullable()->constrained('documents')->nullOnDelete();
            // pending | pursue | not_pursue | not_applicable
            $t->string('execution_decision', 20)->default('pending');
            $t->date('resumed_on')->nullable(); // استكمال الشطب / الوقف
            $t->text('notes')->nullable();
            $t->timestampsTz();
        });

        // ---------------------------------------------------------------- execution
        Schema::create('execution_requests', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->foreignId('client_id')->constrained()->restrictOnDelete();
            $t->foreignId('legal_case_id')->nullable()->constrained()->nullOnDelete();
            $t->string('execution_type', 20);   // financial | direct
            $t->string('instrument_type', 40);  // judgment_deed | electronic_promissory_note | other
            $t->string('instrument_label')->nullable();
            $t->string('request_number')->nullable();
            $t->string('status', 30)->default('draft');
            $t->date('submitted_on')->nullable();
            $t->date('accepted_on')->nullable();
            $t->date('order_issued_on')->nullable();
            $t->date('notified_on')->nullable();
            $t->string('notification_method', 20)->nullable(); // notification | publication
            $t->date('enforcement_started_on')->nullable();
            $t->string('deed_number')->nullable();
            $t->date('deed_date')->nullable();
            $t->string('note_number')->nullable();
            $t->decimal('note_amount', 18, 2)->nullable();
            $t->char('note_currency', 3)->nullable();
            $t->date('note_issued_on')->nullable();
            $t->date('note_due_on')->nullable();
            $t->string('subject_location')->nullable();
            $t->text('obligation_description')->nullable();
            $t->decimal('amount_claimed', 18, 2)->nullable();
            $t->char('currency', 3)->default('SAR');
            $t->text('client_summary')->nullable();
            $t->timestampTz('client_summary_updated_at')->nullable();
            $t->text('internal_notes')->nullable();
            $t->boolean('weekly_followup')->default(false);
            $t->unsignedBigInteger('source_claim_id')->nullable()->unique();
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestampsTz();
            $t->index('request_number');
        });

        Schema::create('execution_parties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('execution_request_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('role', 20); // applicant | respondent
            $t->string('party_type', 20)->default('individual');
            $t->string('identifier')->nullable();
            $t->timestampsTz();
        });

        Schema::create('execution_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('execution_request_id')->constrained()->cascadeOnDelete();
            $t->string('label', 60);     // "قرار 34" | "قرار 46" | تسمية كما ظهرت فعليًا
            $t->string('number')->nullable();
            $t->date('decided_on')->nullable();
            $t->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestampsTz();
        });

        Schema::create('action_types', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->unsignedSmallInteger('resubmission_interval_days')->nullable();
            $t->string('interval_day_type', 20)->default('calendar');
            $t->boolean('interval_from_last_same_type')->default(true);
            $t->string('condition_note')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestampsTz();
        });

        Schema::create('execution_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('execution_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('action_type_id')->constrained()->restrictOnDelete();
            $t->date('submitted_on')->nullable();
            $t->string('status', 20)->default('submitted'); // submitted | accepted | rejected | completed
            $t->text('result')->nullable();
            $t->date('eligible_resubmission_on')->nullable();
            $t->date('next_followup_on')->nullable();
            $t->text('notes')->nullable();
            $t->timestampsTz();
        });

        // ---------------------------------------------------------------- claims
        Schema::create('claims', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->nullable()->unique();
            $t->foreignId('client_id')->constrained()->restrictOnDelete();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            // draft | under_review | needs_completion | converted_case | converted_execution | closed
            $t->string('status', 30)->default('draft');
            $t->string('client_name');
            $t->string('opponent_type', 20)->nullable();
            $t->string('opponent_name')->nullable();
            $t->string('opponent_identifier')->nullable();
            $t->string('opponent_address')->nullable();
            $t->string('opponent_phone')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->char('currency', 3)->default('SAR');
            $t->text('description')->nullable();
            $t->text('missing_document_reason')->nullable();
            $t->boolean('missing_documents')->default(false);
            $t->timestampTz('submitted_at')->nullable();
            $t->uuid('submit_key')->nullable()->unique();
            $t->foreignId('converted_case_id')->nullable()->constrained('legal_cases')->nullOnDelete();
            $t->foreignId('converted_execution_id')->nullable()->constrained('execution_requests')->nullOnDelete();
            $t->text('closed_reason')->nullable();
            $t->text('internal_notes')->nullable();
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestampsTz();
        });

        Schema::create('claim_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('kind', 30); // completion_request | internal_note | client_reply | status
            $t->text('body');
            $t->boolean('visible_to_client')->default(false);
            $t->timestampsTz();
        });

        // ---------------------------------------------------------------- collections
        Schema::create('collections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained()->restrictOnDelete();
            $t->foreignId('legal_case_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('execution_request_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('reconciliation_request_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kind', 20);   // claimed | agreed | seized | received
            $t->string('source', 20); // reconciliation | execution | other
            $t->decimal('amount', 18, 2);
            $t->char('currency', 3);
            $t->date('occurred_on');
            $t->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $t->string('description')->nullable();
            $t->boolean('visible_to_client')->default(true);
            $t->uuid('entry_key')->unique();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampsTz();
            $t->index(['client_id', 'kind', 'currency']);
        });

        // ---------------------------------------------------------------- scheduling
        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->date('date')->unique();
            $t->string('name');
            $t->string('kind', 20)->default('official');
            $t->boolean('reviewed')->default(false);
            $t->timestampsTz();
        });

        Schema::create('deadline_rules', function (Blueprint $t) {
            $t->id();
            $t->string('code', 60);
            $t->unsignedSmallInteger('version')->default(1);
            $t->string('name');
            $t->string('deadline_kind', 40);
            $t->unsignedSmallInteger('duration')->nullable();
            $t->string('day_type', 20)->default('calendar'); // calendar | business
            $t->string('start_event', 40)->nullable();      // notified_on | publication | deed_date ...
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->text('source_reference');
            $t->string('status', 20)->default('draft'); // draft | active | retired
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampTz('reviewed_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestampsTz();
            $t->unique(['code', 'version']);
        });

        Schema::create('deadlines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('legal_case_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('execution_request_id')->nullable()->constrained()->cascadeOnDelete();
            // deed_followup | objection | execution_compliance | resubmission | court | custom
            $t->string('kind', 40);
            // estimated | needs_review | confirmed | needs_determination
            $t->string('certainty', 30);
            $t->string('reason');
            $t->date('start_on')->nullable();
            $t->date('end_on')->nullable();
            $t->unsignedSmallInteger('duration')->nullable();
            $t->string('day_type', 20)->nullable();
            $t->text('basis')->nullable();
            $t->foreignId('rule_id')->nullable()->constrained('deadline_rules')->nullOnDelete();
            $t->string('source', 30)->default('manual');
            $t->string('status', 20)->default('open'); // open | alerted | closed
            $t->timestampTz('alerted_at')->nullable();
            $t->text('extension_reason')->nullable();
            $t->string('unique_key')->nullable()->unique();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampsTz();
            $t->index(['status', 'end_on']);
        });

        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('legal_case_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('execution_request_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('source_hearing_id')->nullable()->constrained('hearings')->nullOnDelete();
            $t->string('kind', 40)->default('manual');
            $t->string('title');
            $t->text('description')->nullable();
            // date | relative | next_hearing | unscheduled
            $t->string('due_mode', 20)->default('date');
            $t->date('due_on')->nullable();
            $t->date('relative_from')->nullable();
            $t->string('relative_event')->nullable();
            $t->smallInteger('relative_days')->nullable();
            $t->string('relative_day_type', 20)->nullable();
            $t->foreignId('anchor_hearing_id')->nullable()->constrained('hearings')->nullOnDelete();
            $t->smallInteger('anchor_offset_days')->nullable();
            $t->string('status', 20)->default('open'); // open | done | cancelled
            $t->timestampTz('completed_at')->nullable();
            $t->string('unique_key')->nullable()->unique();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestampsTz();
            $t->index(['status', 'due_on']);
        });

        Schema::create('task_due_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->date('old_due_on')->nullable();
            $t->date('new_due_on')->nullable();
            $t->string('reason');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampTz('created_at');
        });

        // ---------------------------------------------------------------- timeline & audit
        Schema::create('timeline_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('legal_case_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('execution_request_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('type', 60);
            $t->string('title');
            $t->text('details')->nullable();
            $t->timestampTz('occurred_at');
            $t->boolean('client_visible')->default(false);
            $t->nullableMorphs('subject');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampsTz();
            $t->index(['legal_case_id', 'occurred_at']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 80);
            $t->string('subject_type', 80)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('meta')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['subject_type', 'subject_id']);
            $t->index('action');
        });

        Schema::create('saved_filters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('page', 40);
            $t->string('name');
            $t->json('query');
            $t->timestampsTz();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value');
            $t->timestampsTz();
        });

        Schema::create('system_checks', function (Blueprint $t) {
            $t->id();
            $t->string('check', 40);
            $t->string('status', 10); // ok | warn | fail
            $t->string('message');
            $t->json('data')->nullable();
            $t->timestampTz('checked_at');
            $t->index(['check', 'checked_at']);
        });

        Schema::create('backup_runs', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('status', 20); // running | completed | failed | verified
            $t->unsignedBigInteger('size')->nullable();
            $t->string('sha256', 64)->nullable();
            $t->unsignedInteger('file_count')->nullable();
            $t->boolean('offsite_copied')->default(false);
            $t->text('error')->nullable();
            $t->timestampTz('started_at');
            $t->timestampTz('finished_at')->nullable();
            $t->timestampTz('verified_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([
            'backup_runs', 'system_checks', 'settings', 'saved_filters', 'audit_logs', 'timeline_events',
            'task_due_changes', 'tasks', 'deadlines', 'deadline_rules', 'holidays', 'collections',
            'claim_messages', 'claims', 'execution_actions', 'action_types', 'execution_decisions',
            'execution_parties', 'execution_requests', 'judgments', 'memo_versions', 'memos', 'hearings',
            'court_filings', 'reconciliation_requests', 'case_parties', 'legal_cases', 'case_categories',
            'powers_of_attorney', 'documents', 'invitations', 'client_memberships', 'clients',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
