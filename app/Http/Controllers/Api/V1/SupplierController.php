<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesApiResults;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSupplierRequest;
use App\Http\Resources\Api\V1\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    use PaginatesApiResults, ResolvesCompany;

    public function index(Request $request): AnonymousResourceCollection
    {
        $suppliers = Supplier::query()
            ->where('company_id', $this->companyId($request))
            ->when($request->string('search')->value(), fn ($query, $search) => $query
                ->where(fn ($scoped) => $scoped
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return SupplierResource::collection($suppliers);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::query()->create([
            'company_id' => $this->companyId($request),
            ...$request->validated(),
        ]);

        return SupplierResource::make($supplier)->response()->setStatusCode(201);
    }

    public function show(Request $request, Supplier $supplier): SupplierResource
    {
        $this->guardCompany($request, $supplier);

        return SupplierResource::make($supplier);
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $this->guardCompany($request, $supplier);

        $supplier->update($request->validated());

        return SupplierResource::make($supplier);
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $this->guardCompany($request, $supplier);

        $supplier->delete();

        return response()->json(['message' => 'Supplier removed.']);
    }
}
