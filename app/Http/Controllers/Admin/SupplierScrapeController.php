<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\ApproveScrapedSupplierRequest;
use App\Http\Requests\Admin\DiscardScrapedSupplierRequest;
use App\Http\Requests\Admin\ListScrapedSuppliersRequest;
use App\Http\Requests\Admin\ListSupplierScrapeJobsRequest;
use App\Http\Requests\Admin\StartSupplierScrapeRequest;
use App\Http\Resources\Admin\ScrapedSupplierResource;
use App\Http\Resources\Admin\SupplierScrapeJobResource;
use App\Http\Resources\SupplierResource;
use App\Models\ScrapedSupplier;
use App\Models\SupplierScrapeJob;
use App\Services\Ai\SupplierScrapeService;
use App\Services\Suppliers\ScrapedSupplierReviewService;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SupplierScrapeController extends ApiController
{
    public function __construct(
        private readonly SupplierScrapeService $scrapeService,
        private readonly ScrapedSupplierReviewService $reviewService,
    ) {
    }

    public function index(ListSupplierScrapeJobsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierScrapeJob::class);

        $perPage = $this->perPage($request, 50, 200);
        $companyId = $request->integer('company_id');

        /** @var CursorPaginator $jobs */
        $jobs = CompanyContext::forCompany($companyId, function () use ($request, $perPage): CursorPaginator {
            return SupplierScrapeJob::query()
                ->when($request->filled('status'), function (Builder $query) use ($request): void {
                    $query->where('status', $request->string('status')->toString());
                })
                ->when($request->filled('query'), function (Builder $query) use ($request): void {
                    $term = $request->string('query')->toString();
                    $query->where('query', 'like', '%' . $term . '%');
                })
                ->when($request->filled('region'), function (Builder $query) use ($request): void {
                    $region = $request->string('region')->toString();
                    $query->where('region', 'like', '%' . $region . '%');
                })
                ->when($request->filled('created_from'), function (Builder $query) use ($request): void {
                    $query->where('created_at', '>=', Carbon::parse($request->input('created_from'))->startOfDay());
                })
                ->when($request->filled('created_to'), function (Builder $query) use ($request): void {
                    $query->where('created_at', '<=', Carbon::parse($request->input('created_to'))->endOfDay());
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->cursorPaginate($perPage)
                ->withQueryString();
        });

        $paginated = $this->paginate($jobs, $request, SupplierScrapeJobResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Supplier scrape jobs retrieved.', $paginated['meta']);
    }

    public function start(StartSupplierScrapeRequest $request): JsonResponse
    {
        $this->authorize('create', SupplierScrapeJob::class);

        $companyId = $request->integer('company_id');
        $query = $request->string('query')->toString();
        $region = $request->filled('region') ? $request->string('region')->toString() : null;
        $maxResults = $request->integer('max_results');

        /** @var SupplierScrapeJob $job */
        $job = CompanyContext::forCompany($companyId, function () use ($query, $region, $maxResults): SupplierScrapeJob {
            return $this->scrapeService->startScrape($query, $region, $maxResults);
        });

        return $this->ok([
            'job' => new SupplierScrapeJobResource($job),
        ], 'Supplier scrape job started.');
    }

    public function results(ListScrapedSuppliersRequest $request, SupplierScrapeJob $supplierScrapeJob): JsonResponse
    {
        $this->authorize('view', $supplierScrapeJob);

        $perPage = $this->perPage($request, 25, 100);

        /** @var CursorPaginator $suppliers */
        $suppliers = CompanyContext::forCompany($supplierScrapeJob->company_id, function () use ($request, $supplierScrapeJob, $perPage): CursorPaginator {
            return $supplierScrapeJob->scrapedSuppliers()
                ->when($request->filled('search'), function (Builder $query) use ($request): void {
                    $term = $request->string('search')->toString();
                    $query->where(static function (Builder $builder) use ($term): void {
                        $builder->where('name', 'like', '%' . $term . '%')
                            ->orWhere('website', 'like', '%' . $term . '%')
                            ->orWhere('description', 'like', '%' . $term . '%');
                    });
                })
                ->when($request->filled('status'), function (Builder $query) use ($request): void {
                    $query->where('status', $request->string('status')->toString());
                })
                ->when($request->filled('min_confidence'), function (Builder $query) use ($request): void {
                    $query->where('confidence', '>=', $request->float('min_confidence'));
                })
                ->when($request->filled('max_confidence'), function (Builder $query) use ($request): void {
                    $query->where('confidence', '<=', $request->float('max_confidence'));
                })
                ->orderByDesc('confidence')
                ->orderByDesc('id')
                ->cursorPaginate($perPage)
                ->withQueryString();
        });

        $paginated = $this->paginate($suppliers, $request, ScrapedSupplierResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Scraped supplier records retrieved.', $paginated['meta']);
    }

    public function approve(ApproveScrapedSupplierRequest $request, ScrapedSupplier $scrapedSupplier): JsonResponse
    {
        $this->authorize('approve', $scrapedSupplier);

        $payload = $request->validated();
        $user = $request->user();

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $attachment = $request->file('attachment');
        $supplier = $this->reviewService->approve($scrapedSupplier, $payload, $user, $attachment);
        $scrapedSupplier->refresh();

        return $this->ok([
            'scraped_supplier' => new ScrapedSupplierResource($scrapedSupplier),
            'supplier' => new SupplierResource($supplier),
        ], 'Scraped supplier approved.');
    }

    public function discard(DiscardScrapedSupplierRequest $request, ScrapedSupplier $scrapedSupplier): JsonResponse
    {
        $this->authorize('discard', $scrapedSupplier);

        $user = $request->user();

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $notes = $request->string('notes')->toString();
        $scrapedSupplier = $this->reviewService->discard($scrapedSupplier, $notes !== '' ? $notes : null, $user);

        return $this->ok([
            'scraped_supplier' => new ScrapedSupplierResource($scrapedSupplier),
        ], 'Scraped supplier discarded.');
    }
}
