<?php

namespace NFCom\Common;

class Standardize
{
    /**
     * Converte XML em stdClass
     */
    public static function toStd(string $xml): \stdClass
    {
        $simple = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_decode(json_encode($simple), false);
    }

    /**
     * Converte XML em array
     */
    public static function toArray(string $xml): array
    {
        $simple = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_decode(json_encode($simple), true);
    }

    /**
     * Converte XML em JSON
     */
    public static function toJson(string $xml): string
    {
        $simple = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_encode($simple);
    }
} 