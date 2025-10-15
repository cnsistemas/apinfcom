<?php

namespace NFCom;

use Exception;
use NFCom\Common\Validator;
use NFCom\Exception\ValidationException;
use NFCom\NFComXmlBuilder;

class NFComEmissao
{
    private NFComTools $tools;

    public function __construct(string $cnpj, string $senha, string $ambiente = 'homologacao')
    {
        $this->tools = new NFComTools($cnpj, $senha, $ambiente);
    }

    public function emitir(array $dados): array
    {
        try {
            // 1. CNPJ do emitente vindo do JSON
            $cnpjEmit = preg_replace('/\D/', '', $dados['cnpj_emitente']);

            // 2. Gera o XML da NFCom
            $xml = NFComXmlBuilder::gerarXmlNFCom($dados, $cnpjEmit);

            // 3. Assina o XML usando o Signer (via NFComTools)
            $xmlAssinado = $this->tools->assinarXML($xml, 'infNFCom');

            // 4. Valida o XML assinado contra o XSD oficial
            //$xsdPath = __DIR__ . '/../../docs/PL_NFCOM_1.00/nfcom_v1.00.xsd';
            //NFComXmlBuilder::validarXmlContraXsd($xmlAssinado, $xsdPath);

            // 5. Compacta o XML assinado
            $xmlCompactado = $this->tools->compactarXML($xmlAssinado);

            // 6. Envia para o webservice
            $resposta = $this->tools->enviarSOAP($xmlCompactado, 'NFComRecepcao');

            // 7. Tenta extrair a resposta da SEFAZ (retorno real ou erro HTML)
            $retorno = [
                'status' => 'sucesso',
                'mensagem' => 'NFCom enviada com sucesso',
                'resposta' => $resposta,
                'xml_assinado' => $xmlAssinado
            ];

            if (stripos($resposta, '<retNFCom') !== false) {
                try {
                    $xmlResp = simplexml_load_string($resposta);
                    $xmlResp->registerXPathNamespace('ns', 'http://www.portalfiscal.inf.br/NFCom');
                    $infProt = $xmlResp->xpath('//ns:protNFCom/ns:infProt');

                    if (!empty($infProt[0])) {
                        $prot = $infProt[0];
                        $retorno['resposta_parseada'] = [
                            'chNFCom' => (string) $prot->chNFCom,
                            'cStat'   => (string) $prot->cStat,
                            'xMotivo' => (string) $prot->xMotivo,
                            'nProt'   => (string) $prot->nProt ?? null
                        ];
                    }
                } catch (\Throwable $e) {
                    $retorno['parse_error'] = $e->getMessage();
                }
            }

            return $retorno;

        } catch (\Exception $e) {
            return [
                'status' => 'erro',
                'mensagem' => $e->getMessage()
            ];
        }
    }

}
