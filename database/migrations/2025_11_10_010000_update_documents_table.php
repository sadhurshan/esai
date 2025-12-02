<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->enum('category', [
                'technical',
                'commercial',
                'qa',
                'logistics',
                'financial',
                'communication',
                'esg',
                'other',
            ])->default('other')->after('documentable_id');

            $table->enum('visibility', ['private', 'company', 'public'])
                ->default('company')
                ->after('category');

            $table->unsignedInteger('version_number')->default(1)->after('visibility');
            $table->timestamp('expires_at')->nullable()->after('version_number');
            $table->char('hash', 64)->nullable()->after('mime');
            $table->json('watermark')->nullable()->after('hash');
            $table->json('meta')->nullable()->after('watermark');

            $table->index(['company_id', 'category'], 'documents_company_category_index');
            $table->index('visibility', 'documents_visibility_index');
            $table->index('expires_at', 'documents_expires_at_index');
        });

        DB::table('documents')
            ->select(['id', 'kind', 'hash_sha256', 'version'])
            ->orderBy('id')
            ->chunkById(100, function ($documents): void {
                foreach ($documents as $document) {
                    $category = match ($document->kind) {
                        'rfq', 'quote', 'po' => 'technical',
                        'invoice' => 'financial',
                        'supplier' => 'qa',
                        default => 'other',
                    };

                    DB::table('documents')
                        ->where('id', $document->id)
                        ->update([
                            'category' => $category,
                            'hash' => $document->hash_sha256,
                            'version_number' => $document->version ?? 1,
                            'visibility' => 'company',
                            'meta' => json_encode([]),
                            'watermark' => json_encode([]),
                        ]);
                }
            });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE documents MODIFY kind ENUM('rfq','quote','rfp','rfp_proposal','po','grn_attachment','invoice','supplier','part','cad','manual','certificate','esg_pack','other') NOT NULL DEFAULT 'other'"
            );
        }

        DB::table('documents')
            ->whereIn('kind', ['grn', 'ncr', 'template'])
            ->update(['kind' => 'other']);

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn(['hash_sha256', 'version']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->char('hash_sha256', 64)->nullable()->after('mime');
            $table->unsignedInteger('version')->default(1)->after('hash_sha256');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE documents MODIFY kind ENUM('rfq','quote','rfp','rfp_proposal','po','grn_attachment','invoice','grn','ncr','supplier','template','other') NOT NULL DEFAULT 'other'"
            );
        }

        DB::table('documents')
            ->select(['id', 'hash', 'version_number'])
            ->orderBy('id')
            ->chunkById(100, function ($documents): void {
                foreach ($documents as $document) {
                    DB::table('documents')
                        ->where('id', $document->id)
                        ->update([
                            'hash_sha256' => $document->hash,
                            'version' => $document->version_number ?? 1,
                        ]);
                }
            });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_company_category_index');
            $table->dropIndex('documents_visibility_index');
            $table->dropIndex('documents_expires_at_index');

            $table->dropColumn([
                'category',
                'visibility',
                'version_number',
                'expires_at',
                'hash',
                'watermark',
                'meta',
            ]);
        });
    }
};
