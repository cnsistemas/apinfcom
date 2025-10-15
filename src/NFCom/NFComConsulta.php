<?php

namespace NFCom;

use NFCom\Common\Validator;
use NFCom\Common\Standardize;
use NFCom\Exception\ValidationException;

class NFComConsulta
{
    public function consultar(string $chave, string $cnpj, string $senha, string $ambiente = 'homologacao'): array
    {
        // 1. Montar XML de consulta
        $tpAmb = $ambiente === 'producao' ? '1' : '2';
        $xmlConsulta = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>"
            . "<consSitNFCom xmlns=\"http://www.portalfiscal.inf.br/NFCom\" versao=\"1.00\">"
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

        $xmlCompactado = $tools->compactarXML($xmlAssinado);
        $resposta = $tools->enviarSOAP($xmlCompactado, 'NFComConsulta');

        // 4. Padronizar resposta
        $respostaPadrao = Standardize::toStd($resposta);

        return [
            'status' => 'sucesso',
            'mensagem' => 'Consulta realizada com sucesso',
            'resposta' => $respostaPadrao,
            'xml_assinado' => $xmlAssinado
        ];
    }
}
