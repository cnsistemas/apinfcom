# API NFCom - Coleção Postman

Esta coleção do Postman contém todas as requisições necessárias para testar a API de emissão de NFCom em PHP.

## 📋 Pré-requisitos

1. **Servidor PHP rodando**: Certifique-se de que sua API está rodando (ex: `php -S localhost:8000`)
2. **Certificado Digital**: Você precisará de um certificado digital PFX válido para emissão de NFCom
3. **Postman**: Instale o Postman para importar e usar esta coleção

## 🔧 Configuração Inicial

### 1. Importar a Coleção
1. Abra o Postman
2. Clique em "Import"
3. Selecione o arquivo `NFCom_API_Postman_Collection.json`
4. A coleção será importada com todas as requisições

### 2. Configurar Variáveis de Ambiente
A coleção usa as seguintes variáveis que você deve configurar:

| Variável | Descrição | Valor Padrão |
|----------|-----------|---------------|
| `base_url` | URL base da API | `http://localhost:8000` |
| `token` | Token JWT (será preenchido automaticamente) | - |
| `chave_nfcom` | Chave da NFCom emitida | - |
| `protocolo_nfcom` | Protocolo da NFCom | - |

## 🔐 Autenticação

### Login
**Endpoint**: `POST /login`

**Credenciais padrão**:
- Usuário: `admin`
- Senha: `123456`

**Exemplo de requisição**:
```json
{
    "usuario": "admin",
    "senha": "123456"
}
```

**Resposta esperada**:
```json
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}
```

⚠️ **Importante**: Após fazer login, copie o token da resposta e configure a variável `token` na coleção.

## 📄 Endpoints NFCom

### 1. Emissão de NFCom
**Endpoint**: `POST /nfcom`

**Dados necessários**:
```json
{
    "cnpj_emitente": "41151201000177",
    "senha": "123456",
    "nome": "EMPRESA TESTE LTDA",
    "rgie": "ISENTO",
    "endereco": "Rua das Flores",
    "endereco_numero": "123",
    "bairro": "Centro",
    "cidade": "São Paulo",
    "uf": "SP",
    "cep": "01234-567",
    "telefone": "(11) 1234-5678",
    "cpfcnpj": "12345678901",
    "codigobarras": "12345678901234567890",
    "itens": [
        {
            "item": "001",
            "descricao": "Serviço de Telecomunicações",
            "cclass": "1.01",
            "cfop": "1.101",
            "quantidade": 1.00,
            "unitario": 100.00,
            "total": 100.00,
            "bc_icms": 100.00
        }
    ]
}
```

### 2. Consulta de NFCom
**Endpoint**: `GET /nfcom/{chave}`

**Parâmetros de query**:
- `cnpj`: CNPJ do emitente
- `senha`: Senha do certificado
- `ambiente`: Ambiente (homologacao/producao)

### 3. Cancelamento de NFCom
**Endpoint**: `POST /nfcom/cancelar`

**Dados necessários**:
```json
{
    "cnpj": "41151201000177",
    "senha": "123456",
    "chave": "{{chave_nfcom}}",
    "protocolo": "{{protocolo_nfcom}}",
    "justificativa": "Erro na emissão",
    "ambiente": "homologacao",
    "cOrgao": "35"
}
```

### 4. Status do Serviço
**Endpoint**: `POST /nfcom/status`

**Dados necessários**:
```json
{
    "cnpj": "41151201000177",
    "senha": "123456"
}
```

## 🔑 Certificados Digitais

### 1. Upload de Certificado
**Endpoint**: `POST /certificado/upload`

**Formato**: `multipart/form-data`

**Campos**:
- `certificado`: Arquivo PFX do certificado digital
- `cnpj`: CNPJ do emitente

### 2. Teste de Certificado
**Endpoint**: `POST /certificado/teste`

**Dados necessários**:
```json
{
    "cnpj": "41151201000177",
    "senha": "123456"
}
```

## 🚀 Fluxo de Teste Recomendado

1. **Configurar certificado**:
   - Faça upload do certificado digital
   - Teste o certificado

2. **Autenticação**:
   - Execute o endpoint de login
   - Configure o token na variável `token`

3. **Verificar status do serviço**:
   - Teste o status do serviço NFCom

4. **Emissão**:
   - Emita uma NFCom
   - Guarde a chave e protocolo retornados

5. **Consulta**:
   - Consulte a NFCom emitida

6. **Cancelamento** (opcional):
   - Cancele a NFCom se necessário

## 📝 Dados de Teste

### CNPJ de Teste
- **CNPJ**: `41151201000177`
- **Senha**: `123456`

### Dados da Empresa
- **Nome**: EMPRESA TESTE LTDA
- **IE**: ISENTO
- **Endereço**: Rua das Flores, 123, Centro, São Paulo/SP
- **CEP**: 01234-567
- **Telefone**: (11) 1234-5678

### Itens de Teste
- **Descrição**: Serviço de Telecomunicações
- **Código**: 001
- **CFOP**: 1.101
- **Valor**: R$ 100,00

## ⚠️ Observações Importantes

1. **Ambiente**: Por padrão, a API está configurada para ambiente de homologação
2. **Certificado**: Certifique-se de que o certificado digital é válido e tem permissão para NFCom
3. **Token**: O token JWT expira em 1 hora, faça login novamente se necessário
4. **Chaves**: As chaves de NFCom são geradas automaticamente pela API
5. **Protocolos**: Os protocolos são retornados pela SEFAZ após autorização

## 🔧 Troubleshooting

### Erro 401 - Unauthorized
- Verifique se o token JWT está configurado corretamente
- Faça login novamente se o token expirou

### Erro 400 - Bad Request
- Verifique se todos os campos obrigatórios estão preenchidos
- Confirme se o JSON está bem formatado

### Erro de Certificado
- Verifique se o certificado PFX está correto
- Confirme se a senha do certificado está correta
- Certifique-se de que o certificado tem permissão para NFCom

### Erro de Conexão
- Verifique se o servidor PHP está rodando
- Confirme se a URL base está correta
- Teste se a porta está acessível

## 📞 Suporte

Para dúvidas ou problemas:
1. Verifique os logs do servidor PHP
2. Confirme se todas as dependências estão instaladas
3. Teste os endpoints individualmente
4. Verifique se o certificado digital está válido 