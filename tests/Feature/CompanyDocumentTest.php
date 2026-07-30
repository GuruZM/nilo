<?php

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Currency::create(['code' => 'ZMW', 'name' => 'Zambian Kwacha', 'symbol' => 'K', 'precision' => 2, 'is_active' => true]);

    Storage::fake(CompanyDocument::DISK);
});

/** A user who owns and belongs to a freshly made company. */
function memberOfCompany(): array
{
    $user = User::factory()->withSubscription()->create();
    $company = Company::factory()->create(['owner_id' => $user->id]);
    $company->users()->attach($user->id, ['is_owner' => true, 'status' => 'active']);

    return [$user, $company];
}

test('a member can upload a pdf compliance document', function () {
    [$user, $company] = memberOfCompany();

    $this->actingAs($user)
        ->post("/companies/{$company->id}/documents", [
            'name' => 'Certificate of Incorporation',
            'file' => UploadedFile::fake()->create('incorporation.pdf', 120, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();

    $document = CompanyDocument::where('company_id', $company->id)->firstOrFail();

    expect($document->name)->toBe('Certificate of Incorporation')
        ->and($document->original_filename)->toBe('incorporation.pdf')
        ->and($document->mime_type)->toBe('application/pdf');

    Storage::disk(CompanyDocument::DISK)->assertExists($document->file_path);
});

test('a member can upload a jpg compliance document', function () {
    [$user, $company] = memberOfCompany();

    $this->actingAs($user)
        ->post("/companies/{$company->id}/documents", [
            'name' => 'Trading Licence',
            'file' => UploadedFile::fake()->image('licence.jpg'),
        ])
        ->assertSessionHasNoErrors();

    expect(CompanyDocument::where('company_id', $company->id)->count())->toBe(1);
});

test('uploading rejects file types other than pdf and jpg', function (string $filename, string $mime) {
    [$user, $company] = memberOfCompany();

    $this->actingAs($user)
        ->post("/companies/{$company->id}/documents", [
            'name' => 'Something',
            'file' => UploadedFile::fake()->create($filename, 40, $mime),
        ])
        ->assertSessionHasErrors('file');

    expect(CompanyDocument::count())->toBe(0);
})->with([
    'png' => ['scan.png', 'image/png'],
    'word document' => ['deed.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'plain text' => ['notes.txt', 'text/plain'],
    'executable' => ['payload.exe', 'application/octet-stream'],
]);

test('uploading rejects files larger than five megabytes', function () {
    [$user, $company] = memberOfCompany();

    $this->actingAs($user)
        ->post("/companies/{$company->id}/documents", [
            'name' => 'Huge Scan',
            'file' => UploadedFile::fake()->create('huge.pdf', 5121, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');

    expect(CompanyDocument::count())->toBe(0);
});

test('uploading requires a document name', function () {
    [$user, $company] = memberOfCompany();

    $this->actingAs($user)
        ->post("/companies/{$company->id}/documents", [
            'file' => UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf'),
        ])
        ->assertSessionHasErrors('name');

    expect(CompanyDocument::count())->toBe(0);
});

test('a non-member cannot upload a document to another company', function () {
    [, $company] = memberOfCompany();
    $outsider = User::factory()->withSubscription()->create();

    $this->actingAs($outsider)
        ->post("/companies/{$company->id}/documents", [
            'name' => 'Sneaky',
            'file' => UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf'),
        ])
        ->assertForbidden();

    expect(CompanyDocument::count())->toBe(0);
});

test('a member can download a document', function () {
    [$user, $company] = memberOfCompany();

    $document = CompanyDocument::factory()->create(['company_id' => $company->id]);
    Storage::disk(CompanyDocument::DISK)->put($document->file_path, 'pdf-bytes');

    $this->actingAs($user)
        ->get("/companies/{$company->id}/documents/{$document->id}/download")
        ->assertOk()
        ->assertDownload($document->original_filename);
});

test('a non-member cannot download a document', function () {
    [, $company] = memberOfCompany();
    $outsider = User::factory()->withSubscription()->create();

    $document = CompanyDocument::factory()->create(['company_id' => $company->id]);
    Storage::disk(CompanyDocument::DISK)->put($document->file_path, 'pdf-bytes');

    $this->actingAs($outsider)
        ->get("/companies/{$company->id}/documents/{$document->id}/download")
        ->assertForbidden();
});

test('a member can delete a document and its file', function () {
    [$user, $company] = memberOfCompany();

    $document = CompanyDocument::factory()->create(['company_id' => $company->id]);
    Storage::disk(CompanyDocument::DISK)->put($document->file_path, 'pdf-bytes');

    $this->actingAs($user)
        ->delete("/companies/{$company->id}/documents/{$document->id}")
        ->assertSessionHasNoErrors();

    expect(CompanyDocument::find($document->id))->toBeNull();

    Storage::disk(CompanyDocument::DISK)->assertMissing($document->file_path);
});

test('a non-member cannot delete a document', function () {
    [, $company] = memberOfCompany();
    $outsider = User::factory()->withSubscription()->create();

    $document = CompanyDocument::factory()->create(['company_id' => $company->id]);

    $this->actingAs($outsider)
        ->delete("/companies/{$company->id}/documents/{$document->id}")
        ->assertForbidden();

    expect(CompanyDocument::find($document->id))->not->toBeNull();
});

test('a document cannot be reached through a company it does not belong to', function () {
    [$user, $company] = memberOfCompany();

    $otherCompany = Company::factory()->create(['owner_id' => $user->id]);
    $otherCompany->users()->attach($user->id, ['is_owner' => true, 'status' => 'active']);

    $document = CompanyDocument::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($user)
        ->delete("/companies/{$company->id}/documents/{$document->id}")
        ->assertNotFound();

    expect(CompanyDocument::find($document->id))->not->toBeNull();
});

test('deleting a company deletes its documents', function () {
    [, $company] = memberOfCompany();

    CompanyDocument::factory()->count(3)->create(['company_id' => $company->id]);

    $company->delete();

    expect(CompanyDocument::where('company_id', $company->id)->count())->toBe(0);
});

test('the companies index exposes each company documents', function () {
    [$user, $company] = memberOfCompany();

    CompanyDocument::factory()->create([
        'company_id' => $company->id,
        'name' => 'Tax Clearance',
    ]);

    $this->actingAs($user)
        ->get('/companies')
        ->assertInertia(fn ($page) => $page
            ->component('Companies/Index')
            ->where('companies.0.documents.0.name', 'Tax Clearance')
            ->has('companies.0.documents', 1)
        );
});

test('the companies index never exposes a document file path', function () {
    [$user, $company] = memberOfCompany();

    CompanyDocument::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->get('/companies')
        ->assertInertia(fn ($page) => $page
            ->missing('companies.0.documents.0.file_path')
        );
});
