<?php

namespace NFCom;

use NFCom\Common\Validator;
use NFCom\Common\Standardize;
use NFCom\Exception\ValidationException;

class NFComCancelamento
{
    public function cancelar(array $dados, $senha, $ambiente): array
    {
        // 1. Montar XML do evento de cancelamento
        $tpAmb = $ambiente === 'producao' ? '1' : '2';
        $idEvento = 'ID110111' . $dados['chave'] . '001';
        $dataHora = date('Y-m-d\TH:i:sP');
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>"
                . "<eventoNFCom xmlns=\"http://www.portalfiscal.inf.br/nfcom\" versao=\"1.00\">"
                        . "<infEvento Id=\"{$idEvento}\">"
                            . "<cOrgao>{$dados['cOrgao']}</cOrgao>"
                            . "<tpAmb>{$tpAmb}</tpAmb>"
                            . "<CNPJ>{$dados['cnpj']}</CNPJ>"
                            . "<chNFCom>{$dados['chave']}</chNFCom>"
                            . "<dhEvento>{$dataHora}</dhEvento>"
                            . "<tpEvento>110111</tpEvento>"
                            . "<nSeqEvento>1</nSeqEvento>"
                                . "<detEvento versaoEvento=\"1.00\">"
                                    . "<evCancNFCom>" // Tag corrigida
                                        . "<descEvento>Cancelamento</descEvento>"
                                        . "<nProt>{$dados['protocolo']}</nProt>"
                                        . "<xJust>{$dados['justificativa']}</xJust>"
                                    . "</evCancNFCom>"
                                . "</detEvento>"
                        . "</infEvento>"
                . "</eventoNFCom>";


        // 3. Assinar, compactar e enviar
        $tools = new NFComTools($dados['cnpj'], $senha, $ambiente);
        $xmlAssinado = $tools->assinarXML($xml, 'infEvento');

        // Validação após assinatura, igual à emissão
        // $xsdPath = $_ENV['BASE_PATH'] . 'docs/PL_NFCOM_1.00/evCancNFCom_v1.00.xsd';
        // NFComXmlBuilder::validarXmlContraXsd($xmlAssinado, $xsdPath);

        // echo "<pre>";
        //     echo htmlspecialchars($xmlAssinado);
        //     echo "</pre>";
        //     die();

        // Compacta e envia
        //$xmlCompactado = $tools->compactarXML($xmlAssinado);
        $resposta = $tools->enviarSOAP($xmlAssinado, 'NFComRecepcaoEvento', $dados['cOrgao']);

        $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $resposta);

        // Agora carrega o XML limpo
        $xml = simplexml_load_string($cleanXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_decode(json_encode($xml, JSON_UNESCAPED_UNICODE), true);
    }
}
