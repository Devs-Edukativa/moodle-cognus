#!/bin/bash
set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}[Moodle Container] Starting initialization...${NC}"

# Verificar se config.php existe
if [ ! -f /var/www/html/config.php ]; then
    echo -e "${RED}[Moodle Container] ERROR: config.php not found!${NC}"
    echo -e "${RED}[Moodle Container] Please ensure config.php is in /var/www/html/${NC}"
    exit 1
else
    echo -e "${GREEN}[Moodle Container] config.php found${NC}"
fi

# Verificar permissões do moodledata
if [ -d /var/www/cognus.edukativa.com.br/moodle-data ]; then
    echo -e "${GREEN}[Moodle Container] moodledata directory exists${NC}"
    chown -R www-data:www-data /var/www/cognus.edukativa.com.br/moodle-data
else
    echo -e "${YELLOW}[Moodle Container] Creating moodledata directory...${NC}"
    mkdir -p /var/www/cognus.edukativa.com.br/moodle-data
    chown -R www-data:www-data /var/www/cognus.edukativa.com.br/moodle-data
fi

echo -e "${GREEN}[Moodle Container] Initialization complete. Starting Apache...${NC}"

# Configurar porta do Apache
PORT=${PORT:-8008}
echo -e "${YELLOW}[Moodle Container] Configuring Apache to listen on port ${PORT}...${NC}"

# Atualizar configuração do Apache para usar apenas a porta especificada
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/Listen 443/#Listen 443/" /etc/apache2/ports.conf || true
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Desabilitar qualquer config SSL que possa existir
rm -f /etc/apache2/sites-enabled/default-ssl.conf || true

echo -e "${GREEN}[Moodle Container] Apache configured to listen on port ${PORT}${NC}"

# Executar o comando original do Apache
exec apache2-foreground
