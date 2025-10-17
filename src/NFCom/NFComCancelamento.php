<?php

namespace NFCom;

use NFCom\Common\Validator;
use NFCom\Common\Standardize;
use NFCom\Exception\ValidationException;

class NFComCancelamento
{
    public function cancelar(array $dados): array
    {
        // 1. Montar XML do evento de cancelamento
        $tpAmb = $dados['ambiente'] === 'producao' ? '1' : '2';
        $idEvento = 'ID110111' . $dados['chave'];
        $dataHora = date('Y-m-d\TH:i:sP');
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>"
            . "<envEventoNFCom xmlns=\"http://www.portalfiscal.inf.br/NFCom\" versao=\"1.00\">" // Corrigido para /NFCom
            . "<idLote>1</idLote>"
            . "<eventoNFCom versao=\"1.00\">"
            . "<infEvento Id=\"{$idEvento}\">"
            . "<cOrgao>{$dados['cOrgao']}</cOrgao>"
            . "<tpAmb>{$tpAmb}</tpAmb>"
            . "<CNPJ>{$dados['cnpj']}</CNPJ>"
            . "<chNFCom>{$dados['chave']}</chNFCom>"
            . "<dhEvento>{$dataHora}</dhEvento>"
            . "<tpEvento>110111</tpEvento>"
            . "<nSeqEvento>1</nSeqEvento>"
            . "<verEvento>1.00</verEvento>"
            . "<detEvento versao=\"1.00\">"
            . "<evCancNFCom>" // Tag corrigida
            . "<descEvento>Cancelamento</descEvento>"
            . "<nProt>{$dados['protocolo']}</nProt>"
            . "<xJust>{$dados['justificativa']}</xJust>"
            . "</evCancNFCom>"
            . "</detEvento>"
            . "</infEvento>"
            . "</eventoNFCom>"
            . "</envEventoNFCom>";


        // 3. Assinar, compactar e enviar
        $tools = new NFComTools($dados['cnpj'], $dados['senha'], $dados['ambiente']);
        $xmlAssinado = $tools->assinarXML($xml, 'infEvento');

        // // Validação após assinatura, igual à emissão
        // $xsdPath = __DIR__ . '/../../docs/PL_NFCOM_1.00/eventoNFCom_v1.00.xsd';
        // NFComXmlBuilder::validarXmlContraXsd($xmlAssinado, $xsdPath);

        // echo "<pre>";
        //     echo htmlspecialchars($xmlAssinado);
        //     echo "</pre>";
        //     die();

        // Compacta e envia
        $xmlCompactado = $tools->compactarXML($xmlAssinado);
        $resposta = $tools->enviarSOAP($xmlAssinado, 'NFComRecepcaoEvento');

        $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $resposta);

        // Agora carrega o XML limpo
        $xml = simplexml_load_string($cleanXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_decode(json_encode($xml, JSON_UNESCAPED_UNICODE), true);
    }
}
