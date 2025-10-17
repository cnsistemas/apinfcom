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


    public static function gerarXmlNFCom($dados, $cnpjEmit, $ambiente)
    {
        // Geração correta do cUF, cMunFG, dhEmi etc.
        $cUF = self::getCodigoUF($dados['emitente']['uf']);
        $cMunFG = $dados['emitente']['codMun'];
        $tpAmb = ($ambiente == "homologacao") ? 2 : 1;
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
        $prepago = $dados['pre-pago'];

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
        $xml .= "<tpFat>$tpFat</tpFat><verProc>$verProc</verProc>";
        if($prepago != 0){
            $xml .= "<indPrePago>1</indPrePago	>";
        }
        $xml .= "</ide>";

        // emitente
        $xml .= '<emit><CNPJ>' . $dados['emitente']['cnpj'] . '</CNPJ>';
        $xml .= '<IE>' . (isset($dados['emitente']['ie']) ? htmlspecialchars($dados['emitente']['ie']) : 'ISENTO') . '</IE>';
        $xml .= '<CRT>'.$dados['emitente']['CRT'].'</CRT><xNome>' . htmlspecialchars($dados['emitente']['nome']) . '</xNome>';
        $xml .= '<enderEmit>';
        $xml .= '<xLgr>' . htmlspecialchars($dados['emitente']['endereco']) . '</xLgr>';
        $xml .= '<nro>' . htmlspecialchars($dados['emitente']['numero']) . '</nro>';
        $xml .= '<xBairro>' . htmlspecialchars($dados['emitente']['bairro']) . '</xBairro>';
        $xml .= '<cMun>' . $cMunFG . '</cMun>';
        $xml .= '<xMun>' . htmlspecialchars($dados['emitente']['cidade']) . '</xMun>';
        $xml .= '<CEP>' . preg_replace('/\D/', '', $dados['emitente']['cep']) . '</CEP>';
        $xml .= '<UF>' . htmlspecialchars($dados['emitente']['uf']) . '</UF>';
        $xml .= '<fone>' . htmlspecialchars($dados['emitente']['telefone']) . '</fone>';
        $xml .= '</enderEmit></emit>';

        // destinatário
        $xml .= '<dest><xNome>' . htmlspecialchars($dados['destinatario']['nome']) . '</xNome>';
        $cpfcnpj = preg_replace('/\D/', '', $dados['destinatario']['cpfcnpj']);
        $xml .= strlen($cpfcnpj) == 11 ? '<CPF>' . $cpfcnpj . '</CPF>' : '<CNPJ>' . $cpfcnpj . '</CNPJ>';
        $xml .= '<indIEDest>'.$dados['destinatario']['indIEDest'].'</indIEDest><IE>' . (isset($dados['destinatario']['ie']) ? htmlspecialchars($dados['destinatario']['rgie']) : 'ISENTO') . '</IE>';
        $xml .= '<enderDest>';
        $xml .= '<xLgr>' . htmlspecialchars($dados['destinatario']['endereco']) . '</xLgr>';
        $xml .= '<nro>' . htmlspecialchars($dados['destinatario']['numero']) . '</nro>';
        $xml .= '<xBairro>' . htmlspecialchars($dados['destinatario']['bairro']) . '</xBairro>';
        $xml .= '<cMun>' . $dados['destinatario']['codMun'] . '</cMun>';
        $xml .= '<xMun>' . htmlspecialchars($dados['destinatario']['cidade']) . '</xMun>';
        $xml .= '<CEP>' . preg_replace('/\D/', '', $dados['destinatario']['cep']) . '</CEP>';
        $xml .= '<UF>' . htmlspecialchars($dados['destinatario']['uf']) . '</UF>';
        $xml .= '<fone>' . htmlspecialchars($dados['destinatario']['telefone']) . '</fone>';
        $xml .= '</enderDest></dest>';

        $xml .= '<assinante><iCodAssinante>'. htmlspecialchars($dados['assinante']['CodAssinante']) .'</iCodAssinante><tpAssinante>'. htmlspecialchars($dados['assinante']['tpAssinante']) .'</tpAssinante><tpServUtil>'. htmlspecialchars($dados['assinante']['tpServUtil']) .'</tpServUtil><nContrato>'. htmlspecialchars($dados['assinante']['Contrato']) .'</nContrato><dContratoIni>'. htmlspecialchars($dados['assinante']['DtInicio']) .'</dContratoIni>';
        // if($dados['assinante']['tpServUtil'] == 1){
        //     $xml .= '<NroTermPrinc>'. htmlspecialchars($dados['assinante']['CodAssinante']) .'</NroTermPrinc><cUFPrinc>'. htmlspecialchars($dados['assinante']['cUFPrinc']) .'</cUFPrinc>';
        // }
        $xml .= '</assinante>';

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
            $xml .= '</prod>';
            $xml .= '<imposto><ICMS00><CST>' . $item['cst'] . '</CST><vBC>' . number_format($item['bc_icms'], 2, '.', '') . '</vBC>';
            $xml .= '<pICMS>' . number_format($item['aliq_icms'], 2, '.', '') . '</pICMS><vICMS>' . number_format($item['val_icms'], 2, '.', '') . '</vICMS>';
            $xml .= '</ICMS00></imposto>';
            $xml .= '</det>';
        }

        $xml .= '<total><vProd>' . number_format($dados['impostos']['total_prod'], 2, '.', '') . '</vProd><ICMSTot>';
        $xml .= '<vBC>' . number_format($dados['impostos']['bc_icms_total'], 2, '.', '') . '</vBC><vICMS>' . number_format($dados['impostos']['val_icms_total'], 2, '.', '') . '</vICMS><vICMSDeson>' . number_format($dados['impostos']['icms_des'], 2, '.', '') . '</vICMSDeson><vFCP>' . number_format($dados['impostos']['fcp'], 2, '.', '') . '</vFCP></ICMSTot>';
        $xml .= '<vCOFINS>' . number_format($dados['impostos']['cofins'], 2, '.', '') . '</vCOFINS><vPIS>' . number_format($dados['impostos']['pis'], 2, '.', '') . '</vPIS><vFUNTTEL>' . number_format($dados['impostos']['funttel'], 2, '.', '') . '</vFUNTTEL><vFUST>' . number_format($dados['impostos']['fust'], 2, '.', '') . '</vFUST>';
        $xml .= '<vRetTribTot><vRetPIS>' . number_format($dados['impostos']['retPis'], 2, '.', '') . '</vRetPIS><vRetCofins>' . number_format($dados['impostos']['retCofins'], 2, '.', '') . '</vRetCofins><vRetCSLL>' . number_format($dados['impostos']['relCSLL'], 2, '.', '') . '</vRetCSLL><vIRRF>' . number_format($dados['impostos']['retIRRF'], 2, '.', '') . '</vIRRF></vRetTribTot>';
        $xml .= '<vDesc>' . number_format($dados['impostos']['desconto'], 2, '.', '') . '</vDesc><vOutro>' . number_format($dados['impostos']['outros'], 2, '.', '') . '</vOutro><vNF>' . number_format($dados['impostos']['total_nf'], 2, '.', '') . '</vNF></total>';

        $xml .= '<gFat><CompetFat>' . htmlspecialchars($dados['fatura']['CompetFat']) . '</CompetFat><dVencFat>' . htmlspecialchars($dados['fatura']['dVencFat']) . '</dVencFat>';
        $xml .= '<codBarras>' .$dados['fatura']['codBarras'] . '</codBarras>';

        if(isset($dados['fatura']['codDebAuto']) && $dados['fatura']['codDebAuto'] != 0){
            $xml .= '<codDebAuto>'.$dados['fatura']['codDebAuto'].'</codDebAuto>';
        }
        $xml .= '</gFat>';

        $xml .= '<infAdic><infCpl>' . htmlspecialchars($dados['mensagem']) . '</infCpl></infAdic>';
        $xml .= '<gRespTec><CNPJ>41151201000177</CNPJ><xContato>Suporte</xContato><email>benito@thinkpro.com.br</email><fone>12997877084</fone></gRespTec>';

        $xml .= '</infNFCom>'; // FECHA infNFCom — assinatura será fora

        $chaveNumerica = substr($chaveAcesso, 5); // remove "NFCom"


        // infNFComSupl será depois da assinatura
        $xml .= '<infNFComSupl><qrCodNFCom>https://dfe-portal.svrs.rs.gov.br/NFCom/QRCode?chNFCom=' . $chaveNumerica . '&amp;tpAmb=' . $tpAmb . '</qrCodNFCom></infNFComSupl>';

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