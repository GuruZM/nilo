<?php

namespace App\Enums;

/**
 * Every difference between the six document types Nilo issues.
 *
 * The printed sheet, the numbering and the prerequisite wording all read from
 * here, so adding a seventh type is a matter of adding a case rather than
 * hunting down conditionals across the controllers and the blade.
 */
enum DocumentType: string
{
    case Invoice = 'invoice';
    case Quotation = 'quotation';
    case Receipt = 'receipt';
    case CreditNote = 'credit_note';
    case DeliveryNote = 'delivery_note';
    case PurchaseOrder = 'purchase_order';

    /**
     * Lowercase wording for prose: "A {label} has to be addressed to someone."
     */
    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'invoice',
            self::Quotation => 'quotation',
            self::Receipt => 'receipt',
            self::CreditNote => 'credit note',
            self::DeliveryNote => 'delivery note',
            self::PurchaseOrder => 'purchase order',
        };
    }

    public function pluralLabel(): string
    {
        return $this->label().'s';
    }

    /**
     * The banner printed across the top of the sheet.
     */
    public function documentTitle(): string
    {
        return strtoupper($this->label());
    }

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Invoice => 'INV',
            self::Quotation => 'QUO',
            self::Receipt => 'RCP',
            self::CreditNote => 'CRN',
            self::DeliveryNote => 'DN',
            self::PurchaseOrder => 'PO',
        };
    }

    /**
     * The column holding this type's second date, or null when it only has an
     * issue date. Receipts are dated the day the money landed and credit notes
     * the day they were raised, so neither carries a second one.
     */
    public function secondDateField(): ?string
    {
        return match ($this) {
            self::Invoice => 'due_date',
            self::Quotation => 'valid_until',
            self::DeliveryNote => 'delivery_date',
            self::PurchaseOrder => 'expected_date',
            self::Receipt, self::CreditNote => null,
        };
    }

    public function secondDateLabel(): ?string
    {
        return match ($this) {
            self::Invoice => 'Due',
            self::Quotation => 'Valid until',
            self::DeliveryNote => 'Delivered',
            self::PurchaseOrder => 'Expected',
            self::Receipt, self::CreditNote => null,
        };
    }

    /**
     * A delivery note proves what arrived, not what it cost — showing money on
     * one leaks your margins to whoever signs for the goods.
     */
    public function showsPrices(): bool
    {
        return $this !== self::DeliveryNote;
    }

    /**
     * Whether the counterparty is a supplier rather than a client.
     */
    public function usesSupplier(): bool
    {
        return $this === self::PurchaseOrder;
    }

    public function counterpartyLabel(): string
    {
        return $this === self::DeliveryNote ? 'Deliver to:' : 'To:';
    }

    public function closingLine(): string
    {
        return match ($this) {
            self::Invoice => 'Thank you for your business. Kindly settle within due date.',
            self::Quotation => 'Thank you for the opportunity. This quotation is valid until the date shown above.',
            self::Receipt => 'Payment received with thanks.',
            self::CreditNote => 'This credit note has been applied to the invoice shown above.',
            self::DeliveryNote => 'Please check the goods on arrival and sign below to confirm receipt.',
            self::PurchaseOrder => 'Please confirm acceptance and quote this order number on your invoice.',
        };
    }
}
