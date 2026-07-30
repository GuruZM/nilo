<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyDocument extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyDocumentFactory> */
    use HasFactory;

    /**
     * The disk compliance documents live on. Private, because these are
     * certificates and tax records — they are only ever streamed through an
     * authenticated download route, never served by URL.
     */
    public const DISK = 'local';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'file_path',
        'original_filename',
        'mime_type',
        'size',
    ];

    /**
     * The storage path never leaves the server — the file is reachable only
     * through the authenticated download route.
     *
     * @var list<string>
     */
    protected $hidden = [
        'file_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * The company this document belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
