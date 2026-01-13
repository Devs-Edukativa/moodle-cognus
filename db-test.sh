#!/bin/bash
# Script de diagnóstico de conexão com banco de dados

echo "======================================"
echo "DIAGNÓSTICO DE CONEXÃO COM BANCO DE DADOS"
echo "======================================"
echo ""

# Informações do config.php
DB_HOST="172.31.27.76"
DB_NAME="cognus"
DB_USER="cognus"
DB_PASS="Avanteb.2025"
DB_PORT="3306"

echo "Configurações:"
echo "  Host: $DB_HOST"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"
echo "  Port: $DB_PORT"
echo ""

# Teste 1: Verificar se o MySQL está acessível
echo "1. Testando conectividade com o host..."
if command -v nc &> /dev/null; then
    if nc -zv $DB_HOST $DB_PORT 2>&1 | grep -q succeeded; then
        echo "   ✓ Porta $DB_PORT está acessível em $DB_HOST"
    else
        echo "   ✗ Não foi possível conectar na porta $DB_PORT em $DB_HOST"
        echo "   PROBLEMA: O banco de dados não está acessível neste host/porta"
    fi
else
    echo "   ⚠ netcat não disponível para testar conectividade"
fi
echo ""

# Teste 2: Verificar se mysql client está disponível
echo "2. Verificando cliente MySQL..."
if command -v mysql &> /dev/null; then
    echo "   ✓ Cliente MySQL encontrado"
    
    # Teste 3: Tentar conectar ao banco
    echo ""
    echo "3. Tentando conectar ao banco de dados..."
    if mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1;" 2>&1 | grep -q "1"; then
        echo "   ✓ Conexão bem-sucedida!"
        echo ""
        echo "4. Informações do servidor MySQL:"
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "SELECT VERSION();"
    else
        echo "   ✗ Falha na conexão"
        echo ""
        echo "   Tentando conectar e mostrar erro:"
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1;" 2>&1
    fi
else
    echo "   ✗ Cliente MySQL não encontrado no container"
    echo "   Instale com: apt-get update && apt-get install -y default-mysql-client"
fi
echo ""

# Teste 4: Verificar extensão mysqli do PHP
echo "5. Verificando extensão mysqli do PHP..."
php -r "if (extension_loaded('mysqli')) { echo '   ✓ Extensão mysqli carregada\n'; } else { echo '   ✗ Extensão mysqli NÃO está carregada\n'; }"
echo ""

# Teste 5: Teste de conexão via PHP
echo "6. Testando conexão via PHP mysqli..."
php -r "
\$conn = @new mysqli('$DB_HOST', '$DB_USER', '$DB_PASS', '$DB_NAME', $DB_PORT);
if (\$conn->connect_error) {
    echo '   ✗ Erro de conexão: ' . \$conn->connect_error . \"\n\";
    echo '   Código do erro: ' . \$conn->connect_errno . \"\n\";
} else {
    echo '   ✓ Conexão PHP mysqli bem-sucedida!\n';
    echo '   Versão do servidor: ' . \$conn->server_info . \"\n\";
    \$conn->close();
}
"
echo ""

# Teste 6: Verificar se o diretório moodledata existe e tem permissões
echo "7. Verificando diretório moodledata..."
DATAROOT="/var/www/cognus.edukativa.com.br/moodle-data"
if [ -d "$DATAROOT" ]; then
    echo "   ✓ Diretório $DATAROOT existe"
    ls -ld "$DATAROOT"
    if [ -w "$DATAROOT" ]; then
        echo "   ✓ Diretório tem permissão de escrita"
    else
        echo "   ✗ Diretório NÃO tem permissão de escrita"
    fi
else
    echo "   ✗ Diretório $DATAROOT NÃO existe"
fi
echo ""

echo "======================================"
echo "FIM DO DIAGNÓSTICO"
echo "======================================"
