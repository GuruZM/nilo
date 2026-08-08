<?php

namespace App\Services\Dpo\Support;

use RuntimeException;
use SimpleXMLElement;

class XmlCodec
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function encode(array $payload, string $rootElement = 'API3G'): string
    {
        $xml = new SimpleXMLElement("<{$rootElement}/>");

        self::arrayToXml($payload, $xml);

        return (string) $xml->asXML();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function arrayToXml(array $data, SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                self::arrayToXml($value, $child);

                continue;
            }

            $xml->addChild($key, htmlspecialchars((string) $value, ENT_XML1));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $xml): array
    {
        $element = @simplexml_load_string($xml);

        if ($element === false) {
            throw new RuntimeException('Invalid XML response received from DPO.');
        }

        $json = json_encode($element);

        if ($json === false) {
            throw new RuntimeException('Unable to decode XML response received from DPO.');
        }

        return json_decode($json, true) ?? [];
    }
}
