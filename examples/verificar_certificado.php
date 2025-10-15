<?php

require_once __DIR__ . '/../vendor/autoload.php';

use NFCom\NFComTools;

try {
    // Configurações
    $cnpj = '41151201000177';
    $senha = '123456';
    $ambiente = 'homologacao';
    $pfxPath = __DIR__ . '/../storage/certificados/' . $cnpj . '/certificado.pfx';

    // Instancia a classe
    $tools = new NFComTools($cnpj, $senha, $ambiente);

    // Verifica o certificado
    $resultado = $tools->verificarCertificado($pfxPath, $senha);

    // Exibe o resultado
    echo "Status: " . $resultado['mensagem'] . "\n";
    echo "CNPJ: " . $resultado['detalhes']['cnpj'] . "\n";
    echo "Válido até: " . $resultado['detalhes']['valido_ate'] . "\n";
    echo "Tem assinatura digital: " . ($resultado['detalhes']['tem_assinatura_digital'] ? 'Sim' : 'Não') . "\n";
    echo "Tem permissão NFCom: " . ($resultado['detalhes']['tem_nfcom'] ? 'Sim' : 'Não') . "\n";
    echo "Emissor: " . $resultado['detalhes']['emissor'] . "\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
} 