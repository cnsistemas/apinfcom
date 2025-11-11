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

    public static function removerSimbolos($string) {
        // (A remoção de acentos via iconv é desnecessária para CNPJ, mas mantida por ser genérica)
        $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        
        // Remove todos os caracteres que NÃO são letras, números, espaço ou hífen.
        // Para CNPJ, isso remove '.', '/' e o '-'
        $string_limpa = preg_replace('/[^a-zA-Z0-9\s-]/', '', $string);
        
        // Remove múltiplos espaços e retorna a string
        $string_limpa = preg_replace('/\s+/', ' ', $string_limpa);
        
        return trim($string_limpa);
    }

    public static function limparNumeros($string) {
        return preg_replace('/[^0-9]/', '', $string);
    }

    /**
     * Abrevia nomes para NFCom (modelo 62) dentro do limite (padrão: 60 chars).
     */
    public static function abrevia_xnome_nfcom(string $nome, int $limite = 60): string
    {
        if (!function_exists('mb_strlen')) {
            throw new RuntimeException('Extensão mbstring é necessária.');
        }

        $nome = trim(preg_replace('/\s+/u', ' ', $nome ?? ''));
        $nome = mb_strtoupper($nome, 'UTF-8');

        $map = [
            '/\bSECRETARIA MUNICIPAL\b/u' => 'SM',
            '/\bESCOLA MUNICIPAL\b/u' => 'E.M.',
            '/\bCENTRO MUNICIPAL\b/u' => 'C.M.',
            '/\bCONSELHO ESCOLAR\b/u' => 'CONS. ESCOLAR',
            '/\bUNIVERSIDADE\b/u' => 'UNIV.',
            '/\bINSTITUTO\b/u' => 'INST.',
            '/\bFUNDAC[ÃA]O\b/u' => 'FUND.',
            '/\bASSOCIA[ÇC][AÃ]O\b/u' => 'ASSOC.',
            '/\bCOL[ÉE]GIO\b/u' => 'COL.',
            '/\bESCOLA\b/u' => 'E.',
            '/\bMUNICIPAL\b/u' => 'MUN.',
            '/\bPROFESSORA\b/u' => 'PROFª',
            '/\bPROFESSOR\b/u' => 'PROF.',
            '/\bDEPARTAMENTO\b/u' => 'DEP.',
            '/\bFACULDADE\b/u' => 'FAC.',
            '/\bFUNDAMENTAL\b/u' => 'FUND.',
            '/\bEDUCA[ÇC][AÃ]O\b/u' => 'ED.',
        ];

        foreach ($map as $pattern => $repl) {
            $nome = preg_replace($pattern, $repl, $nome);
        }
        $nome = trim(preg_replace('/\s+/u', ' ', $nome));

        if (mb_strlen($nome, 'UTF-8') <= $limite) {
            return $nome;
        }

        $stopwords = [' DE ', ' DA ', ' DO ', ' DAS ', ' DOS ', ' E ', ' EM ', ' PARA ', ' POR '];
        foreach ($stopwords as $sw) {
            $nome = preg_replace('/' . preg_quote($sw, '/') . '/u', ' ', ' ' . $nome . ' ');
            $nome = trim(preg_replace('/\s+/u', ' ', $nome));
            if (mb_strlen($nome, 'UTF-8') <= $limite) {
                return $nome;
            }
        }

        $palavras = preg_split('/\s+/u', $nome, -1, PREG_SPLIT_NO_EMPTY);
        $abrevPalavra = function (string $p): string {
            $len = mb_strlen($p, 'UTF-8');
            if ($len <= 4 || strpos($p, '.') !== false) return $p;
            if ($len >= 12) return mb_substr($p, 0, 4, 'UTF-8') . '.';
            if ($len >= 9) return mb_substr($p, 0, 3, 'UTF-8') . '.';
            if ($len >= 7) return mb_substr($p, 0, 2, 'UTF-8') . '.';
            return $p;
        };

        for ($i = 0; $i < count($palavras); $i++) {
            $palavras[$i] = $abrevPalavra($palavras[$i]);
            $temp = trim(preg_replace('/\s+/u', ' ', implode(' ', $palavras)));
            if (mb_strlen($temp, 'UTF-8') <= $limite) {
                return $temp;
            }
        }

        for ($i = 0; $i < count($palavras); $i++) {
            $p = $palavras[$i];
            if (mb_strlen($p, 'UTF-8') >= 6 && strpos($p, '.') === false) {
                $palavras[$i] = mb_substr($p, 0, 2, 'UTF-8') . '.';
            }
            $temp = trim(preg_replace('/\s+/u', ' ', implode(' ', $palavras)));
            if (mb_strlen($temp, 'UTF-8') <= $limite) {
                return $temp;
            }
        }

        $out = '';
        for ($i = 0, $n = mb_strlen($nome, 'UTF-8'); $i < $n; $i++) {
            $ch = mb_substr($nome, $i, 1, 'UTF-8');
            if (mb_strlen($out . $ch, 'UTF-8') > $limite) break;
            $out .= $ch;
        }
        return rtrim($out);
    }

    /**
     * Abrevia logradouros para NFCom (modelo 62) dentro do limite (padrão: 60 chars).
     */
    public static function abrevia_logradouro_nfcom(string $logradouro, int $limite = 60): string
    {
        if (!function_exists('mb_strlen')) {
            throw new RuntimeException('Extensão mbstring é necessária.');
        }

        $logradouro = trim(preg_replace('/\s+/u', ' ', $logradouro ?? ''));
        $logradouro = mb_strtoupper($logradouro, 'UTF-8');

        $map = [
            '/\bAVENIDA\b/u' => 'Av.',
            '/\bRUA\b/u' => 'R.',
            '/\bESTRADA\b/u' => 'Estr.',
            '/\bESTRADA MUNICIPAL\b/u' => 'Estr. Mun.',
            '/\bESTRADA ESTADUAL\b/u' => 'Estr. Est.',
            '/\bRODOVIA\b/u' => 'Rod.',
            '/\bALAMEDA\b/u' => 'Al.',
            '/\bTRAVESSA\b/u' => 'Tv.',
            '/\bVIELA\b/u' => 'Vl.',
            '/\bVIADUTO\b/u' => 'Vd.',
            '/\bPRAÇA\b/u' => 'Pç.',
            '/\bLARGO\b/u' => 'Lg.',
            '/\bBECO\b/u' => 'Bc.',
            '/\bPASSAGEM\b/u' => 'Ps.',
            '/\bPARQUE\b/u' => 'Pq.',
            '/\bJARDIM\b/u' => 'Jd.',
            '/\bCONJUNTO\b/u' => 'Cj.',
            '/\bLOTEAMENTO\b/u' => 'Lt.',
            '/\bCHÁCARA\b/u' => 'Ch.',
            '/\bSÍTIO\b/u' => 'St.',
            '/\bFAZENDA\b/u' => 'Faz.',
            '/\bVILA\b/u' => 'Vl.',
            '/\bDISTRITO\b/u' => 'Dist.',
            '/\bMUNICIPAL\b/u' => 'Mun.',
            '/\bESTADUAL\b/u' => 'Est.',
            '/\bFEDERAL\b/u' => 'Fed.',
        ];

        foreach ($map as $pattern => $repl) {
            $logradouro = preg_replace($pattern, $repl, $logradouro);
        }
        $logradouro = trim(preg_replace('/\s+/u', ' ', $logradouro));

        if (mb_strlen($logradouro, 'UTF-8') <= $limite) {
            return $logradouro;
        }

        $stopwords = [' DE ', ' DA ', ' DO ', ' DAS ', ' DOS ', ' E ', ' EM ', ' PARA ', ' POR ', ' - '];
        foreach ($stopwords as $sw) {
            $logradouro = preg_replace('/' . preg_quote($sw, '/') . '/u', ' ', ' ' . $logradouro . ' ');
            $logradouro = trim(preg_replace('/\s+/u', ' ', $logradouro));
            if (mb_strlen($logradouro, 'UTF-8') <= $limite) {
                return $logradouro;
            }
        }

        $palavras = preg_split('/\s+/u', $logradouro, -1, PREG_SPLIT_NO_EMPTY);
        $abrevPalavra = function (string $p): string {
            $len = mb_strlen($p, 'UTF-8');
            if ($len <= 4 || strpos($p, '.') !== false) return $p;
            if ($len >= 12) return mb_substr($p, 0, 4, 'UTF-8') . '.';
            if ($len >= 9) return mb_substr($p, 0, 3, 'UTF-8') . '.';
            if ($len >= 7) return mb_substr($p, 0, 2, 'UTF-8') . '.';
            return $p;
        };

        for ($i = 0; $i < count($palavras); $i++) {
            $palavras[$i] = $abrevPalavra($palavras[$i]);
            $temp = trim(preg_replace('/\s+/u', ' ', implode(' ', $palavras)));
            if (mb_strlen($temp, 'UTF-8') <= $limite) {
                return $temp;
            }
        }

        for ($i = 0; $i < count($palavras); $i++) {
            $p = $palavras[$i];
            if (mb_strlen($p, 'UTF-8') >= 6 && strpos($p, '.') === false) {
                $palavras[$i] = mb_substr($p, 0, 2, 'UTF-8') . '.';
            }
            $temp = trim(preg_replace('/\s+/u', ' ', implode(' ', $palavras)));
            if (mb_strlen($temp, 'UTF-8') <= $limite) {
                return $temp;
            }
        }

        $out = '';
        for ($i = 0, $n = mb_strlen($logradouro, 'UTF-8'); $i < $n; $i++) {
            $ch = mb_substr($logradouro, $i, 1, 'UTF-8');
            if (mb_strlen($out . $ch, 'UTF-8') > $limite) break;
            $out .= $ch;
        }
        return rtrim($out);
    }


    public static function gerarXmlNFCom($dados, $cnpjEmit, $ambiente)
    {
        // Geração correta do cUF, cMunFG, dhEmi etc.
        $cUF = self::getCodigoUF($dados['emitente']['uf']);
        $cMunFG = $dados['emitente']['codMun'];
        $tpAmb = ($ambiente == "homologacao") ? 2 : 1;
        $mod = '62';
        $serie = 1;
        $nNF = $dados['numero_nota'];
        $tpEmis = 1;
        $nSiteAutoriz = 0;
        $finNFCom = 0;
        $tpFat = 0;
        $verProc = '1.0';
        $procEmi = 0;
        $dhEmi = date('Y-m-d\TH:i:sP');
        $prepago = $dados['pre-pago'];
        $cont = 1;

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
        $xml .= '<emit><CNPJ>' . self::limparNumeros($dados['emitente']['cnpj']) . '</CNPJ>';
        // IE do emitente: se vazia, ISENTO ou ISENTA, não incluir a tag <IE>
        $ieEmitente = isset($dados['emitente']['ie']) ? trim($dados['emitente']['ie']) : '';
        if (!empty($ieEmitente) && strtoupper($ieEmitente) !== 'ISENTO' && strtoupper($ieEmitente) !== 'ISENTA') {
            $xml .= '<IE>' . self::limparNumeros($ieEmitente) . '</IE>';
        }
        // Se IE estiver vazia, ISENTO ou ISENTA, não incluir a tag <IE>
        $xml .= '<CRT>'.$dados['emitente']['CRT'].'</CRT><xNome>' . htmlspecialchars($dados['emitente']['nome']) . '</xNome>';
        $xml .= '<enderEmit>';
        // Aplica abreviação no logradouro do emitente (limite de 60 caracteres)
        $logradouroEmitente = self::abrevia_logradouro_nfcom($dados['emitente']['endereco'], 60);
        $xml .= '<xLgr>' . htmlspecialchars($logradouroEmitente) . '</xLgr>';
        $xml .= '<nro>' . htmlspecialchars($dados['emitente']['numero']) . '</nro>';
        $xml .= '<xBairro>' . htmlspecialchars($dados['emitente']['bairro']) . '</xBairro>';
        $xml .= '<cMun>' . $cMunFG . '</cMun>';
        $xml .= '<xMun>' . htmlspecialchars($dados['emitente']['cidade']) . '</xMun>';
        $xml .= '<CEP>' . preg_replace('/\D/', '', $dados['emitente']['cep']) . '</CEP>';
        $xml .= '<UF>' . htmlspecialchars($dados['emitente']['uf']) . '</UF>';
        $xml .= '<fone>' . htmlspecialchars(self::limparNumeros($dados['emitente']['telefone'])) . '</fone>';
        $xml .= '</enderEmit></emit>';

        // destinatário
        // Aplica abreviação no nome do destinatário (limite de 60 caracteres)
        $nomeDestinatario = self::abrevia_xnome_nfcom($dados['destinatario']['nome'], 60);
        $xml .= '<dest><xNome>' . htmlspecialchars($nomeDestinatario) . '</xNome>';
        if($dados['destinatario']['uf'] == "EX"){
            $xml .= '<idOutros>9999999</idOutros>';
            $xml .= '<indIEDest>9</indIEDest>';
            $xml .= '<enderDest>';
                $logradouroDestinatario = self::abrevia_logradouro_nfcom($dados['destinatario']['endereco'], 60);
                $xml .= '<xLgr>' . htmlspecialchars($logradouroDestinatario) . '</xLgr>';
                $xml .= '<nro>' . htmlspecialchars($dados['destinatario']['numero']) . '</nro>';
                $xml .= '<xBairro>' . htmlspecialchars($dados['destinatario']['bairro']) . '</xBairro>';
                $xml .= '<cMun>9999999</cMun>';
                $xml .= '<xMun>EXTERIOR</xMun>';
                $xml .= '<CEP>99999999</CEP>';
                $xml .= '<UF>EX</UF>';
                $xml .= '<cPais>00655</cPais>';
                $xml .= '<xPais>ARUBA</xPais>';
            $xml .= '</enderDest></dest>';
        }else{
            $cpfcnpj = preg_replace('/\D/', '', $dados['destinatario']['cpfcnpj']);
            $xml .= strlen(self::limparNumeros($cpfcnpj)) == 11 ? '<CPF>' . self::limparNumeros($cpfcnpj) . '</CPF>' : '<CNPJ>' . self::limparNumeros($cpfcnpj) . '</CNPJ>';
            
            // Lógica de IE: se IE estiver vazia ou for "ISENTO"/"ISENTA", usar indIEDest = 9 e não incluir tag <IE>
            $ie = isset($dados['destinatario']['ie']) ? trim($dados['destinatario']['ie']) : '';
            $indIEDest = isset($dados['destinatario']['indIEDest']) ? intval($dados['destinatario']['indIEDest']) : null;
            
            // Se IE estiver vazia ou for 'ISENTO'/'ISENTA', sempre definir indIEDest = 9 (Não contribuinte)
            if (empty($ie) || strtoupper($ie) === 'ISENTO' || strtoupper($ie) === 'ISENTA') {
                $indIEDest = 9;
            } else {
                // Se IE está preenchida e indIEDest não foi informado, assumir 1 (contribuinte)
                if ($indIEDest === null) {
                    $indIEDest = 1;
                }
            }
            
            // Se ainda não foi definido, usar 9 como padrão (não contribuinte)
            if ($indIEDest === null) {
                $indIEDest = 9;
            }
            
            $xml .= '<indIEDest>' . $indIEDest . '</indIEDest>';
            
            // Só incluir tag <IE> se indIEDest for 1 (contribuinte) e IE estiver preenchida
            // Quando IE vazia ou "ISENTO"/"ISENTA" ou indIEDest = 9, não informar a tag <IE>
            if ($indIEDest == 1 && !empty($ie) && strtoupper($ie) !== 'ISENTO' && strtoupper($ie) !== 'ISENTA') {
                $xml .= '<IE>' . self::limparNumeros($ie) . '</IE>';
            }
            // Se indIEDest = 9 ou IE vazia/ISENTO/ISENTA, não incluir a tag <IE>
            $xml .= '<enderDest>';
            // Aplica abreviação no logradouro do destinatário (limite de 60 caracteres)
            $logradouroDestinatario = self::abrevia_logradouro_nfcom($dados['destinatario']['endereco'], 60);
            $xml .= '<xLgr>' . htmlspecialchars($logradouroDestinatario) . '</xLgr>';
            $xml .= '<nro>' . htmlspecialchars($dados['destinatario']['numero']) . '</nro>';
            $xml .= '<xBairro>' . htmlspecialchars($dados['destinatario']['bairro']) . '</xBairro>';
            $xml .= '<cMun>' . $dados['destinatario']['codMun'] . '</cMun>';
            $xml .= '<xMun>' . htmlspecialchars($dados['destinatario']['cidade']) . '</xMun>';
            $xml .= '<CEP>' . preg_replace('/\D/', '', $dados['destinatario']['cep']) . '</CEP>';
            $xml .= '<UF>' . htmlspecialchars($dados['destinatario']['uf']) . '</UF>';
            $xml .= '</enderDest></dest>';
        }

        $xml .= '<assinante><iCodAssinante>'. htmlspecialchars($dados['assinante']['CodAssinante']) .'</iCodAssinante><tpAssinante>'. htmlspecialchars($dados['assinante']['tpAssinante']) .'</tpAssinante><tpServUtil>'. htmlspecialchars($dados['assinante']['tpServUtil']) .'</tpServUtil><nContrato>'. htmlspecialchars($dados['assinante']['Contrato']) .'</nContrato><dContratoIni>'. htmlspecialchars($dados['assinante']['DtInicio']) .'</dContratoIni>';
        // if($dados['assinante']['tpServUtil'] == 1){
        //     $xml .= '<NroTermPrinc>'. htmlspecialchars($dados['assinante']['CodAssinante']) .'</NroTermPrinc><cUFPrinc>'. htmlspecialchars($dados['assinante']['cUFPrinc']) .'</cUFPrinc>';
        // }
        $xml .= '</assinante>';

        $vProd = 0; $vBC = 0; $vICMS = 0;
        foreach ($dados['itens'] as $item) {
            $xml .= '<det nItem="' . intval($cont) . '"><prod>';
                $xml .= '<cProd>' . htmlspecialchars($item['item']) . '</cProd>';
                $xml .= '<xProd>' . htmlspecialchars($item['descricao']) . '</xProd>';
                $xml .= '<cClass>' . htmlspecialchars($item['cclass']) . '</cClass>';
                $xml .= '<CFOP>' . htmlspecialchars($item['cfop']) . '</CFOP>';
                $xml .= '<uMed>' . $item['uMed']. '</uMed>';
                $xml .= '<qFaturada>' . number_format($item['quantidade'], 2, '.', '') . '</qFaturada>';
                $xml .= '<vItem>' . number_format($item['unitario'], 2, '.', '') . '</vItem>';
                $xml .= '<vProd>' . number_format($item['total'], 2, '.', '') . '</vProd>';
                $xml .= '</prod>';
                if($dados['emitente']['CRT'] == 1){
                    $xml .= '<imposto><ICMSSN><CST>90</CST><indSN>1</indSN>';
                    $xml .= '</ICMSSN></imposto>';
                }else{
                    if(isset($item['impostos']['tipo_icms'])){
                        $xml .= '<imposto>';
                    switch ($item['impostos']['tipo_icms']) {
                        case '00':
                            $icms = "ICMS00";
                            break;
                        case '20':
                            $icms = "ICMS20";
                            break;
                        case '40':
                            $icms = "ICMS40";
                            break;
                        case '51':
                            $icms = "ICMS51";
                            break;
                        case '90':
                            $icms = "ICMS00";
                            break;
                        default:
                            $icms = "ICMS00";
                            break;
                    }
                        $xml .= '<'.$icms.'>';
                            $xml .= '<CST>' . $item['impostos']['cst'] . '</CST>';
                            if($icms == 'ICMS20'){
                                $xml .= '<pRedBC>' . number_format($item['impostos']['pRedBC'], 2, '.', '') . '</pRedBC>';
                            }
                            if($icms != 'ICMS40' && $icms != 'ICMS51'){
                                $xml .= '<vBC>' . number_format($item['impostos']['bc_icms'], 2, '.', '') . '</vBC>';
                                $xml .= '<pICMS>' . number_format($item['impostos']['aliq_icms'], 2, '.', '') . '</pICMS>';
                                $xml .= '<vICMS>' . number_format($item['impostos']['val_icms'], 2, '.', '') . '</vICMS>';
                            }
                            if($icms == 'ICMS20' || $icms == 'ICMS40' || $icms == 'ICMS51' || $icms == 'ICMS90'){
                                $xml .= '<vICMSDeson>' . number_format($item['impostos']['vICMSDeson'], 2, '.', '') . '</vICMSDeson>';
                                $xml .= '<cBenef>' . number_format($item['impostos']['cBenef'], 2, '.', '') . '</cBenef>';
                            }
                            if($icms != 'ICMS40' && $icms != 'ICMS51'){
                                $xml .= '<pFCP>' . number_format($item['impostos']['pFCP'], 2, '.', '') . '</pFCP>';
                                $xml .= '<vFCP>' . number_format($item['impostos']['vFCP'], 2, '.', '') . '</vFCP>';
                            }
                        $xml .= '</'.$icms.'>';
                        if($item['impostos']['difal']['vBCUFDest'] > 0){
                            $xml .= '<cUFDest>' .$item['impostos']['difal']['cUFDest']. '</cUFDest>';
                            $xml .= '<vBCUFDest>' . number_format($item['impostos']['difal']['vBCUFDest'], 2, '.', '') . '</vBCUFDest>';
                            $xml .= '<pFCPUFDest>' . number_format($item['impostos']['difal']['pFCPUFDest'], 2, '.', '') . '</pFCPUFDest>';
                            $xml .= '<pICMSUFDest>' . number_format($item['impostos']['difal']['pICMSUFDest'], 2, '.', '') . '</pICMSUFDest>';
                            $xml .= '<vFCPUFDest>' . number_format($item['impostos']['difal']['vFCPUFDest'], 2, '.', '') . '</vFCPUFDest>';
                            $xml .= '<vICMSUFDest>' . number_format($item['impostos']['difal']['vICMSUFDest'], 2, '.', '') . '</vICMSUFDest>';
                            $xml .= '<vICMSUFEmi>' . number_format($item['impostos']['difal']['vICMSUFEmi'], 2, '.', '') . '</vICMSUFEmi>';
                            $xml .= '<cBenefUFDest>' . number_format($item['impostos']['difal']['cBenefUFDest'], 2, '.', '') . '</cBenefUFDest>';
                            $xml .= '<indSemCST>' . number_format($item['impostos']['difal']['indSemCST'], 2, '.', '') . '</indSemCST>';
                        }
                        if(isset($item['impostos']['pis']['pis_cst'])){
                            $xml .= '<PIS>';
                                $xml .= '<CST>' . $item['impostos']['pis']['pis_cst'] . '</CST>';
                                $xml .= '<vBC>' . number_format($item['impostos']['pis']['pis_bc'], 2, '.', '') . '</vBC>';
                                $xml .= '<pPIS>' . number_format($item['impostos']['pis']['pis_aliq'], 2, '.', '') . '</pPIS>';
                                $xml .= '<vPIS>' . number_format($item['impostos']['pis']['pis_valor'], 2, '.', '') . '</vPIS>';
                            $xml .= '</PIS>';
                        }
                        if(isset($item['impostos']['cofins']['cofins_cst'])){
                            $xml .= '<COFINS>';
                                $xml .= '<CST>' . $item['impostos']['cofins']['cofins_cst'] . '</CST>';
                                $xml .= '<vBC>' . number_format($item['impostos']['cofins']['cofins_bc'], 2, '.', '') . '</vBC>';
                                $xml .= '<pCOFINS>' . number_format($item['impostos']['cofins']['cofins_aliq'], 2, '.', '') . '</pCOFINS>';
                                $xml .= '<vCOFINS>' . number_format($item['impostos']['cofins']['cofins_valor'], 2, '.', '') . '</vCOFINS>';
                            $xml .= '</COFINS>';
                        }
                        if(isset($item['impostos']['fust']['fust_bc'])){
                            $xml .= '<FUST>';
                                $xml .= '<vBC>' . number_format($item['impostos']['fust']['fust_bc'], 2, '.', '') . '</vBC>';
                                $xml .= '<pFUST>' . number_format($item['impostos']['fust']['fust_aliq'], 2, '.', '') . '</pFUST>';
                                $xml .= '<vFUST>' . number_format($item['impostos']['fust']['fust_valor'], 2, '.', '') . '</vFUST>';
                            $xml .= '</FUST>';
                        }
                        if(isset($item['impostos']['funttel']['funttel_bc'])){
                            $xml .= '<FUNTTEL>';
                                $xml .= '<vBC>' . number_format($item['impostos']['funttel']['funttel_bc'], 2, '.', '') . '</vBC>';
                                $xml .= '<pFUNTTEL>' . number_format($item['impostos']['funttel']['funttel_aliq'], 2, '.', '') . '</pFUNTTEL>';
                                $xml .= '<vFUNTTEL>' . number_format($item['impostos']['funttel']['funttel_valor'], 2, '.', '') . '</vFUNTTEL>';
                            $xml .= '</FUNTTEL>';
                        }
                        if(isset($item['impostos']['retencao']['vRetPIS'])){
                            $xml .= '<retTrib>';
                                $xml .= '<vRetPIS>' . number_format($item['impostos']['retencao']['ret_pis'], 2, '.', '') . '</vRetPIS>';
                                $xml .= '<vRetCofins>' . number_format($item['impostos']['retencao']['ret_cofins'], 2, '.', '') . '</vRetCofins>';
                                $xml .= '<vRetCSLL>' . number_format($item['impostos']['retencao']['ret_csll'], 2, '.', '') . '</vRetCSLL>';
                                $xml .= '<vBCIRRF>' . number_format($item['impostos']['retencao']['ret_irrf_bc'], 2, '.', '') . '</vBCIRRF>';
                                $xml .= '<vIRRF>' . number_format($item['impostos']['retencao']['ret_irrf_val'], 2, '.', '') . '</vIRRF>';
                            $xml .= '</retTrib>';
                        }
                    $xml .= '</imposto>';
                    }
                }
            $xml .= '</det>';
            $cont++;
        }

        $xml .= '<total>';
            $xml .= '<vProd>' . number_format($dados['totais']['total_prod'], 2, '.', '') . '</vProd>';
            $xml .= '<ICMSTot>';
                $xml .= '<vBC>' . number_format($dados['totais']['bc_icms_total'], 2, '.', '') . '</vBC>';
                $xml .= '<vICMS>' . number_format($dados['totais']['val_icms_total'], 2, '.', '') . '</vICMS>';
                $xml .= '<vICMSDeson>' . number_format($dados['totais']['icms_des'], 2, '.', '') . '</vICMSDeson>';
                $xml .= '<vFCP>' . number_format($dados['totais']['fcp'], 2, '.', '') . '</vFCP>';
            $xml .= '</ICMSTot>';
            $xml .= '<vCOFINS>' . number_format($dados['totais']['cofins'], 2, '.', '') . '</vCOFINS>';
            $xml .= '<vPIS>' . number_format($dados['totais']['pis'], 2, '.', '') . '</vPIS>';
            $xml .= '<vFUNTTEL>' . number_format($dados['totais']['funttel'], 2, '.', '') . '</vFUNTTEL>';
            $xml .= '<vFUST>' . number_format($dados['totais']['fust'], 2, '.', '') . '</vFUST>';
            $xml .= '<vRetTribTot>';
                $xml .= '<vRetPIS>' . number_format($dados['totais']['retPis'], 2, '.', '') . '</vRetPIS>';
                $xml .= '<vRetCofins>' . number_format($dados['totais']['retCofins'], 2, '.', '') . '</vRetCofins>';
                $xml .= '<vRetCSLL>' . number_format($dados['totais']['relCSLL'], 2, '.', '') . '</vRetCSLL>';
                $xml .= '<vIRRF>' . number_format($dados['totais']['retIRRF'], 2, '.', '') . '</vIRRF>';
            $xml .= '</vRetTribTot>';
                $xml .= '<vDesc>' . number_format($dados['totais']['desconto'], 2, '.', '') . '</vDesc>';
                $xml .= '<vOutro>' . number_format($dados['totais']['outros'], 2, '.', '') . '</vOutro>';
                $xml .= '<vNF>' . number_format($dados['totais']['total_nf'], 2, '.', '') . '</vNF>';
        $xml .= '</total>';
       

        $xml .= '<gFat><CompetFat>' . htmlspecialchars($dados['fatura']['CompetFat']) . '</CompetFat><dVencFat>' . htmlspecialchars($dados['fatura']['dVencFat']) . '</dVencFat>';
        if($dados['fatura']['codBarras']!=""){
            $xml .= '<codBarras>' . $dados['fatura']['codBarras'] . '</codBarras>';
        }else{
            $xml .= '<codBarras>99999888887777766666555554444433333222221111</codBarras>';
        }
        

        if(isset($dados['fatura']['codDebAuto']) && $dados['fatura']['codDebAuto'] != 0){
            $xml .= '<codDebAuto>'.$dados['fatura']['codDebAuto'].'</codDebAuto>';
        }
        $xml .= '</gFat>';

        $xml .= '<autXML>';
            $xml .= '<CNPJ>' . self::limparNumeros($dados['emitente']['cnpj']) . '</CNPJ>';
        $xml .= '</autXML>';

        $mensagem = preg_replace('/\s+/', ' ', $dados['mensagem']);

        $xml .= '<infAdic><infCpl>' . $mensagem . '</infCpl></infAdic>';
        $xml .= '<gRespTec><CNPJ>65040693000191</CNPJ><xContato>Suporte</xContato><email>benito@vssistemas.com.br</email><fone>12997877084</fone></gRespTec>';

        $xml .= '</infNFCom>'; // FECHA infNFCom — assinatura será fora

        $chaveNumerica = substr($chaveAcesso, 5); // remove "NFCom"


        // infNFComSupl será depois da assinatura
        if($cUF == 31){
            if($tpAmb == 1){
                $xml .= '<infNFComSupl><qrCodNFCom>https://portalnfcom.fazenda.mg.gov.br/qrcode?chNFCom=' . $chaveNumerica . '&amp;tpAmb=' . $tpAmb . '</qrCodNFCom></infNFComSupl>';
            }else{
                $xml .= '<infNFComSupl><qrCodNFCom>https://portalnfcom.fazenda.mg.gov.br/qrcode?chNFCom=' . $chaveNumerica . '&amp;tpAmb=' . $tpAmb . '</qrCodNFCom></infNFComSupl>';
            }
        }elseif ($cUF == 51) {
            if($tpAmb == 1){
                $xml .= '<infNFComSupl><qrCodNFCom>https://www.sefaz.mt.gov.br/nfcom-ext-fe/qrcode?chNFCom=' . $chaveNumerica . '&amp;tpAmb=' . $tpAmb . '</qrCodNFCom></infNFComSupl>';
            }else{
                $xml .= '<infNFComSupl><qrCodNFCom>https://homologacao.sefaz.mt.gov.br/nfcom-ext-fe/qrcode?chNFCom=' . $chaveNumerica . '&amp;tpAmb=' . $tpAmb . '</qrCodNFCom></infNFComSupl>';
            }
        }elseif ($cUF == 50) {
            if($tpAmb == 1){
                $xml .= '<infNFComSupl><qrCodNFCom>https://www.dfe.ms.gov.br/nfcom/qrcode?chNFCom=' . $chaveNumerica . '&amp;tpAmb=' . $tpAmb . '</qrCodNFCom></infNFComSupl>';
            }else{
                $xml .= '<infNFComSupl><qrCodNFCom>https://www.dfe.ms.gov.br/nfcom/qrcode?chNFCom=' . $chaveNumerica . '&amp;tpAmb=' . $tpAmb . '</qrCodNFCom></infNFComSupl>';
            }
        }
	else{
            $xml .= '<infNFComSupl><qrCodNFCom>https://dfe-portal.svrs.rs.gov.br/NFCom/QRCode?chNFCom=' . $chaveNumerica . '&amp;tpAmb=' . $tpAmb . '</qrCodNFCom></infNFComSupl>';
        }
        

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
