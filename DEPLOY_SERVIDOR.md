# 🚀 Deploy da API NFCom em Servidor

## 📋 Opções de Servidor

### 1. **VPS/Servidor Dedicado** (Recomendado)
- **Ubuntu 20.04/22.04**
- **Apache** ou **Nginx**
- **PHP 8.1+**
- **SSL/HTTPS obrigatório**

### 2. **Hospedagem Compartilhada**
- Limitada para certificados digitais
- Pode não ter todas as extensões PHP

### 3. **Docker** (Containerização)
- Mais portabilidade
- Isolamento de ambiente

### 4. **Cloud (AWS, Google Cloud, Azure)**
- Escalabilidade
- Alta disponibilidade

## 🔧 Configuração VPS Ubuntu (Recomendado)

### 1. Preparar o Servidor

```bash
# Atualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar PHP 8.1 e extensões
sudo apt install -y php8.1 php8.1-fpm php8.1-cli php8.1-common \
php8.1-mysql php8.1-zip php8.1-gd php8.1-mbstring php8.1-curl \
php8.1-xml php8.1-bcmath php8.1-soap php8.1-openssl

# Instalar Apache
sudo apt install -y apache2

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar Git
sudo apt install -y git unzip
```

### 2. Configurar Apache

```bash
# Habilitar módulos necessários
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers

# Criar VirtualHost
sudo nano /etc/apache2/sites-available/nfcom-api.conf
```

**Arquivo VirtualHost**:
```apache
<VirtualHost *:80>
    ServerName api.seudominio.com
    DocumentRoot /var/www/nfcom-api
    
    # Redirecionar para HTTPS
    Redirect permanent / https://api.seudominio.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName api.seudominio.com
    DocumentRoot /var/www/nfcom-api
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/ssl-cert-snakeoil.pem
    SSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key
    
    # PHP Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.1-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Rewrite Rules
    <Directory /var/www/nfcom-api>
        AllowOverride All
        Require all granted
        
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>
    
    # Security Headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    
    # Logs
    ErrorLog ${APACHE_LOG_DIR}/nfcom-api-error.log
    CustomLog ${APACHE_LOG_DIR}/nfcom-api-access.log combined
</VirtualHost>
```

```bash
# Ativar site
sudo a2ensite nfcom-api.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2
```

### 3. Fazer Deploy do Código

```bash
# Criar diretório
sudo mkdir -p /var/www/nfcom-api
sudo chown $USER:www-data /var/www/nfcom-api

# Clonar/copiar projeto
cd /var/www/nfcom-api
git clone https://github.com/seu-usuario/apinfcomphp.git .

# Ou copiar via SCP
# scp -r /caminho/local/apinfcomphp/* usuario@servidor:/var/www/nfcom-api/

# Instalar dependências
composer install --no-dev --optimize-autoloader

# Configurar permissões
sudo chown -R www-data:www-data /var/www/nfcom-api
sudo chmod -R 755 /var/www/nfcom-api
sudo chmod -R 775 /var/www/nfcom-api/storage
```

### 4. Configurar SSL com Let's Encrypt

```bash
# Instalar Certbot
sudo apt install -y certbot python3-certbot-apache

# Obter certificado SSL
sudo certbot --apache -d api.seudominio.com

# Renovação automática
sudo crontab -e
# Adicionar linha:
# 0 12 * * * /usr/bin/certbot renew --quiet
```

### 5. Configurações de Segurança

**Arquivo `.env`** (criar na raiz):
```env
# Ambiente
APP_ENV=production
APP_DEBUG=false

# JWT
JWT_SECRET=sua_chave_jwt_super_secreta_aqui

# NFCom
NFCOM_AMBIENTE=producao
NFCOM_URL_PRODUCAO=https://nfcom.sefaz.rs.gov.br/ws/nfcomws/NfcomRecepcao
NFCOM_URL_HOMOLOGACAO=https://nfcom-homologacao.sefaz.rs.gov.br/ws/nfcomws/NfcomRecepcao

# Database (se necessário)
DB_HOST=localhost
DB_NAME=nfcom_api
DB_USER=nfcom_user
DB_PASS=senha_segura
```

**Arquivo `.htaccess`** (na raiz):
```apache
RewriteEngine On

# Forçar HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# API Routes
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.json">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.lock">
    Order allow,deny
    Deny from all
</Files>
```

### 6. Configurar PHP para Produção

```bash
sudo nano /etc/php/8.1/fpm/php.ini
```

**Configurações importantes**:
```ini
# Segurança
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

# Performance
memory_limit = 256M
max_execution_time = 60
max_input_time = 60
post_max_size = 10M
upload_max_filesize = 5M

# OpenSSL
openssl.cafile = /etc/ssl/certs/ca-certificates.crt
```

```bash
# Criar diretório de logs
sudo mkdir -p /var/log/php
sudo chown www-data:www-data /var/log/php

# Reiniciar PHP-FPM
sudo systemctl restart php8.1-fpm
sudo systemctl restart apache2
```

## 🐳 Deploy com Docker

### 1. Dockerfile

```dockerfile
FROM php:8.1-apache

# Instalar extensões
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    libssl-dev \
    && docker-php-ext-install zip pdo_mysql bcmath soap \
    && docker-php-ext-enable openssl

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar Apache
RUN a2enmod rewrite ssl headers
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Copiar código
WORKDIR /var/www/html
COPY . .

# Instalar dependências
RUN composer install --no-dev --optimize-autoloader

# Permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 storage

EXPOSE 80 443
```

### 2. docker-compose.yml

```yaml
version: '3.8'

services:
  nfcom-api:
    build: .
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./storage:/var/www/html/storage
      - ./ssl:/etc/ssl/private
    environment:
      - APP_ENV=production
      - JWT_SECRET=sua_chave_secreta
    restart: unless-stopped

  nginx-proxy:
    image: jwilder/nginx-proxy
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/tmp/docker.sock:ro
      - ./ssl:/etc/nginx/certs
    environment:
      - VIRTUAL_HOST=api.seudominio.com
      - LETSENCRYPT_HOST=api.seudominio.com
    restart: unless-stopped
```

## ☁️ Deploy na AWS (EC2)

### 1. Criar Instância EC2

```bash
# Conectar via SSH
ssh -i sua-chave.pem ubuntu@ip-do-servidor

# Seguir passos do Ubuntu acima
```

### 2. Configurar Load Balancer

```bash
# Criar Application Load Balancer
# Configurar Target Groups
# Configurar SSL Certificate (ACM)
```

### 3. Configurar RDS (opcional)

```bash
# Criar instância MySQL/PostgreSQL
# Configurar Security Groups
# Atualizar variáveis de ambiente
```

## 🔒 Segurança de Certificados

### 1. Proteger Diretório de Certificados

```bash
# Criar diretório seguro
sudo mkdir -p /var/certificates/nfcom
sudo chown www-data:www-data /var/certificates/nfcom
sudo chmod 700 /var/certificates/nfcom

# Atualizar caminho no código
# /var/certificates/nfcom/{cnpj}/cert.pfx
```

### 2. Backup de Certificados

```bash
# Script de backup
#!/bin/bash
tar -czf /backup/certificates-$(date +%Y%m%d).tar.gz /var/certificates/nfcom
aws s3 cp /backup/certificates-$(date +%Y%m%d).tar.gz s3://seu-bucket/backups/
```

## 📊 Monitoramento

### 1. Logs

```bash
# Logs da aplicação
tail -f /var/log/apache2/nfcom-api-error.log

# Logs do PHP
tail -f /var/log/php/error.log

# Logs do sistema
tail -f /var/log/syslog
```

### 2. Monitoramento com Supervisor

```bash
sudo apt install supervisor

# /etc/supervisor/conf.d/nfcom-api.conf
[program:nfcom-api]
command=/usr/bin/php -S 0.0.0.0:8000 -t /var/www/nfcom-api
directory=/var/www/nfcom-api
autostart=true
autorestart=true
user=www-data
```

## 🚀 Checklist de Deploy

- [ ] Servidor configurado (Ubuntu/Apache/PHP)
- [ ] SSL/HTTPS configurado
- [ ] Código deployado
- [ ] Dependências instaladas
- [ ] Permissões configuradas
- [ ] Variáveis de ambiente definidas
- [ ] Certificados digitais protegidos
- [ ] Backups configurados
- [ ] Monitoramento ativo
- [ ] Testes em produção

## 🔧 Comandos Úteis

```bash
# Verificar status
sudo systemctl status apache2
sudo systemctl status php8.1-fpm

# Reiniciar serviços
sudo systemctl restart apache2
sudo systemctl restart php8.1-fpm

# Ver logs em tempo real
tail -f /var/log/apache2/nfcom-api-error.log

# Testar configuração
sudo apache2ctl configtest

# Verificar SSL
openssl s_client -connect api.seudominio.com:443
```

## 💰 Custos Estimados

### VPS
- **Básico**: R$ 30-50/mês
- **Médio**: R$ 80-150/mês
- **Avançado**: R$ 200-400/mês

### Cloud AWS
- **t3.micro**: ~R$ 50/mês
- **t3.small**: ~R$ 100/mês
- **t3.medium**: ~R$ 200/mês

Sua API estará rodando em produção com segurança e performance! 🎉 