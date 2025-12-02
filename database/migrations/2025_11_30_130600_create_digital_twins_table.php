<?php

use App\Enums\DigitalTwinStatus;
use App\Enums\DigitalTwinVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('digital_twins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('digital_twin_categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('code', 64)->nullable()->unique();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('status', 32)->default(DigitalTwinStatus::Draft->value);
            $table->string('version', 32)->default('1.0.0');
            $table->text('revision_notes')->nullable();
            $table->json('tags')->nullable();
            $table->string('tags_search')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('visibility', 32)->default(DigitalTwinVisibility::Public->value);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('category_id');
            $table->index('status');
            $table->index('visibility');
            $table->index('tags_search');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('CREATE FULLTEXT INDEX digital_twins_title_summary_fulltext ON digital_twins (title, summary)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('digital_twins') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('DROP INDEX digital_twins_title_summary_fulltext ON digital_twins');
        }

        Schema::dropIfExists('digital_twins');
    }
};
