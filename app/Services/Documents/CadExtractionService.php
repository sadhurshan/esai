<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Part;
use App\Services\Documents\DocumentTextExtractor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CadExtractionService
{
    private const MATERIAL_PATTERNS = [
        'aluminum 6061' => 'aluminum 6061',
        '6061' => 'aluminum 6061',
        'aluminum 7075' => 'aluminum 7075',
        '7075' => 'aluminum 7075',
        'stainless 304' => 'stainless steel 304',
        'stainless 316' => 'stainless steel 316',
        'steel 304' => 'stainless steel 304',
        'steel 316' => 'stainless steel 316',
        'abs' => 'abs',
        'peek' => 'peek',
        'titanium' => 'titanium',
        'carbon steel' => 'carbon steel',
        'mild steel' => 'mild steel',
    ];

    private const FINISH_PATTERNS = [
        'anodize' => 'anodizing',
        'anodizing' => 'anodizing',
        'powder coat' => 'powder coat',
        'black oxide' => 'black oxide',
        'passivation' => 'passivation',
        'polish' => 'polishing',
        'polished' => 'polishing',
    ];

    private const PROCESS_PATTERNS = [
        'cnc' => 'cnc',
        'cnc milling' => 'cnc',
        'cnc turning' => 'cnc',
        'sheet metal' => 'sheet_metal',
        'sheet_metal' => 'sheet_metal',
        'injection molding' => 'injection_molding',
        'injection_molding' => 'injection_molding',
        '3d printing' => '3d_printing',
        '3d_printing' => '3d_printing',
        'additive' => '3d_printing',
        'casting' => 'casting',
    ];

    private const GDT_KEYWORDS = [
        'gdt',
        'gd&t',
        'datum',
        'true position',
        'position',
        'profile',
        'flatness',
        'perpendicularity',
        'cylindricity',
        'runout',
        'mmc',
        'lmc',
        'feature control frame',
    ];

    /**
     * @return array{extracted: array<string, mixed>, gdt: array<string, mixed>, similar_parts: array<int, array<string, mixed>>}
     */
    public function extract(Document $document): array
    {
        $sourceText = $this->buildSourceText($document);
        $materials = $this->matchPatterns($sourceText, self::MATERIAL_PATTERNS);
        $finishes = $this->matchPatterns($sourceText, self::FINISH_PATTERNS);
        $processes = $this->matchPatterns($sourceText, self::PROCESS_PATTERNS);
        $tolerances = $this->extractToleranceTags($sourceText);

        $gdtSignals = $this->findGdtSignals($sourceText);
        $gdtComplex = count($gdtSignals) >= 2;

        $similarParts = $this->findSimilarParts($document->company_id, $materials, $finishes, $processes);

        return [
            'extracted' => [
                'materials' => $materials,
                'finishes' => $finishes,
                'processes' => $processes,
                'tolerances' => $tolerances,
            ],
            'gdt' => [
                'complex' => $gdtComplex,
                'signals' => $gdtSignals,
            ],
            'similar_parts' => $similarParts,
        ];
    }

    private function buildSourceText(Document $document): string
    {
        $filename = strtolower((string) ($document->filename ?? ''));
        $meta = $document->meta ?? [];
        $metaText = trim((string) Arr::get($meta, 'text'));

        $body = $metaText;

        if ($body === '') {
            $body = $this->extractDocumentText($document);
        }

        return trim($filename.' '.$body);
    }

    private function readDocumentContents(Document $document): string
    {
        $path = (string) ($document->path ?? '');

        if ($path === '') {
            return '';
        }

        $disk = config('documents.disk', config('filesystems.default', 'public'));

        try {
            return (string) Storage::disk($disk)->get($path);
        } catch (Throwable) {
            return '';
        }
    }

    private function extractDocumentText(Document $document): string
    {
        $extractor = $this->resolveTextExtractor();

        if ($extractor !== null) {
            try {
                $text = trim((string) ($extractor->extract($document) ?? ''));
                if ($text !== '') {
                    return $text;
                }
            } catch (Throwable $exception) {
                Log::warning('cad_text_extractor_failed', ['error' => $exception->getMessage()]);
            }
        }

        return $this->readDocumentContents($document);
    }

    private function resolveTextExtractor(): ?DocumentTextExtractor
    {
        try {
            return app()->bound(DocumentTextExtractor::class)
                ? app(DocumentTextExtractor::class)
                : null;
        } catch (Throwable $exception) {
            Log::warning('cad_text_extractor_missing', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * @param array<string, string> $patterns
     * @return array<int, string>
     */
    private function matchPatterns(string $source, array $patterns): array
    {
        $hits = [];
        $haystack = strtolower($source);

        foreach ($patterns as $pattern => $normalized) {
            if ($pattern === '') {
                continue;
            }

            if (str_contains($haystack, $pattern)) {
                $hits[] = $normalized;
            }
        }

        return array_values(array_unique($hits));
    }

    /**
     * @return array<int, string>
     */
    private function extractToleranceTags(string $source): array
    {
        $matches = [];
        $haystack = strtolower($source);

        if (preg_match_all('/iso\s?2768-?[fmhkc]/i', $haystack, $isoMatches)) {
            foreach ($isoMatches[0] as $match) {
                $matches[] = strtoupper(str_replace(' ', '', $match));
            }
        }

        if (preg_match_all('/\+\/-\s*\d+(?:\.\d+)?\s*(mm|in|"|inch)?/i', $haystack, $plusMinusMatches)) {
            foreach ($plusMinusMatches[0] as $match) {
                $matches[] = strtoupper(trim($match));
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @return array<int, string>
     */
    private function findGdtSignals(string $source): array
    {
        $signals = [];
        $haystack = strtolower($source);

        foreach (self::GDT_KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword)) {
                $signals[] = $keyword;
            }
        }

        return array_values(array_unique($signals));
    }

    /**
     * @param array<int, string> $materials
     * @param array<int, string> $finishes
     * @param array<int, string> $processes
     * @return array<int, array<string, mixed>>
     */
    private function findSimilarParts(?int $companyId, array $materials, array $finishes, array $processes): array
    {
        if ($companyId === null) {
            return [];
        }

        $tokens = array_values(array_unique(array_merge($materials, $finishes, $processes)));

        if ($tokens === []) {
            return [];
        }

        $builder = Part::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($tokens): void {
                foreach ($tokens as $token) {
                    $query->orWhere('parts.part_number', 'like', '%'.$token.'%')
                        ->orWhere('parts.name', 'like', '%'.$token.'%')
                        ->orWhere('parts.spec', 'like', '%'.$token.'%');
                }
            })
            ->select(['id', 'part_number', 'name', 'spec'])
            ->limit(5);

        return $builder
            ->get()
            ->map(static fn (Part $part): array => [
                'id' => (int) $part->getKey(),
                'part_number' => $part->part_number,
                'name' => $part->name,
                'spec' => $part->spec,
            ])
            ->all();
    }
}
