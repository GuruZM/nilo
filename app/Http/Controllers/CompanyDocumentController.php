<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyDocumentController extends Controller
{
    /**
     * Compliance documents are private business records, so every route
     * re-checks membership rather than trusting the URL.
     */
    private function authorizeMembership(?User $user, Company $company): void
    {
        $belongs = $user?->companies()->whereKey($company->id)->exists() ?? false;

        abort_unless($belongs, 403, 'You are not allowed to manage documents for this company.');
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->authorizeMembership($request->user(), $company);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg', 'max:5120'],
        ], [
            'file.mimes' => 'The document must be a PDF or JPG file.',
            'file.max' => 'The document may not be larger than 5MB.',
        ]);

        $file = $request->file('file');

        $company->documents()->create([
            'name' => trim($data['name']),
            'file_path' => $file->store('company-documents', CompanyDocument::DISK),
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function download(Request $request, Company $company, CompanyDocument $document): StreamedResponse
    {
        $this->authorizeMembership($request->user(), $company);

        abort_unless(
            Storage::disk(CompanyDocument::DISK)->exists($document->file_path),
            404,
            'The document file is missing.'
        );

        return Storage::disk(CompanyDocument::DISK)
            ->download($document->file_path, $document->original_filename);
    }

    public function destroy(Request $request, Company $company, CompanyDocument $document): RedirectResponse
    {
        $this->authorizeMembership($request->user(), $company);

        Storage::disk(CompanyDocument::DISK)->delete($document->file_path);

        $document->delete();

        return back()->with('success', 'Document deleted.');
    }
}
