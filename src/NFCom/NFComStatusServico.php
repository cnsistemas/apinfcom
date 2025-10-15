<?php

namespace NFCom;

use Exception;
use NFCom\Common\Validator;
use NFCom\Common\Standardize;
use NFCom\Exception\ValidationException;

class NFComStatusServico
{
    private NFComTools $tools;

    public function __construct(string $cnpj, string $senha, string $ambiente = 'homologacao')
    {
        $this->tools = new NFComTools($cnpj, $senha, $ambiente);
    }

    public function consultar(): array
    {
        $tpAmb = $this->tools->environment === 'producao' ? '1' : '2';

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>"
            . "<consStatServNFCom xmlns=\"http://www.portalfiscal.inf.br/NFCom\" versao=\"1.00\">"
            . "<tpAmb>{$tpAmb}</tpAmb>"
            . "<xServ>STATUS</xServ>"
            . "</consStatServNFCom>";

        $compactado = base64_encode($xml);
        $resposta = $this->tools->enviarSOAP($compactado, 'NFComStatusServico');

        $retorno = [
            'xml' => $xml,
            'resposta_bruta' => $resposta,
            'status' => 'falha',
            'cStat' => null,
            'xMotivo' => null
        ];

        if (stripos($resposta, '<retConsStatServNFCom') !== false) {
            try {
                $xmlResp = simplexml_load_string($resposta);
                $xmlResp->registerXPathNamespace('ns', 'http://www.portalfiscal.inf.br/NFCom');
                $stat = $xmlResp->xpath('//ns:retConsStatServNFCom');

                if (!empty($stat[0])) {
                    $retorno['status'] = 'sucesso';
                    $retorno['cStat'] = (string) $stat[0]->cStat;
                    $retorno['xMotivo'] = (string) $stat[0]->xMotivo;
                }
            } catch (\Throwable $e) {
                $retorno['erro_parse'] = $e->getMessage();
            }
        }

        return $retorno;
    }

    public function consultarStatus(string $cnpj, string $senha, string $ambiente = 'homologacao'): array
    {
        // 1. Montar XML de status do serviço
        $tpAmb = $ambiente === 'producao' ? '1' : '2';
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>"
            . "<consStatServNFCom xmlns=\"http://www.portalfiscal.inf.br/NFCom\" versao=\"1.00\">"
            . "<tpAmb>{$tpAmb}</tpAmb>"
            . "<cUF>35</cUF>"
            . "<xServ>STATUS</xServ>"
            . "</consStatServNFCom>";

        // 2. Validar XML contra o XSD
        $xsd = __DIR__ . '/../../schemas/consStatServNFCom_v1.00.xsd';
        $erros = Validator::validate($xml, $xsd);
        if (!empty($erros)) {
            throw new ValidationException('XML de status inválido: ' . implode('; ', $erros));
        }

        // 3. Assinar, compactar e enviar
        $tools = new NFComTools($cnpj, $senha, $ambiente);
        $xmlAssinado = $tools->assinarXML($xml, 'consStatServNFCom');
        $xmlCompactado = $tools->compactarXML($xmlAssinado);
        $resposta = $tools->enviarSOAP($xmlCompactado, 'NFComStatusServico');

        // 4. Padronizar resposta
        $respostaPadrao = Standardize::toStd($resposta);

        return [
            'status' => 'sucesso',
            'mensagem' => 'Consulta de status realizada com sucesso',
            'resposta' => $respostaPadrao,
            'xml_assinado' => $xmlAssinado
        ];
    }
}
