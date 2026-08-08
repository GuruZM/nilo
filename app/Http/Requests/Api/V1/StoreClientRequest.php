<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Middleware\ResolveApiCompany;
use App\Models\Client;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160', $this->uniqueNameInCompany()],
            'contact_person' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'tpin' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Client names are unique per company, case-insensitively.
     *
     * Spelled out rather than left to `Rule::unique`, which compares with a
     * plain `=` — case-sensitive on SQLite, case-insensitive on MySQL. The rule
     * would then mean one thing in the test suite and another in production.
     * This is the same LOWER() comparison ClientController makes by hand, so
     * the two front doors agree on what counts as a duplicate.
     */
    private function uniqueNameInCompany(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $duplicate = Client::query()
                ->where('company_id', $this->companyId())
                ->whereRaw('LOWER(name) = LOWER(?)', [(string) $value])
                ->when($this->route('client'), fn ($query, Client $client) => $query->whereKeyNot($client->getKey()))
                ->exists();

            if ($duplicate) {
                $fail('A client with this name already exists in this company.');
            }
        };
    }

    private function companyId(): int
    {
        return (int) $this->attributes->get(ResolveApiCompany::ATTRIBUTE);
    }
}
