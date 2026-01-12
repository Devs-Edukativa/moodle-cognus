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
    
    # Definir valores padrão se variáveis não estiverem definidas
    DB_TYPE="${MOODLE_DB_TYPE:-mysqli}"
    DB_HOST="${MOODLE_DB_HOST:-localhost}"
    DB_NAME="${MOODLE_DB_NAME:-moodle}"
    DB_USER="${MOODLE_DB_USER:-moodle}"
    DB_PASS="${MOODLE_DB_PASS:-}"
    DB_PREFIX="${MOODLE_DB_PREFIX:-mdl_}"
    DB_PORT="${MOODLE_DB_PORT:-}"
    DB_SOCKET="${MOODLE_DB_SOCKET:-}"
    WWWROOT="${MOODLE_URL:-http://localhost}"
    ALT_LOGIN="${MOODLE_ALTERNATE_LOGIN_URL:-}"
    
    echo -e "${YELLOW}[Moodle Container] Database config: ${DB_USER}@${DB_HOST}/${DB_NAME}${NC}"
    
    # Criar config.php com valores reais das variáveis de ambiente
    cat > /var/www/html/config.php << EOF
<?php  // Moodle configuration file

unset(\$CFG);
global \$CFG;
\$CFG = new stdClass();

\$CFG->dbtype    = '${DB_TYPE}';
\$CFG->dblibrary = 'native';
\$CFG->dbhost    = '${DB_HOST}';
\$CFG->dbname    = '${DB_NAME}';
\$CFG->dbuser    = '${DB_USER}';
\$CFG->dbpass    = '${DB_PASS}';
\$CFG->prefix    = '${DB_PREFIX}';
\$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '${DB_PORT}',
  'dbsocket' => '${DB_SOCKET}',
  'dbcollation' => 'utf8mb4_unicode_ci',
);

\$CFG->wwwroot   = '${WWWROOT}';
\$CFG->dataroot  = '/var/www/moodledata';
\$CFG->admin     = 'admin';

\$CFG->directorypermissions = 0777;

require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
EOF

    if [ -n "${ALT_LOGIN}" ]; then
        echo "\$CFG->alternateloginurl = '${ALT_LOGIN}';" >> /var/www/html/config.php
    fi

    echo -e "${GREEN}[Moodle Container] config.php created successfully${NC}"
    echo -e "${GREEN}[Moodle Container] Config: ${DB_USER}@${DB_HOST}/${DB_NAME}${NC}"
else
    echo -e "${GREEN}[Moodle Container] config.php already exists${NC}"
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
