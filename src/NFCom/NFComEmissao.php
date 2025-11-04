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

    public function emitir(array $dados, $ambiente)
    {
        try {
            // 1. CNPJ do emitente vindo do JSON
            $cnpjEmit = preg_replace('/\D/', '', $dados['emitente']['cnpj']);

            // 2. Gera o XML da NFCom
            $xml = NFComXmlBuilder::gerarXmlNFCom($dados, $cnpjEmit, $ambiente);
            // 1. Define o cabeçalho para texto simples
            // header('Content-Type: text/plain'); 

            // 2. Exibe o XML dentro de tags <pre> para manter a formatação
            echo "<pre>";
            echo htmlspecialchars($xml);
            echo "</pre>";
            die();

            // 3. Assina o XML usando o Signer (via NFComTools)
            $xmlAssinado = $this->tools->assinarXML(trim($xml), 'infNFCom');

            // // 2. Exibe o XML dentro de tags <pre> para manter a formatação
            // echo "<pre>";
            // echo htmlspecialchars($xml);
            // echo "</pre>";
            // die();

            // 4. Valida o XML assinado contra o XSD oficial
            //$xsdPath = __DIR__ . '/../../docs/PL_NFCOM_1.00/nfcom_v1.00.xsd';
            //NFComXmlBuilder::validarXmlContraXsd($xmlAssinado, $xsdPath);

            // 5. Compacta o XML assinado
            $xmlCompactado = $this->tools->compactarXML($xmlAssinado);

            // 6. Envia para o webservice
            $resposta = $this->tools->enviarSOAP($xmlCompactado, 'NFComRecepcao', $dados['emitente']['uf']);
            $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $resposta);

            // Agora carrega o XML limpo
            $xml = simplexml_load_string($cleanXml, 'SimpleXMLElement', LIBXML_NOCDATA);
            return json_decode(json_encode($xml, JSON_UNESCAPED_UNICODE), true);


        } catch (\Exception $e) {
            return [
                'status' => 'erro',
                'mensagem' => $e->getMessage()
            ];
        }
    }

}
