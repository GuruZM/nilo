<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ClientController extends Controller
{
    /**
     * Display a listing of the clients (scoped to active company).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // ✅ Must have an active company
        $companyId = (int) ($user->current_company_id ?? 0);
        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No active company selected. Please select a company first.',
            ]);
        }

        // ✅ UI does client-side search; return a clean list (sorted)
        $clients = Client::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'contact_person',
                'email',
                'phone',
                'tpin',
                'address',
                'city',
                'country',
                'notes',
                'created_at',
            ]);

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
        ]);
    }

    /**
     * Store a newly created client (used by modal on the index page).
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $companyId = (int) ($user->current_company_id ?? 0);
        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No active company selected. Please select a company first.',
            ]);
        }

        try {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:160'],
                'contact_person' => ['nullable', 'string', 'max:160'],
                'email' => ['nullable', 'email', 'max:160'],
                'phone' => ['nullable', 'string', 'max:60'],
                'tpin' => ['nullable', 'string', 'max:40'],
                'address' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:120'],
                'country' => ['nullable', 'string', 'max:120'],
                'notes' => ['nullable', 'string'],
            ]);

            // Optional: prevent duplicate client names per company (case-insensitive)
            $exists = Client::where('company_id', $companyId)
                ->whereRaw('LOWER(name) = LOWER(?)', [$data['name']])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'name' => 'A client with this name already exists in this company.',
                ]);
            }

            Client::create([
                'company_id' => $companyId,
                ...$data,
            ]);

            return back()->with('success', 'Client created.');
        } catch (ValidationException $e) {
            throw $e; // 422 -> Inertia onError
        } catch (\Throwable $e) {
            Log::error('Client create failed', [
                'user_id' => $user?->id,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'name' => 'Failed to create client. Please try again.',
            ]);
        }
    }

    /**
     * Update an existing client (used by modal on the index page).
     */
    public function update(Request $request, int $client)
    {
        $user = $request->user();

        $companyId = (int) ($user->current_company_id ?? 0);
        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No active company selected. Please select a company first.',
            ]);
        }

        $clientModel = Client::where('company_id', $companyId)->where('id', $client)->first();
        if (! $clientModel) {
            throw ValidationException::withMessages([
                'client' => 'Client not found for the active company.',
            ]);
        }

        try {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:160'],
                'contact_person' => ['nullable', 'string', 'max:160'],
                'email' => ['nullable', 'email', 'max:160'],
                'phone' => ['nullable', 'string', 'max:60'],
                'tpin' => ['nullable', 'string', 'max:40'],
                'address' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:120'],
                'country' => ['nullable', 'string', 'max:120'],
                'notes' => ['nullable', 'string'],
            ]);

            $exists = Client::where('company_id', $companyId)
                ->whereRaw('LOWER(name) = LOWER(?)', [$data['name']])
                ->where('id', '!=', $clientModel->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'name' => 'Another client with this name already exists in this company.',
                ]);
            }

            $clientModel->update($data);

            return back()->with('success', 'Client updated.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Client update failed', [
                'user_id' => $user?->id,
                'company_id' => $companyId,
                'client_id' => $client,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'name' => 'Failed to update client. Please try again.',
            ]);
        }
    }

    /**
     * Delete an existing client (used by delete button on the index page).
     */
    public function destroy(Request $request, int $client)
    {
        $user = $request->user();

        $companyId = (int) ($user->current_company_id ?? 0);
        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No active company selected. Please select a company first.',
            ]);
        }

        $clientModel = Client::where('company_id', $companyId)->where('id', $client)->first();
        if (! $clientModel) {
            throw ValidationException::withMessages([
                'client' => 'Client not found for the active company.',
            ]);
        }

        try {
            $clientModel->delete();
            return back()->with('success', 'Client deleted.');
        } catch (\Throwable $e) {
            Log::error('Client delete failed', [
                'user_id' => $user?->id,
                'company_id' => $companyId,
                'client_id' => $client,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'client' => 'Failed to delete client. Please try again.',
            ]);
        }
    }

    /**
     * (Optional) Keep this for legacy links. Your new UI uses a modal, so you can remove later.
     */
    public function create()
    {
        return Inertia::render('Clients/Create');
    }
}