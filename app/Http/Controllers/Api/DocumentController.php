<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends ApiController
{
    public function store(Request $request): JsonResponse
    {
        // TODO: clarify with spec - implement document upload workflow per FR-8.
        return $this->fail('Document uploads are not yet available.', 501);
    }
}
