<?php

use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_documents', 'document_id')) {
                $table->foreignId('document_id')
                    ->nullable()
                    ->after('supplier_id')
                    ->constrained('documents')
                    ->nullOnDelete();
            }
        });

        DB::table('supplier_documents')
            ->select(['id', 'supplier_id', 'company_id', 'path', 'mime', 'size_bytes', 'expires_at', 'created_at', 'updated_at'])
            ->whereNull('document_id')
            ->orderBy('id')
            ->chunkById(100, function ($documents): void {
                foreach ($documents as $document) {
                    $documentId = DB::table('documents')->insertGetId([
                        'company_id' => $document->company_id,
                        'documentable_type' => Supplier::class,
                        'documentable_id' => $document->supplier_id,
                        'kind' => 'certificate',
                        'category' => 'qa',
                        'visibility' => 'company',
                        'version_number' => 1,
                        'expires_at' => $document->expires_at,
                        'path' => $document->path ?? '',
                        'filename' => $this->resolveFilename($document->path, $document->id),
                        'mime' => $document->mime ?? 'application/octet-stream',
                        'size_bytes' => $document->size_bytes ?? 0,
                        'hash' => null,
                        'watermark' => json_encode([]),
                        'meta' => json_encode(['source' => 'supplier_documents_migration']),
                        'created_at' => $document->created_at,
                        'updated_at' => $document->updated_at,
                    ]);

                    DB::table('supplier_documents')
                        ->where('id', $document->id)
                        ->update(['document_id' => $documentId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('supplier_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('supplier_documents', 'document_id')) {
                $table->dropConstrainedForeignId('document_id');
            }
        });
    }

    private function resolveFilename(?string $path, int $id): string
    {
        if (is_string($path) && $path !== '') {
            $name = basename($path);

            if ($name !== '') {
                return $name;
            }
        }

        return 'supplier-document-'.$id.'.bin';
    }
};
