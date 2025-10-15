# 🚀 Como Rodar a API NFCom

## 📋 Pré-requisitos

1. **PHP 7.4+** instalado
2. **Composer** instalado
3. **Certificado Digital PFX** válido para NFCom
4. **Extensões PHP** necessárias:
   - `openssl`
   - `curl`
   - `zip`
   - `xml`

## 🔧 Passo a Passo

### 1. Instalar Dependências

```bash
# Navegar para o diretório do projeto
cd /caminho/para/apinfcomphp

# Instalar dependências via Composer
composer install
```

### 2. Configurar Certificado Digital

```bash
# Criar diretório para certificados
mkdir -p storage/certificados/41151201000177

# Copiar seu certificado PFX para o diretório
# O arquivo deve se chamar: cert.pfx
cp /caminho/do/seu/certificado.pfx storage/certificados/41151201000177/cert.pfx
```

### 3. Rodar o Servidor PHP

#### Opção 1: Servidor de Desenvolvimento PHP
```bash
# Rodar na porta 8000
php -S localhost:8000

# Ou em uma porta específica
php -S 0.0.0.0:8080
```

#### Opção 2: Apache/Nginx
```bash
# Configurar o DocumentRoot para o diretório do projeto
# O arquivo index.php será executado automaticamente
```

### 4. Testar se está Funcionando

```bash
# Testar se o servidor está rodando
curl http://localhost:8000/login

# Deve retornar algo como:
# {"error":"Unauthorized: Token ausente"}
```

## 🔍 Verificar Instalação

### 1. Verificar Extensões PHP
```bash
php -m | grep -E "(openssl|curl|zip|xml)"
```

### 2. Verificar Composer
```bash
composer --version
```

### 3. Verificar Dependências
```bash
# Verificar se todas as dependências estão instaladas
composer show
```

## 🧪 Testes Rápidos

### 1. Teste de Login
```bash
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"usuario":"admin","senha":"123456"}'
```

### 2. Teste de Certificado
```bash
curl -X POST http://localhost:8000/certificado/teste \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -d '{"cnpj":"41151201000177","senha":"123456"}'
```

## ⚙️ Configurações Avançadas

### 1. Variáveis de Ambiente (Opcional)
Crie um arquivo `.env` na raiz do projeto:

```env
# Configurações da API
APP_ENV=development
APP_DEBUG=true

# Configurações JWT
JWT_SECRET=SEGREDO_SUPER_SEGURO

# Configurações NFCom
NFCOM_AMBIENTE=homologacao
NFCOM_CNPJ=41151201000177
NFCOM_SENHA=123456
```

### 2. Configurar Apache (Opcional)
Crie um arquivo `.htaccess` na raiz:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

### 3. Configurar Nginx (Opcional)
```nginx
server {
    listen 80;
    server_name localhost;
    root /caminho/para/apinfcomphp;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 🔧 Troubleshooting

### Erro: "Class not found"
```bash
# Regenerar autoloader
composer dump-autoload
```

### Erro: "Permission denied"
```bash
# Dar permissões ao diretório storage
chmod -R 755 storage/
```

### Erro: "Certificate not found"
```bash
# Verificar se o certificado existe
ls -la storage/certificados/41151201000177/
```

### Erro: "Port already in use"
```bash
# Usar outra porta
php -S localhost:8080
```

## 📱 Usando com Postman

1. **Importar a coleção** que criamos
2. **Configurar a URL base**: `http://localhost:8000`
3. **Fazer login** para obter o token JWT
4. **Testar os endpoints**

## 🚀 Comandos Rápidos

```bash
# Iniciar servidor (desenvolvimento)
php -S localhost:8000

# Verificar se está rodando
curl http://localhost:8000/login

# Parar servidor
# Ctrl+C no terminal onde está rodando
```

## 📊 Logs e Debug

### Ativar Logs de Erro
```php
// Adicionar no início do index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Verificar Logs do PHP
```bash
# Logs de erro do PHP
tail -f /var/log/php_errors.log

# Logs do servidor web
tail -f /var/log/apache2/error.log
```

## 🔒 Segurança

### Para Produção
1. **Alterar a chave JWT** no `index.php`
2. **Configurar HTTPS**
3. **Usar certificado válido**
4. **Configurar firewall**
5. **Usar variáveis de ambiente**

### Chave JWT
```php
// No index.php, linha 15
$key = 'SUA_CHAVE_SUPER_SECRETA_AQUI';
```

## ✅ Checklist de Verificação

- [ ] PHP 7.4+ instalado
- [ ] Composer instalado
- [ ] Dependências instaladas (`composer install`)
- [ ] Certificado PFX configurado
- [ ] Servidor rodando (`php -S localhost:8000`)
- [ ] Login funcionando
- [ ] Postman configurado
- [ ] Testes passando

## 🎯 Próximos Passos

1. **Testar login** via Postman
2. **Upload do certificado**
3. **Teste de emissão**
4. **Configurar dados reais**
5. **Testar em produção**

Agora sua API deve estar rodando em `http://localhost:8000`! 🎉 