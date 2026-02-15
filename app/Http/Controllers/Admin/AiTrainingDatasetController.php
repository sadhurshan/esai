<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\UploadAiTrainingDatasetRequest;
use App\Models\Company;
use App\Models\ModelTrainingJob;
use App\Support\Audit\AuditLogger;
use App\Support\Security\VirusScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class AiTrainingDatasetController extends ApiController
{
    public function __construct(
        private readonly VirusScanner $virusScanner,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function store(UploadAiTrainingDatasetRequest $request): JsonResponse
    {
        $this->authorize('create', ModelTrainingJob::class);

        $companyId = (int) $request->validated('company_id');
        $company = Company::query()->findOrFail($companyId);

        $file = $request->dataset();
        $this->virusScanner->assertClean($file, [
            'context' => 'ai_training_dataset',
            'company_id' => $companyId,
        ]);

        $datasetPath = $this->storeDataset($file);
        $datasetId = basename($datasetPath);

        $this->auditLogger->custom($company, 'ai_training_dataset_uploaded', [
            'dataset_upload_id' => $datasetId,
            'filename' => $file->getClientOriginalName(),
            'stored_path' => $datasetPath,
            'size_bytes' => $file->getSize(),
        ]);

        return $this->ok([
            'dataset_upload_id' => $datasetId,
            'filename' => $file->getClientOriginalName(),
            'size_bytes' => $file->getSize(),
            'stored_path' => $datasetPath,
        ], 'Training dataset uploaded.');
    }

    private function storeDataset(UploadedFile $file): string
    {
        $directory = (string) (env('AI_CHAT_TRAINING_DATASET_DIR') ?: storage_path('ai_chat_datasets'));
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create dataset directory.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jsonl');
        $filename = Str::uuid()->toString().'.'.$extension;

        $file->move($directory, $filename);

        return $directory.DIRECTORY_SEPARATOR.$filename;
    }
}
