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

echo -e "${GREEN}[Moodle Container] Initialization complete. Starting Apache...${NC}"

# Executar o comando original do Apache
exec apache2-foreground
