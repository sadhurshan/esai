<?php

use App\Enums\DownloadDocumentType;
use App\Enums\DownloadFormat;
use App\Enums\DownloadJobStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('download_jobs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('requested_by');
            $table->string('document_type', 40);
            $table->unsignedBigInteger('document_id');
            $table->string('reference', 120)->nullable();
            $table->string('format', 12);
            $table->string('status', 20)->default(DownloadJobStatus::Queued->value);
            $table->string('storage_disk', 60)->nullable();
            $table->string('file_path')->nullable();
            $table->string('filename')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('requested_by')->references('id')->on('users');
            $table->index(['company_id', 'status']);
            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_jobs');
    }
};
