<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scraped_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('scrape_job_id');
            $table->string('name');
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->json('industry_tags')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('contact_person')->nullable();
            $table->json('certifications')->nullable();
            $table->text('product_summary')->nullable();
            $table->string('source_url')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('scrape_job_id')->references('id')->on('supplier_scrape_jobs')->cascadeOnDelete();
            $table->index(['company_id', 'scrape_job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraped_suppliers');
    }
};
