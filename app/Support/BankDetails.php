<?php

namespace App\Support;

/**
 * The bank account a template prints under "Bank details", entered in the
 * template builder and stored in the template's `settings.bank`.
 *
 * The fields are fixed rather than free text so every sheet can print them
 * escaped, line by line, in the same order and with the same labels.
 */
class BankDetails
{
    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'name' => 'Bank',
        'account_name' => 'Account name',
        'account_number' => 'Account number',
        'branch' => 'Branch',
        'swift_code' => 'SWIFT code',
    ];

    /**
     * Every known field as a trimmed string, dropping anything else.
     *
     * @return array{name: string, account_name: string, account_number: string, branch: string, swift_code: string}
     */
    public static function normalize(mixed $bank): array
    {
        $bank = is_array($bank) ? $bank : [];

        return array_map(
            fn (string $field): string => is_scalar($bank[$field] ?? null) ? trim((string) $bank[$field]) : '',
            array_combine(array_keys(self::LABELS), array_keys(self::LABELS)),
        );
    }

    /**
     * The filled fields, labelled, in print order.
     *
     * @return list<array{label: string, value: string}>
     */
    public static function lines(mixed $bank): array
    {
        $lines = [];

        foreach (self::normalize($bank) as $field => $value) {
            if ($value !== '') {
                $lines[] = ['label' => self::LABELS[$field], 'value' => $value];
            }
        }

        return $lines;
    }
}
