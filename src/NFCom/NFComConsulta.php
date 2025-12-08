<?php

namespace NFCom;

use NFCom\Common\Validator;
use NFCom\Common\Standardize;
use NFCom\Exception\ValidationException;

class NFComConsulta
{
    public function consultar(string $chave, string $cnpj, string $senha, string $ambiente = 'homologacao')
    {
        // 1. Montar XML de consulta
        $tpAmb = $ambiente === 'producao' ? '1' : '2';
        $xmlConsulta =  "<consSitNFCom xmlns=\"http://www.portalfiscal.inf.br/nfcom\" versao=\"1.00\">"
                . "<tpAmb>{$tpAmb}</tpAmb>"
                . "<xServ>CONSULTAR</xServ>"
                . "<chNFCom>{$chave}</chNFCom>"
            . "</consSitNFCom>";


        // 3. Assinar, compactar e enviar
        $tools = new NFComTools($cnpj, $senha, $ambiente);
        $xmlAssinado = $tools->assinarXML($xmlConsulta, 'consSitNFCom');

        // Validação após assinatura, igual à emissão
        //$xsdPath = __DIR__ . '/../../docs/PL_NFCOM_1.00/consSitNFCom_v1.00.xsd';
        //NFComXmlBuilder::validarXmlContraXsd($xmlAssinado, $xsdPath);

        // echo "<pre>";
        //     echo htmlspecialchars($xmlAssinado);
        //     echo "</pre>";
        //     die();

        $xmlCompactado = $tools->compactarXML($xmlAssinado);
        $resposta = $tools->enviarSOAP($xmlConsulta, 'NFComConsulta');

        $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $resposta);

        // Agora carrega o XML limpo
        $xml = simplexml_load_string($cleanXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_decode(json_encode($xml, JSON_UNESCAPED_UNICODE), true);
    }
}
