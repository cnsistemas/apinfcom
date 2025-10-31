<?php

namespace NFCom;

use DOMDocument;
use Exception;
use NFePHP\Common\Signer;
use NFePHP\Common\Certificate;

class NFComTools
{
    private string $cnpj;
    private string $certPath;
    private string $certPassword;
    public string $environment;
    private string $privateKey;
    private string $publicCert;
    private string $url;
    private string $certPemPath;
    private string $keyPemPath;

    public function __construct(string $cnpj, string $certPassword, string $environment = 'homologacao')
    {
        $this->cnpj = preg_replace('/\D/', '', $cnpj);
        $this->certPassword = $certPassword;
        $this->environment = $environment;
        $this->certPath = __DIR__ . "/../../storage/certificados/{$this->cnpj}/cert.pfx";
        $this->certPemPath = __DIR__ . "/../../storage/certificados/{$this->cnpj}/cert.pem";
        $this->keyPemPath = __DIR__ . "/../../storage/certificados/{$this->cnpj}/key.pem";

        if (!file_exists($this->certPath)) {
            throw new Exception("Certificado PFX não encontrado para o CNPJ: {$this->cnpj}");
        }

        $this->loadCert();
    }

    private function loadCert(): void
    {
        $pfxContent = file_get_contents($this->certPath);
        $certs = [];

        if (!openssl_pkcs12_read($pfxContent, $certs, $this->certPassword)) {
            throw new Exception("Não foi possível ler o certificado PFX. Verifique a senha.");
        }

        file_put_contents($this->certPemPath, $certs['cert']);
        file_put_contents($this->keyPemPath, $certs['pkey']);

        /* // Verificação detalhada do certificado
        $certInfo = openssl_x509_parse($certs['cert']);
        if (!$certInfo) {
            throw new Exception("Erro ao analisar o certificado");
        }

        // Verifica validade
        $now = time();
        if ($now < $certInfo['validFrom_time_t'] || $now > $certInfo['validTo_time_t']) {
            throw new Exception("Certificado fora do período de validade");
        }

        // Verifica CNPJ
        $cnpj = '';
        if (isset($certInfo['subject']['CN'])) {
            $cnpj = preg_replace('/[^0-9]/', '', $certInfo['subject']['CN']);
        }
        if ($cnpj !== $this->cnpj) {
            throw new Exception("CNPJ do certificado ({$cnpj}) não corresponde ao CNPJ informado ({$this->cnpj})");
        }

        // Verifica permissões - Lista todas as extensões do certificado
        $permissoes = [];
        if (isset($certInfo['extensions'])) {
            foreach ($certInfo['extensions'] as $oid => $value) {
                $permissoes[] = "OID: $oid = $value";
            }
        }

        // Log das informações do certificado
        error_log("Certificado carregado com sucesso:");
        error_log("CNPJ: " . $cnpj);
        error_log("Válido de: " . date('Y-m-d', $certInfo['validFrom_time_t']));
        error_log("Válido até: " . date('Y-m-d', $certInfo['validTo_time_t']));
        error_log("Emissor: " . $certInfo['issuer']['O']);
        error_log("Permissões encontradas: " . implode(", ", $permissoes));

        // Verifica se é um certificado A1
        if (!isset($certInfo['extensions']['1.2.3.4.5.6.7.8.9'])) {
            throw new Exception("Certificado não é do tipo A1. Permissões encontradas: " . implode(", ", $permissoes));
        } */

        $this->privateKey = $certs['pkey'];
        $this->publicCert = $certs['cert'];
    }

    public function assinarXML(string $xml, string $tag = 'infNFCom', string $idAttr = 'Id'): string
    {
        $certContent = file_get_contents($this->certPath);
        $certificate = Certificate::readPfx($certContent, $this->certPassword);

        $xmlAssinado = Signer::sign(
            $certificate, // Objeto Certificate
            $xml,         // XML a ser assinado
            $tag,         // Tag a ser assinada
            $idAttr       // Atributo de ID
        );

        return $xmlAssinado;
    }


    public function compactarXML(string $xml): string
    {
        // Remove espaços e quebras de linha extras
        $xml = preg_replace('/\s+/', ' ', $xml);
        $xml = trim($xml);

        $tmpIn = tempnam(sys_get_temp_dir(), 'nfcom_xml_');
        $tmpOut = $tmpIn . '.gz';
        file_put_contents($tmpIn, $xml);

        // Compactar com gzip via shell
        $cmd = "gzip -c " . escapeshellarg($tmpIn) . " > " . escapeshellarg($tmpOut);
        shell_exec($cmd);

        $gzData = file_get_contents($tmpOut);
        unlink($tmpIn);
        unlink($tmpOut);

        if ($gzData === false) {
            throw new \Exception("Erro ao ler gzip gerado");
        }

        return base64_encode($gzData);
    }

    public function enviarSOAP(string $xmlCompactado, string $service, $uf=""): string
    {
        $urlBase = $this->environment === 'producao'
            ? 'https://nfcom.svrs.rs.gov.br/WS/'
            : 'https://nfcom-homologacao.svrs.rs.gov.br/WS/';

        $this->url = $urlBase . $service . '/' . $service . '.asmx';

        $soapActions = [
            'NFComRecepcao'        => 'nfcomRecepcao',
            'NFComConsulta'        => 'nfcomConsulta',
            'NFComRecepcaoEvento'  => 'nfcomRecepcaoEvento',
            'NFComStatusServico'   => 'nfcomStatusServico',
        ];

        if (!isset($soapActions[$service])) {
            throw new Exception("Serviço SOAP desconhecido: {$service}");
        }

        $soapAction = "http://www.portalfiscal.inf.br/nfcom/wsdl/{$service}/{$soapActions[$service]}";

        if($uf == "MG" || $uf == "31"){
            $this->url = "https://nfcom.fazenda.mg.gov.br/nfcom/services" . $service;
        }
        if($uf == "MT" || $uf == "51"){
            $this->url = "https://nfcom.fazenda.mt.gov.br/nfcom/services" . $service;
        }

        // Corpo do envelope corrigido (sem tag aninhada)
        $soap = <<<XML
    <soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Body>
        <nfcomDadosMsg xmlns="http://www.portalfiscal.inf.br/nfcom/wsdl/{$service}">
        {$xmlCompactado}
        </nfcomDadosMsg>
    </soap:Body>
    </soap:Envelope>
    XML;

        $headers = [
            "Content-Type: application/soap+xml; charset=utf-8; action=\"{$soapAction}\"",
            "SOAPAction: \"{$soapAction}\""
        ];

        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $soap,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSLCERT        => $this->certPemPath,
            CURLOPT_SSLKEY         => $this->keyPemPath,
            CURLOPT_TIMEOUT        => 60, // Aumente o tempo de timeout aqui (em segundos)
            CURLOPT_CONNECTTIMEOUT => 30, // Aumente o tempo de conexão aqui (em segundos)
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_VERBOSE => true
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("Erro na comunicação SOAP: " . curl_error($ch));
        }

        curl_close($ch);
        return $response;
    }

    /**
     * Verifica se o certificado .pfx é válido e tem permissão para NFCom
     * @param string $pfxPath Caminho do arquivo .pfx
     * @param string $senha Senha do certificado
     * @return array Resultado da verificação
     */
    public function verificarCertificado(string $pfxPath, string $senha): array
    {
        $resultado = [
            'valido' => false,
            'mensagem' => '',
            'detalhes' => []
        ];

        try {
            // Lê o conteúdo do certificado
            $pfxContent = file_get_contents($pfxPath);
            if (!$pfxContent) {
                throw new Exception("Não foi possível ler o arquivo do certificado");
            }

            // Tenta abrir o certificado
            $certs = [];
            if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
                throw new Exception("Senha incorreta ou certificado inválido");
            }

            // Analisa o certificado
            $certInfo = openssl_x509_parse($certs['cert']);
            if (!$certInfo) {
                throw new Exception("Erro ao analisar o certificado");
            }

            // Verifica validade
            $now = time();
            $valido = ($now >= $certInfo['validFrom_time_t'] && $now <= $certInfo['validTo_time_t']);
            
            // Verifica CNPJ
            $cnpj = '';
            if (isset($certInfo['subject']['CN'])) {
                $cnpj = preg_replace('/[^0-9]/', '', $certInfo['subject']['CN']);
            }

            // Verifica permissões
            $temAssinaturaDigital = false;
            $temNFCom = false;

            if (isset($certInfo['extensions']['keyUsage'])) {
                $keyUsage = $certInfo['extensions']['keyUsage'];
                $temAssinaturaDigital = (strpos($keyUsage, 'Digital Signature') !== false);
            }

            if (isset($certInfo['extensions']['extendedKeyUsage'])) {
                $extendedKeyUsage = $certInfo['extensions']['extendedKeyUsage'];
                $temNFCom = (strpos($extendedKeyUsage, 'Document Signing') !== false);
            }

            // Monta resultado
            $resultado['valido'] = $valido && $temAssinaturaDigital;
            $resultado['detalhes'] = [
                'cnpj' => $cnpj,
                'valido_de' => date('Y-m-d', $certInfo['validFrom_time_t']),
                'valido_ate' => date('Y-m-d', $certInfo['validTo_time_t']),
                'tem_assinatura_digital' => $temAssinaturaDigital,
                'tem_nfcom' => $temNFCom,
                'emissor' => $certInfo['issuer']['O'] ?? 'Desconhecido'
            ];

            if (!$valido) {
                $resultado['mensagem'] = "Certificado fora do período de validade";
            } elseif (!$temAssinaturaDigital) {
                $resultado['mensagem'] = "Certificado não possui permissão para assinatura digital";
            } elseif (!$temNFCom) {
                $resultado['mensagem'] = "Certificado não possui permissão para NFCom";
            } else {
                $resultado['mensagem'] = "Certificado válido e com permissões para NFCom";
            }

        } catch (Exception $e) {
            $resultado['mensagem'] = "Erro: " . $e->getMessage();
        }

        return $resultado;
    }
}
