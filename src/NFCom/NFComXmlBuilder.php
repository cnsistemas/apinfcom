<?php

namespace NFCom;

use NFePHP\Common\Keys;

class NFComXmlBuilder
{
    // Tabela simplificada de UF para código IBGE
    private static $ufIbge = [
        'AC' => '12', 'AL' => '27', 'AM' => '13', 'AP' => '16', 'BA' => '29', 'CE' => '23', 'DF' => '53',
        'ES' => '32', 'GO' => '52', 'MA' => '21', 'MG' => '31', 'MS' => '50', 'MT' => '51', 'PA' => '15',
        'PB' => '25', 'PE' => '26', 'PI' => '22', 'PR' => '41', 'RJ' => '33', 'RN' => '24', 'RO' => '11',
        'RR' => '14', 'RS' => '43', 'SC' => '42', 'SE' => '28', 'SP' => '35', 'TO' => '17'
    ];

    // Tabela simplificada de cidades (exemplo, só SP)
    private static $cidadesIbge = [
        'SÃO PAULO|SP' => '3550308',
        // Adicione mais cidades conforme necessário
    ];

    public static function getCodigoUF($uf)
    {
        $uf = strtoupper($uf);
        return self::$ufIbge[$uf] ?? '35'; // Default SP
    }

    public static function getCodigoMunicipio($cidade, $uf)
    {
        $chave = strtoupper(trim($cidade)) . '|' . strtoupper(trim($uf));
        return self::$cidadesIbge[$chave] ?? '3550308'; // Default SP
    }

    // Gera código aleatório de 8 dígitos
    public static function gerarCNF()
    {
        // Gera um número aleatório entre 0 e 99999999 e preenche com zeros à esquerda
        return str_pad(strval(mt_rand(0, 99999999)), 8, '0', STR_PAD_LEFT);
    }

    // Gera código aleatório de 8 dígitos
    public static function gerarCNF2()
    {
        // Gera um número aleatório entre 0 e 9999999 e preenche com zeros à esquerda (7 dígitos)
        return str_pad(strval(mt_rand(0, 9999999)), 7, '0', STR_PAD_LEFT);
    }

    // Gera número sequencial da NF (exemplo)
    public static function gerarNNF()
    {
        return rand(10000, 99999); // Ideal: sequencial
    }

    // Gera chave de acesso (simplificada)
    public static function gerarChaveAcesso($cUF, $dhEmi, $cnpjEmit, $mod, $serie, $nNF, $tpEmis, $cNF)
    {
        // Formato: cUF(2) + AAMM(4) + CNPJ(14) + mod(2) + serie(3) + nNF(9) + tpEmis(1) + cNF(8) + cDV(1)
        $ano = substr($dhEmi, 2, 2);
        $mes = substr($dhEmi, 5, 2);
        $AAMM = $ano . $mes;
        $serie = str_pad($serie, 3, '0', STR_PAD_LEFT);
        $nNF = str_pad($nNF, 9, '0', STR_PAD_LEFT);
        $chave = $cUF . $AAMM . $cnpjEmit . $mod . $serie . $nNF . $tpEmis . $cNF;
        $cDV = self::calcularDV($chave);
        return $chave . $cDV;
    }

    // Calcula o dígito verificador (módulo 11 base 2 a 9)
    public static function calcularDV($chave)
    {
        $peso = 2;
        $soma = 0;
        for ($i = strlen($chave) - 1; $i >= 0; $i--) {
            $soma += intval($chave[$i]) * $peso;
            $peso++;
            if ($peso > 9) $peso = 2;
        }
        $resto = $soma % 11;
        $dv = ($resto == 0 || $resto == 1) ? 0 : (11 - $resto);
        return $dv;
    }

    public static function gerarChaveAcessoNFCom($cnpjEmitente, $serie, $nNF, $cNF7, $cUF)
    {
        $AAMM = date('ym'); // Ano e mês da emissão
        $mod = '62';
        $tpEmis = '1';
        $nSiteAutoriz = '0';

        $cnpj = preg_replace('/\D/', '', $cnpjEmitente); // remove não dígitos
        $serie = str_pad($serie, 3, '0', STR_PAD_LEFT);
        $nNF = str_pad($nNF, 9, '0', STR_PAD_LEFT);
        $cNF = str_pad($cNF7, 7, '0', STR_PAD_LEFT);

        // Monta os 43 dígitos da chave
        $chave43 = $cUF . $AAMM . $cnpj . $mod . $serie . $nNF . $tpEmis . $nSiteAutoriz . $cNF;

        // Calcula o DV com módulo 11
        $dv = self::calcularDVModulo11($chave43);

        // Retorna chave completa com prefixo NFCom
        return 'NFCom' . $chave43 . $dv;
    }

    private static function calcularDVModulo11($chave)
    {
        $peso = 2;
        $soma = 0;

        for ($i = strlen($chave) - 1; $i >= 0; $i--) {
            $soma += intval($chave[$i]) * $peso;
            $peso = $peso == 9 ? 2 : $peso + 1;
        }

        $resto = $soma % 11;
        return ($resto == 0 || $resto == 1) ? 0 : (11 - $resto);
    }


    public static function gerarXmlNFCom($dados, $cnpjEmit)
    {
        // Geração correta do cUF, cMunFG, dhEmi etc.
        $cUF = self::getCodigoUF($dados['uf']);
        $cMunFG = self::getCodigoMunicipio($dados['cidade'], $dados['uf']);
        $tpAmb = 2;
        $mod = '62';
        $serie = 1;
        $nNF = self::gerarNNF();
        $tpEmis = 1;
        $nSiteAutoriz = 0;
        $finNFCom = 0;
        $tpFat = 0;
        $verProc = '1.0';
        $procEmi = 0;
        $dhEmi = date('Y-m-d\TH:i:sP');

        $cNF7 = self::gerarCNF2();

        // Gera chave de acesso com cNF8 (8 dígitos)
        $chaveAcesso = self::gerarChaveAcessoNFCom($cnpjEmit, $serie, $nNF, $cNF7, $cUF);

        // Calcula DV
        $cDV = substr($chaveAcesso, -1);

        // Aplica o cNF correto (7 dígitos) no XML
        $dados['cNF'] = $cNF7;



        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<NFCom xmlns="http://www.portalfiscal.inf.br/nfcom">';

        // infNFCom
        $xml .= '<infNFCom Id="'.$chaveAcesso.'" versao="1.00">';
        $xml .= '<ide>';
        $xml .= "<cUF>$cUF</cUF><tpAmb>$tpAmb</tpAmb><mod>$mod</mod><serie>$serie</serie><nNF>$nNF</nNF>";
        $xml .= "<cNF>{$dados['cNF']}</cNF><cDV>$cDV</cDV><dhEmi>$dhEmi</dhEmi><tpEmis>$tpEmis</tpEmis>";
        $xml .= "<nSiteAutoriz>$nSiteAutoriz</nSiteAutoriz><cMunFG>$cMunFG</cMunFG><finNFCom>$finNFCom</finNFCom>";
        $xml .= "<tpFat>$tpFat</tpFat><verProc>$verProc</verProc></ide>";

        // emitente
        $xml .= '<emit><CNPJ>' . $cnpjEmit . '</CNPJ>';
        $xml .= '<IE>' . (isset($dados['rgie']) ? htmlspecialchars($dados['rgie']) : 'ISENTO') . '</IE>';
        $xml .= '<CRT>3</CRT><xNome>' . htmlspecialchars($dados['nome']) . '</xNome>';
        $xml .= '<enderEmit>';
        $xml .= '<xLgr>' . htmlspecialchars($dados['endereco']) . '</xLgr>';
        $xml .= '<nro>' . htmlspecialchars($dados['endereco_numero']) . '</nro>';
        $xml .= '<xBairro>' . htmlspecialchars($dados['bairro']) . '</xBairro>';
        $xml .= '<cMun>' . $cMunFG . '</cMun>';
        $xml .= '<xMun>' . htmlspecialchars($dados['cidade']) . '</xMun>';
        $xml .= '<CEP>' . preg_replace('/\D/', '', $dados['cep']) . '</CEP>';
        $xml .= '<UF>' . htmlspecialchars($dados['uf']) . '</UF>';
        $xml .= '<fone>' . htmlspecialchars($dados['telefone']) . '</fone>';
        $xml .= '</enderEmit></emit>';

        // destinatário
        $xml .= '<dest><xNome>' . htmlspecialchars($dados['nome']) . '</xNome>';
        $cpfcnpj = preg_replace('/\D/', '', $dados['cpfcnpj']);
        $xml .= strlen($cpfcnpj) == 11 ? '<CPF>' . $cpfcnpj . '</CPF>' : '<CNPJ>' . $cpfcnpj . '</CNPJ>';
        $xml .= '<indIEDest>9</indIEDest><IE>' . (isset($dados['rgie']) ? htmlspecialchars($dados['rgie']) : 'ISENTO') . '</IE>';
        $xml .= '<enderDest>';
        $xml .= '<xLgr>' . htmlspecialchars($dados['endereco']) . '</xLgr>';
        $xml .= '<nro>' . htmlspecialchars($dados['endereco_numero']) . '</nro>';
        $xml .= '<xBairro>' . htmlspecialchars($dados['bairro']) . '</xBairro>';
        $xml .= '<cMun>' . $cMunFG . '</cMun>';
        $xml .= '<xMun>' . htmlspecialchars($dados['cidade']) . '</xMun>';
        $xml .= '<CEP>' . preg_replace('/\D/', '', $dados['cep']) . '</CEP>';
        $xml .= '<UF>' . htmlspecialchars($dados['uf']) . '</UF>';
        $xml .= '<fone>' . htmlspecialchars($dados['telefone']) . '</fone>';
        $xml .= '</enderDest></dest>';

        $xml .= '<assinante><iCodAssinante>1</iCodAssinante><tpAssinante>3</tpAssinante><tpServUtil>1</tpServUtil><nContrato>123</nContrato><dContratoIni>2024-01-01</dContratoIni></assinante>';

        $vProd = 0; $vBC = 0; $vICMS = 0;
        foreach ($dados['itens'] as $item) {
            $xml .= '<det nItem="' . intval($item['item']) . '"><prod>';
            $xml .= '<cProd>' . htmlspecialchars($item['item']) . '</cProd>';
            $xml .= '<xProd>' . htmlspecialchars($item['descricao']) . '</xProd>';
            $xml .= '<cClass>' . htmlspecialchars($item['cclass']) . '</cClass>';
            $xml .= '<CFOP>' . htmlspecialchars($item['cfop']) . '</CFOP><uMed>1</uMed>';
            $xml .= '<qFaturada>' . number_format($item['quantidade'], 2, '.', '') . '</qFaturada>';
            $xml .= '<vItem>' . number_format($item['unitario'], 2, '.', '') . '</vItem>';
            $xml .= '<vProd>' . number_format($item['total'], 2, '.', '') . '</vProd>';
            $xml .= '</prod><imposto><ICMS00><CST>00</CST><vBC>' . number_format($item['bc_icms'], 2, '.', '') . '</vBC>';
            $xml .= '<pICMS>18.00</pICMS><vICMS>' . number_format($item['bc_icms'] * 0.18, 2, '.', '') . '</vICMS>';
            $xml .= '</ICMS00></imposto></det>';
            $vProd += $item['total'];
            $vBC += $item['bc_icms'];
            $vICMS += $item['bc_icms'] * 0.18;
        }

        $xml .= '<total><vProd>' . number_format($vProd, 2, '.', '') . '</vProd><ICMSTot>';
        $xml .= '<vBC>' . number_format($vBC, 2, '.', '') . '</vBC><vICMS>' . number_format($vICMS, 2, '.', '') . '</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP></ICMSTot>';
        $xml .= '<vCOFINS>0.00</vCOFINS><vPIS>0.00</vPIS><vFUNTTEL>0.00</vFUNTTEL><vFUST>0.00</vFUST>';
        $xml .= '<vRetTribTot><vRetPIS>0.00</vRetPIS><vRetCofins>0.00</vRetCofins><vRetCSLL>0.00</vRetCSLL><vIRRF>0.00</vIRRF></vRetTribTot>';
        $xml .= '<vDesc>0.00</vDesc><vOutro>0.00</vOutro><vNF>0.00</vNF></total>';

        $xml .= '<gFat><CompetFat>202401</CompetFat><dVencFat>2024-01-31</dVencFat>';
        $xml .= '<codBarras>' . htmlspecialchars($dados['codigobarras']) . '</codBarras><codDebAuto>123456</codDebAuto></gFat>';

        $xml .= '<infAdic><infCpl>' . htmlspecialchars($dados['mensagem']) . '</infCpl></infAdic>';
        $xml .= '<gRespTec><CNPJ>00000000000191</CNPJ><xContato>Suporte</xContato><email>suporte@empresa.com.br</email><fone>11999999999</fone></gRespTec>';

        $xml .= '</infNFCom>'; // FECHA infNFCom — assinatura será fora

        $chaveNumerica = substr($chaveAcesso, 5); // remove "NFCom"
        $tpAmb = 2;


        // infNFComSupl será depois da assinatura
        $xml .= '<infNFComSupl><qrCodNFCom>https://nfcom.seufisco.gov.br/consulta?chNFCom=' . $chaveNumerica . '&amp;tpAmb=' . $tpAmb . '</qrCodNFCom></infNFComSupl>';

        $xml .= '</NFCom>';
        return $xml;
    }

    public static function validarXmlContraXsd($xmlString, $xsdPath)
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xmlString);

        // Valida contra o XSD
        $isValid = $dom->schemaValidate($xsdPath);

        if (!$isValid) {
            $erros = libxml_get_errors();
            $mensagens = [];
            foreach ($erros as $erro) {
                $mensagens[] = trim($erro->message) . " (linha {$erro->line})";
            }
            libxml_clear_errors();
            throw new \Exception("XML inválido segundo o XSD:\n" . implode("\n", $mensagens));
        }
        return true;
    }
} 