<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesApiResults;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClientRequest;
use App\Http\Resources\Api\V1\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    use PaginatesApiResults, ResolvesCompany;

    public function index(Request $request): AnonymousResourceCollection
    {
        $clients = Client::query()
            ->where('company_id', $this->companyId($request))
            ->when($request->string('search')->value(), fn ($query, $search) => $query
                ->where(fn ($scoped) => $scoped
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::query()->create([
            'company_id' => $this->companyId($request),
            ...$request->validated(),
        ]);

        return ClientResource::make($client)->response()->setStatusCode(201);
    }

    public function show(Request $request, Client $client): ClientResource
    {
        $this->guardCompany($request, $client);

        return ClientResource::make($client);
    }

    public function update(StoreClientRequest $request, Client $client): ClientResource
    {
        $this->guardCompany($request, $client);

        $client->update($request->validated());

        return ClientResource::make($client);
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        $this->guardCompany($request, $client);

        $client->delete();

        return response()->json(['message' => 'Client deleted.']);
    }
}
