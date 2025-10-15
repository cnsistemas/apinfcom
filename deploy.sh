#!/bin/bash

# 🚀 Script de Deploy da API NFCom
# Uso: ./deploy.sh [ambiente] [dominio]
# Exemplo: ./deploy.sh production api.minhaempresa.com

set -e  # Sair se algum comando falhar

# Configurações
AMBIENTE=${1:-production}
DOMINIO=${2:-api.seudominio.com}
PROJETO_DIR="/var/www/nfcom-api"
BACKUP_DIR="/backup/nfcom-api"
LOG_FILE="/var/log/deploy-nfcom.log"

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Função de log
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" >> $LOG_FILE
}

error() {
    echo -e "${RED}[ERRO] $1${NC}"
    echo "[ERRO] $1" >> $LOG_FILE
    exit 1
}

warning() {
    echo -e "${YELLOW}[AVISO] $1${NC}"
    echo "[AVISO] $1" >> $LOG_FILE
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
    echo "[INFO] $1" >> $LOG_FILE
}

# Verificar se é root ou sudo
if [[ $EUID -ne 0 ]]; then
   error "Este script deve ser executado como root (sudo)"
fi

log "🚀 Iniciando deploy da API NFCom"
log "Ambiente: $AMBIENTE"
log "Domínio: $DOMINIO"

# 1. Verificar dependências
log "📋 Verificando dependências..."

# Verificar PHP
if ! command -v php &> /dev/null; then
    error "PHP não encontrado. Instale o PHP 8.1+"
fi

PHP_VERSION=$(php -v | head -n1 | cut -d" " -f2 | cut -d"." -f1,2)
if [[ $(echo "$PHP_VERSION < 8.1" | bc -l) -eq 1 ]]; then
    error "PHP 8.1+ é necessário. Versão atual: $PHP_VERSION"
fi

# Verificar Composer
if ! command -v composer &> /dev/null; then
    error "Composer não encontrado. Instale o Composer"
fi

# Verificar Apache
if ! systemctl is-active --quiet apache2; then
    error "Apache não está rodando. Instale e inicie o Apache"
fi

log "✅ Dependências verificadas"

# 2. Backup do projeto atual (se existir)
if [ -d "$PROJETO_DIR" ]; then
    log "💾 Criando backup do projeto atual..."
    mkdir -p $BACKUP_DIR
    BACKUP_NAME="backup-$(date +%Y%m%d-%H%M%S)"
    tar -czf "$BACKUP_DIR/$BACKUP_NAME.tar.gz" -C "$PROJETO_DIR" .
    log "✅ Backup criado: $BACKUP_DIR/$BACKUP_NAME.tar.gz"
fi

# 3. Preparar diretório do projeto
log "📁 Preparando diretório do projeto..."
mkdir -p $PROJETO_DIR
cd $PROJETO_DIR

# 4. Fazer deploy do código
log "📦 Fazendo deploy do código..."

# Se for via Git
if [ -d ".git" ]; then
    log "🔄 Atualizando via Git..."
    git pull origin main
else
    log "⬇️ Código deve ser copiado manualmente para $PROJETO_DIR"
    warning "Certifique-se de que os arquivos estão no diretório correto"
fi

# 5. Instalar dependências
log "📚 Instalando dependências..."
composer install --no-dev --optimize-autoloader --no-interaction

# 6. Configurar permissões
log "🔐 Configurando permissões..."
chown -R www-data:www-data $PROJETO_DIR
chmod -R 755 $PROJETO_DIR
chmod -R 775 $PROJETO_DIR/storage

# Criar diretórios necessários
mkdir -p $PROJETO_DIR/storage/certificados
mkdir -p $PROJETO_DIR/storage/logs
chown -R www-data:www-data $PROJETO_DIR/storage
chmod -R 775 $PROJETO_DIR/storage

# 7. Configurar arquivo .env
log "⚙️ Configurando variáveis de ambiente..."

ENV_FILE="$PROJETO_DIR/.env"
if [ ! -f "$ENV_FILE" ]; then
    log "📝 Criando arquivo .env..."
    cat > $ENV_FILE << EOF
# Ambiente
APP_ENV=$AMBIENTE
APP_DEBUG=false

# JWT
JWT_SECRET=$(openssl rand -base64 32)

# NFCom
NFCOM_AMBIENTE=$AMBIENTE
NFCOM_URL_PRODUCAO=https://nfcom.sefaz.rs.gov.br/ws/nfcomws/NfcomRecepcao
NFCOM_URL_HOMOLOGACAO=https://nfcom-homologacao.sefaz.rs.gov.br/ws/nfcomws/NfcomRecepcao

# Logs
LOG_LEVEL=info
LOG_PATH=/var/log/nfcom-api/
EOF
    
    chown www-data:www-data $ENV_FILE
    chmod 640 $ENV_FILE
    log "✅ Arquivo .env criado"
else
    info "Arquivo .env já existe, mantendo configurações atuais"
fi

# 8. Configurar Apache VirtualHost
log "🌐 Configurando Apache VirtualHost..."

VHOST_FILE="/etc/apache2/sites-available/nfcom-api.conf"
cat > $VHOST_FILE << EOF
<VirtualHost *:80>
    ServerName $DOMINIO
    DocumentRoot $PROJETO_DIR
    
    # Redirecionar para HTTPS
    Redirect permanent / https://$DOMINIO/
</VirtualHost>

<VirtualHost *:443>
    ServerName $DOMINIO
    DocumentRoot $PROJETO_DIR
    
    # SSL será configurado pelo Certbot
    
    # PHP Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.1-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Rewrite Rules
    <Directory $PROJETO_DIR>
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
    ErrorLog \${APACHE_LOG_DIR}/nfcom-api-error.log
    CustomLog \${APACHE_LOG_DIR}/nfcom-api-access.log combined
</VirtualHost>
EOF

# 9. Configurar .htaccess
log "📄 Configurando .htaccess..."
cat > "$PROJETO_DIR/.htaccess" << 'EOF'
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

<Files "*.md">
    Order allow,deny
    Deny from all
</Files>
EOF

# 10. Ativar site e módulos Apache
log "🔧 Configurando Apache..."

# Habilitar módulos
a2enmod rewrite ssl headers proxy_fcgi setenvif

# Ativar site
a2ensite nfcom-api.conf

# Desativar site padrão se ainda estiver ativo
if a2query -s 000-default > /dev/null 2>&1; then
    a2dissite 000-default.conf
fi

# Testar configuração
if apache2ctl configtest; then
    log "✅ Configuração do Apache válida"
else
    error "❌ Erro na configuração do Apache"
fi

# Recarregar Apache
systemctl reload apache2

# 11. Configurar SSL com Let's Encrypt
log "🔒 Configurando SSL..."

if command -v certbot &> /dev/null; then
    log "📜 Obtendo certificado SSL para $DOMINIO..."
    
    # Verificar se domínio está apontando para este servidor
    CURRENT_IP=$(curl -s ifconfig.me)
    DOMAIN_IP=$(dig +short $DOMINIO)
    
    if [ "$CURRENT_IP" = "$DOMAIN_IP" ]; then
        certbot --apache -d $DOMINIO --non-interactive --agree-tos --email admin@$DOMINIO
        log "✅ SSL configurado com sucesso"
    else
        warning "Domínio $DOMINIO não aponta para este servidor ($CURRENT_IP)"
        warning "Configure o DNS e execute: certbot --apache -d $DOMINIO"
    fi
else
    warning "Certbot não encontrado. Instale com: apt install certbot python3-certbot-apache"
fi

# 12. Configurar logs
log "📊 Configurando logs..."
mkdir -p /var/log/nfcom-api
chown www-data:www-data /var/log/nfcom-api
chmod 755 /var/log/nfcom-api

# 13. Configurar cron para renovação SSL
log "⏰ Configurando renovação automática SSL..."
(crontab -l 2>/dev/null; echo "0 12 * * * /usr/bin/certbot renew --quiet") | crontab -

# 14. Testes finais
log "🧪 Executando testes..."

# Testar se o servidor está respondendo
sleep 2
if curl -k -f -s "https://$DOMINIO/login" > /dev/null 2>&1; then
    log "✅ API respondendo corretamente"
else
    warning "API pode não estar respondendo. Verifique os logs"
fi

# 15. Informações finais
log "🎉 Deploy concluído com sucesso!"
echo ""
echo -e "${GREEN}=== INFORMAÇÕES DO DEPLOY ===${NC}"
echo -e "${BLUE}URL da API:${NC} https://$DOMINIO"
echo -e "${BLUE}Diretório:${NC} $PROJETO_DIR"
echo -e "${BLUE}Logs Apache:${NC} /var/log/apache2/nfcom-api-*.log"
echo -e "${BLUE}Logs PHP:${NC} /var/log/php/error.log"
echo -e "${BLUE}Certificados:${NC} $PROJETO_DIR/storage/certificados/"
echo ""
echo -e "${YELLOW}PRÓXIMOS PASSOS:${NC}"
echo "1. Teste a API: curl https://$DOMINIO/login"
echo "2. Configure seus certificados digitais"
echo "3. Atualize a variável JWT_SECRET no arquivo .env"
echo "4. Configure monitoramento e backups"
echo ""

# 16. Criar script de update
log "📝 Criando script de update..."
cat > "$PROJETO_DIR/update.sh" << 'EOF'
#!/bin/bash
# Script de atualização rápida
cd /var/www/nfcom-api
git pull origin main
composer install --no-dev --optimize-autoloader
sudo chown -R www-data:www-data .
sudo systemctl reload apache2
echo "✅ Atualização concluída"
EOF

chmod +x "$PROJETO_DIR/update.sh"

log "🎯 Deploy finalizado! API NFCom rodando em https://$DOMINIO" 