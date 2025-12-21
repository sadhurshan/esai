<?php

use App\Jobs\IndexDocumentForSearchJob;
use App\Models\AiChatThread;
use App\Models\Document;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$thread = AiChatThread::query()->latest('id')->first();

if (! $thread) {
    fwrite(STDERR, "No AI chat threads found; cannot infer company context.\n");
    exit(1);
}

$companyId = $thread->company_id;

$documents = [
    [
        'filename' => 'RFQ-Launch-Plan.txt',
        'kind' => 'rfq',
        'category' => 'technical',
        'visibility' => 'private',
        'text' => <<<TEXT
Launch plan for upcoming RFQ covering custom machined brackets.
- Budget: $480k total, split across two phases.
- Target suppliers: Tier-1 CNC partners with AS9100 certification.
- Non-negotiables: traceable aluminum billet, 14 day prototype lead time, PPAP package before volume award.
TEXT,
    ],
    [
        'filename' => 'Supplier-Scorecard.txt',
        'kind' => 'supplier',
        'category' => 'qa',
        'visibility' => 'private',
        'text' => <<<TEXT
Supplier evaluation for Meridian Metals.
Quality: 97% lot acceptance.
OTD: 92% average over last 4 quarters.
Strengths: responsive expediters, deep titanium experience.
Risks: limited redundancy on anodize step, expiring ISO cert (renewal due Feb).
TEXT,
    ],
    [
        'filename' => 'Inventory-Risk-Note.txt',
        'kind' => 'other',
        'category' => 'logistics',
        'visibility' => 'private',
        'text' => <<<TEXT
Inventory watchlist memo for Q1.
Critical SKUs: BKT-8831 carriage bolts, gasket kit GSK-552, and cable assembly CAB-9002.
Drivers: ramp for EV program Delta, resin volatility, and single-source crimps.
Recommendation: build three week buffer, renegotiate consignment with Delta Wire.
TEXT,
    ],
];

foreach ($documents as $index => $definition) {
    $basename = pathinfo($definition['filename'], PATHINFO_FILENAME) ?: 'document';
    $extension = pathinfo($definition['filename'], PATHINFO_EXTENSION) ?: 'txt';
    $path = sprintf('ai/seed/%s.%s', Str::slug($basename) ?: Str::random(12), strtolower($extension));
    $hash = hash('sha256', $definition['text']);

    $document = Document::query()->updateOrCreate(
        [
            'company_id' => $companyId,
            'filename' => $definition['filename'],
        ],
        [
            'documentable_type' => AiChatThread::class,
            'documentable_id' => $thread->getKey(),
            'kind' => $definition['kind'],
            'category' => $definition['category'],
            'visibility' => $definition['visibility'],
            'version_number' => 1,
            'path' => $path,
            'mime' => 'text/plain',
            'size_bytes' => strlen($definition['text']),
            'hash' => $hash,
            'meta' => [
                'text' => $definition['text'],
                'seeded_by' => 'seed_vector_store.php',
            ],
        ]
    );

    $docVersion = 'v' . now()->format('YmdHis') . '-' . ($index + 1);

    IndexDocumentForSearchJob::dispatchSync(
        $companyId,
        $document->getKey(),
        $docVersion
    );

    echo sprintf(
        "Indexed document #%d (%s) for company %d as %s\n",
        $document->getKey(),
        $definition['filename'],
        $companyId,
        $docVersion
    );
}

