<?php

namespace NFCom\Common;

class Validator
{
    /**
     * Valida um XML contra um XSD
     * @param string $xml XML como string
     * @param string $xsd Caminho do arquivo XSD
     * @return array Lista de erros (vazia se válido)
     */
    public static function validate(string $xml, string $xsd): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        libxml_use_internal_errors(true);
        $ok = $dom->schemaValidate($xsd);
        $errors = [];
        if (!$ok) {
            foreach (libxml_get_errors() as $error) {
                $errors[] = trim($error->message);
            }
        }
        libxml_clear_errors();
        return $errors;
    }
} 