<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

use NFCom\NFComEmissao;
use NFCom\NFComConsulta;
use NFCom\NFComCancelamento;
use NFCom\NFComTools;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

$key = 'SEGREDO_SUPER_SEGURO';

$jwtMiddleware = function (Request $request, $handler) use ($key) {
    $authHeader = $request->getHeaderLine('Authorization');
    $token = str_replace('Bearer ', '', $authHeader);

    try {
        if (!$token) {
            throw new Exception('Token ausente');
        }
        $decoded = JWT::decode($token, new Key($key, 'HS256'));
        return $handler->handle($request);
    } catch (Exception $e) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(['error' => 'Unauthorized: ' . $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
};

$app->post('/login', function (Request $request, Response $response) use ($key) {
    $body = json_decode($request->getBody()->getContents(), true);
    $usuario = $body['usuario'] ?? '';
    $senha = $body['senha'] ?? '';

    if ($usuario === 'admin' && $senha === '123456') {
        $payload = [
            'sub' => $usuario,
            'iat' => time(),
            'exp' => time() + 3600
        ];
        $jwt = JWT::encode($payload, $key, 'HS256');
        $response->getBody()->write(json_encode(['token' => $jwt]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(['error' => 'Credenciais inválidas']));
    return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
});

$app->group('/nfcom', function (RouteCollectorProxy $group) {
    $group->post('', function (Request $request, Response $response) {
        $dados = json_decode($request->getBody()->getContents(), true);

        if (!is_array($dados)) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'JSON inválido.']));
        }

        try {

            $cnpj = $dados['cnpj_emitente'];
            $senha = $dados['senha'];
            $emissor = new NFComEmissao($cnpj, $senha);
            $res = $emissor->emitir($dados);
            $response->getBody()->write(json_encode($res));
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400);
        }

        return $response->withHeader('Content-Type', 'application/json');
    });

    $group->post('/cancelar', function (Request $request, Response $response) {
        $dados = json_decode($request->getBody()->getContents(), true);

        if (!is_array($dados)) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'JSON inválido.']));
        }

        try {
            if (!isset($dados['cnpj'], $dados['senha'], $dados['chave'], $dados['protocolo'])) {
                throw new Exception('Campos obrigatórios ausentes para cancelamento.');
            }

            $cnpj = $dados['cnpj'];
            $senha = $dados['senha'];
            $cancelador = new NFComCancelamento($cnpj, $senha);
            $res = $cancelador->cancelar($dados);
            $response->getBody()->write(json_encode($res));
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400);
        }

        return $response->withHeader('Content-Type', 'application/json');
    });

    $group->get('/{chave}', function (Request $request, Response $response, $args) {
        $chave = $args['chave'];
        $params = $request->getQueryParams();

        try {
            $cnpj = $params['cnpj'] ?? '';
            $senha = $params['senha'] ?? '';
            $ambiente = $params['ambiente'] ?? 'homologacao';
            if (!$cnpj || !$senha) {
                throw new Exception('CNPJ e senha são obrigatórios na consulta.');
            }
            $consultor = new NFComConsulta($cnpj, $senha);
            $res = $consultor->consultar($chave, $cnpj, $senha, $ambiente);
            $response->getBody()->write(json_encode($res));
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400);
        }

        return $response->withHeader('Content-Type', 'application/json');
    });

    $group->post('/status', function (Request $request, Response $response) {
        $dados = json_decode($request->getBody()->getContents(), true);

        try {
            $cnpj = $dados['cnpj'] ?? '';
            $senha = $dados['senha'] ?? '';
            if (!$cnpj || !$senha) {
                throw new Exception('CNPJ e senha são obrigatórios.');
            }

            $tools = new NFComTools($cnpj, $senha);
            $tpAmb = $tools->environment === 'producao' ? '1' : '2';

            $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>"
                . "<consStatServNFCom xmlns=\"http://www.portalfiscal.inf.br/NFCom\" versao=\"1.00\">"
                . "<tpAmb>{$tpAmb}</tpAmb>"
                . "<cUF>35</cUF>"
                . "<xServ>STATUS</xServ>"
                . "</consStatServNFCom>";

            $compactado = $tools->compactarXML($xml);
            $resposta = $tools->enviarSOAP($compactado, 'NFComStatusServico');

            $response->getBody()->write(json_encode([
                'status' => 'sucesso',
                'resposta' => $resposta,
                'xml_enviado' => $xml,
                'xml_compactado' => $compactado
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    });

})->add($jwtMiddleware);

$app->post('/certificado/upload', function (Request $request, Response $response) {
    $uploadedFiles = $request->getUploadedFiles();
    $cnpj = $request->getParsedBody()['cnpj'] ?? null;

    if (!isset($uploadedFiles['certificado']) || !$cnpj) {
        $response->getBody()->write(json_encode(['error' => 'Arquivo e CNPJ obrigatórios']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $cert = $uploadedFiles['certificado'];
    $dir = __DIR__ . "/storage/certificados/$cnpj";
    if ($cert->getError() === UPLOAD_ERR_OK) {
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        if (file_exists("$dir/cert.pfx")) {
            unlink("$dir/cert.pfx");
        }
        $cert->moveTo("$dir/cert.pfx");
        $response->getBody()->write(json_encode(['status' => 'sucesso', 'mensagem' => 'Certificado salvo com sucesso']));
    } else {
        $response->getBody()->write(json_encode(['error' => 'Erro ao fazer upload do certificado']));
    }

    return $response->withHeader('Content-Type', 'application/json');
})->add($jwtMiddleware);

$app->post('/certificado/teste', function (Request $request, Response $response) {
    $dados = json_decode($request->getBody()->getContents(), true);
    
    try {
        $cnpj = $dados['cnpj'] ?? '';
        $senha = $dados['senha'] ?? '';
        
        if (!$cnpj || !$senha) {
            throw new Exception('CNPJ e senha são obrigatórios.');
        }

        $certPath = __DIR__ . "/storage/certificados/{$cnpj}/cert.pfx";
        
        if (!file_exists($certPath)) {
            throw new Exception("Certificado não encontrado para o CNPJ: {$cnpj}");
        }

        $pfxContent = file_get_contents($certPath);
        $certs = [];
        
        if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {

            throw new Exception("(index)Não foi possível ler o certificado PFX. Verifique a senha.");
        }

        $certInfo = openssl_x509_parse($certs['cert']);
        
        $response->getBody()->write(json_encode([
            'status' => 'sucesso',
            'certificado' => [
                'valido' => time() >= $certInfo['validFrom_time_t'] && time() <= $certInfo['validTo_time_t'],
                'validade_inicio' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t']),
                'validade_fim' => date('Y-m-d H:i:s', $certInfo['validTo_time_t']),
                'cnpj' => $certInfo['subject']['CN'] ?? null,
                'emissor' => $certInfo['issuer']['O'] ?? null
            ]
        ]));
        
    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'status' => 'erro',
            'mensagem' => $e->getMessage()
        ]));
        return $response->withStatus(400);
    }

    return $response->withHeader('Content-Type', 'application/json');
})->add($jwtMiddleware);

$app->run();
