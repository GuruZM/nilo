<?php

namespace App\Services;

use NumberFormatter;
use ResourceBundle;

/**
 * The ISO 4217 currency catalog, built from the `intl` extension's ICU data.
 *
 * ICU ships names, symbols and minor units for every currency it knows, which
 * saves both a dependency and a network call. It also keeps retired codes
 * (ZMK, DEM, HRK) around forever, so the current set is pinned explicitly below.
 */
class CurrencyCatalog
{
    /**
     * Currency codes in current circulation.
     *
     * ICU's `CurrencyMap` supplemental data would derive this, but it is not
     * reachable from this PHP build, so the list is maintained by hand. Every
     * code here is asserted to exist in ICU by the catalog's test.
     *
     * @var list<string>
     */
    public const ISO_4217 = [
        'AED', 'AFN', 'ALL', 'AMD', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN',
        'BAM', 'BBD', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL',
        'BSD', 'BTN', 'BWP', 'BYN', 'BZD',
        'CAD', 'CDF', 'CHF', 'CLP', 'CNY', 'COP', 'CRC', 'CUP', 'CVE', 'CZK',
        'DJF', 'DKK', 'DOP', 'DZD',
        'EGP', 'ERN', 'ETB', 'EUR',
        'FJD', 'FKP',
        'GBP', 'GEL', 'GHS', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD',
        'HKD', 'HNL', 'HTG', 'HUF',
        'IDR', 'ILS', 'INR', 'IQD', 'IRR', 'ISK',
        'JMD', 'JOD', 'JPY',
        'KES', 'KGS', 'KHR', 'KMF', 'KPW', 'KRW', 'KWD', 'KYD', 'KZT',
        'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LYD',
        'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRU', 'MUR', 'MVR',
        'MWK', 'MXN', 'MYR', 'MZN',
        'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD',
        'OMR',
        'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG',
        'QAR',
        'RON', 'RSD', 'RUB', 'RWF',
        'SAR', 'SBD', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP', 'SLE', 'SOS', 'SRD',
        'SSP', 'STN', 'SVC', 'SYP', 'SZL',
        'THB', 'TJS', 'TMT', 'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS',
        'UAH', 'UGX', 'USD', 'UYU', 'UZS',
        'VES', 'VND', 'VUV',
        'WST',
        'XAF', 'XCD', 'XCG', 'XOF', 'XPF',
        'YER',
        'ZAR', 'ZMW', 'ZWG',
    ];

    /**
     * Every current currency, with its display name, symbol and minor units.
     *
     * @return list<array{code: string, name: string, symbol: string|null, precision: int}>
     */
    public function all(): array
    {
        $icu = $this->icuCurrencies();

        $rows = [];

        foreach (self::ISO_4217 as $code) {
            $entry = $icu[$code] ?? null;

            if ($entry === null) {
                continue;
            }

            $rows[] = [
                'code' => $code,
                'name' => $entry['name'],
                'symbol' => $entry['symbol'],
                'precision' => $this->precisionFor($code),
            ];
        }

        return $rows;
    }

    /**
     * Names and symbols keyed by code, as ICU reports them.
     *
     * @return array<string, array{name: string, symbol: string|null}>
     */
    protected function icuCurrencies(): array
    {
        $bundle = ResourceBundle::create('en', 'ICUDATA-curr');

        if ($bundle === null) {
            return [];
        }

        $currencies = $bundle['Currencies'];

        if ($currencies === null) {
            return [];
        }

        $rows = [];

        foreach ($currencies as $code => $entry) {
            $symbol = (string) $entry[0];

            $rows[$code] = [
                'name' => (string) $entry[1],
                /**
                 * ICU falls back to the code itself when a currency has no
                 * distinct symbol. Storing "ZMW" as ZMW's symbol would print
                 * the code twice, so treat that as having none.
                 */
                'symbol' => $symbol === $code ? null : $symbol,
            ];
        }

        return $rows;
    }

    /**
     * Minor units for a currency — 0 for JPY, 3 for KWD, 2 for most others.
     */
    protected function precisionFor(string $code): int
    {
        $formatter = new NumberFormatter('en', NumberFormatter::CURRENCY);
        $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $code);

        return $formatter->getAttribute(NumberFormatter::FRACTION_DIGITS);
    }
}
