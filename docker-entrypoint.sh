#!/bin/bash
set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}[Moodle Container] Starting initialization...${NC}"

# Verificar se config.php já existe
if [ ! -f /var/www/html/config.php ]; then
    echo -e "${YELLOW}[Moodle Container] config.php not found, creating from environment variables...${NC}"
    
    # Criar config.php a partir das variáveis de ambiente
    cat > /var/www/html/config.php << 'EOF'
<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = getenv('MOODLE_DB_TYPE') ?: 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('MOODLE_DB_HOST') ?: 'localhost';
$CFG->dbname    = getenv('MOODLE_DB_NAME') ?: 'moodle';
$CFG->dbuser    = getenv('MOODLE_DB_USER') ?: 'moodle';
$CFG->dbpass    = getenv('MOODLE_DB_PASS') ?: '';
$CFG->prefix    = getenv('MOODLE_DB_PREFIX') ?: 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => getenv('MOODLE_DB_PORT') ?: '',
  'dbsocket' => getenv('MOODLE_DB_SOCKET') ?: '',
  'dbcollation' => 'utf8mb4_unicode_ci',
);

$CFG->wwwroot   = getenv('MOODLE_URL') ?: 'http://localhost';
$CFG->dataroot  = '/var/www/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!

$CFG->alternateloginurl = getenv('MOODLE_ALTERNATE_LOGIN_URL') ?: '';
EOF

    echo -e "${GREEN}[Moodle Container] config.php created successfully${NC}"
else
    echo -e "${GREEN}[Moodle Container] config.php already exists${NC}"
fi

echo -e "${YELLOW}[Moodle Container] Setting permissions...${NC}"

# Ajustar permissões
chown -R www-data:www-data /var/www/html
chown -R www-data:www-data /var/www/moodledata

echo -e "${GREEN}[Moodle Container] Permissions set${NC}"

echo -e "${YELLOW}[Moodle Container] Checking moodledata directory...${NC}"

# Verificar se moodledata tem as pastas necessárias
if [ ! -d /var/www/moodledata/cache ]; then
    echo -e "${YELLOW}[Moodle Container] Creating moodledata structure...${NC}"
    mkdir -p /var/www/moodledata/{cache,localcache,sessions,temp,trashdir}
    chown -R www-data:www-data /var/www/moodledata
fi

echo -e "${GREEN}[Moodle Container] moodledata directory is ready${NC}"

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
