<?php

use App\Enums\RfpStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->string('status', 20)->default(RfpStatus::Draft->value)->index();
            $table->text('problem_objectives');
            $table->text('scope');
            $table->text('timeline');
            $table->text('evaluation_criteria');
            $table->text('proposal_format');
            $table->boolean('ai_assist_enabled')->default(false);
            $table->json('ai_suggestions')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('in_review_at')->nullable()->index();
            $table->timestamp('awarded_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'published_at'], 'rfps_company_status_publish_idx');
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('rfps', function (Blueprint $table): void {
                $table->fullText(['title', 'problem_objectives', 'scope'], 'rfps_fulltext_title_scope');
            });
        }
    }

    public function down(): void
    {
        if (
            in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            && Schema::hasTable('rfps')
        ) {
            Schema::table('rfps', function (Blueprint $table): void {
                $table->dropFullText('rfps_fulltext_title_scope');
            });
        }

        Schema::dropIfExists('rfps');
    }
};
