<?php

namespace App\Contracts;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * What the shared renderer needs from a document model. Keeping it this small
 * means a new document type only has to say what it is and who it is for.
 */
interface RenderableDocument
{
    public function documentType(): DocumentType;

    /**
     * The client or supplier the document is addressed to.
     */
    public function counterparty(): ?Model;

    /**
     * The line items to print, in display order.
     *
     * @return Collection<int, Model>
     */
    public function printableItems(): Collection;

    /**
     * The company template this document should print through, if one was chosen.
     */
    public function chosenTemplateId(): ?int;
}
