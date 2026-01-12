# Troubleshooting - Conexão do Container com MySQL

## Problema: Container não consegue conectar ao MySQL

### Solução Implementada: `--network host`

Mudamos para usar `--network host`, que faz o container compartilhar a rede do host. Com isso:

- ✅ O container pode usar `localhost` para acessar o MySQL
- ✅ Não precisa expor portas com `-p`
- ✅ O Apache usa a porta configurada (8008) diretamente no host

### Testando a Conexão

```bash
# 1. Parar container antigo
sudo docker stop moodle-cognus
sudo docker rm moodle-cognus

# 2. Rebuild (se necessário)
sudo docker build -t edukativa/moodle-cognus:latest .

# 3. Rodar com network host
sudo docker run -d \
    --name moodle-cognus \
    --restart unless-stopped \
    --network host \
    -v /var/www/cognus.edukativa.com.br/moodle-data:/var/www/moodledata \
    -e MOODLE_DB_TYPE=mysqli \
    -e MOODLE_DB_HOST=localhost \
    -e MOODLE_DB_NAME=cognus \
    -e MOODLE_DB_USER=cognus \
    -e MOODLE_DB_PASS='Avanteb.2025' \
    -e MOODLE_DB_PREFIX=mdl_ \
    -e MOODLE_URL=https://cognus.edukativa.com.br \
    -e PORT=8008 \
    edukativa/moodle-cognus:latest

# 4. Verificar logs
sudo docker logs -f moodle-cognus

# 5. Testar conexão MySQL de dentro do container
sudo docker exec -it moodle-cognus mysql -h localhost -u cognus -p cognus
# Senha: Avanteb.2025
```

### Verificações

#### 1. MySQL está aceitando conexões locais?

```bash
# Verificar se MySQL está rodando
sudo systemctl status mysql

# Verificar se está escutando em localhost
sudo netstat -tlnp | grep 3306
# Deve mostrar: 127.0.0.1:3306 ou 0.0.0.0:3306

# Testar conexão local
mysql -h localhost -u cognus -p cognus
```

#### 2. Usuário MySQL tem permissões?

```bash
sudo mysql -u root -p

-- Verificar usuário
SELECT User, Host FROM mysql.user WHERE User='cognus';

-- Se necessário, criar/atualizar permissões
GRANT ALL PRIVILEGES ON cognus.* TO 'cognus'@'localhost' IDENTIFIED BY 'Avanteb.2025';
FLUSH PRIVILEGES;
EXIT;
```

#### 3. Container consegue resolver localhost?

```bash
# Entrar no container
sudo docker exec -it moodle-cognus bash

# Testar conectividade
ping localhost
telnet localhost 3306

# Testar com PHP
php -r "\$c = new mysqli('localhost', 'cognus', 'Avanteb.2025', 'cognus'); if(\$c->connect_error) { echo 'Error: ' . \$c->connect_error; } else { echo 'Connected successfully!'; }"
```

### Alternativas se `--network host` não funcionar

#### Opção 1: Usar Socket Unix

```bash
sudo docker run -d \
    --name moodle-cognus \
    --restart unless-stopped \
    -p 8008:80 \
    -v /var/www/cognus.edukativa.com.br/moodle-data:/var/www/moodledata \
    -v /var/run/mysqld/mysqld.sock:/var/run/mysqld/mysqld.sock \
    -e MOODLE_DB_TYPE=mysqli \
    -e MOODLE_DB_HOST=localhost \
    -e MOODLE_DB_SOCKET=/var/run/mysqld/mysqld.sock \
    -e MOODLE_DB_NAME=cognus \
    -e MOODLE_DB_USER=cognus \
    -e MOODLE_DB_PASS='Avanteb.2025' \
    -e MOODLE_DB_PREFIX=mdl_ \
    -e MOODLE_URL=https://cognus.edukativa.com.br \
    edukativa/moodle-cognus:latest
```

Atualizar docker-entrypoint.sh para usar socket:

```php
'dbsocket' => getenv('MOODLE_DB_SOCKET') ?: '',
```

#### Opção 2: Usar IP Real do Servidor

```bash
# Descobrir IP do servidor
hostname -I | awk '{print $1}'

# Usar esse IP em vez de localhost
-e MOODLE_DB_HOST=192.168.1.XXX
```

E permitir no MySQL:

```sql
GRANT ALL PRIVILEGES ON cognus.* TO 'cognus'@'192.168.1.XXX' IDENTIFIED BY 'Avanteb.2025';
FLUSH PRIVILEGES;
```

#### Opção 3: Usar Gateway do Docker (se não usar --network host)

```bash
# Descobrir gateway
docker network inspect bridge | grep Gateway
# Normalmente: 172.17.0.1

-e MOODLE_DB_HOST=172.17.0.1
```

E permitir no MySQL:

```sql
GRANT ALL PRIVILEGES ON cognus.* TO 'cognus'@'172.17.%' IDENTIFIED BY 'Avanteb.2025';
FLUSH PRIVILEGES;

# Configurar MySQL para aceitar conexões de rede
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
# Alterar: bind-address = 0.0.0.0
sudo systemctl restart mysql
```

### Erros Comuns

#### "Can't connect to MySQL server on 'localhost'"

**Causa:** MySQL não está rodando ou não está escutando em localhost

**Solução:**

```bash
sudo systemctl status mysql
sudo systemctl start mysql
```

#### "Access denied for user 'cognus'@'localhost'"

**Causa:** Senha incorreta ou usuário não tem permissões

**Solução:**

```bash
sudo mysql -u root -p
GRANT ALL PRIVILEGES ON cognus.* TO 'cognus'@'localhost' IDENTIFIED BY 'Avanteb.2025';
FLUSH PRIVILEGES;
```

#### "Unknown database 'cognus'"

**Causa:** Banco de dados não existe

**Solução:**

```bash
sudo mysql -u root -p
CREATE DATABASE cognus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Vantagens do --network host

✅ **Pros:**

- Simplicidade - usa localhost diretamente
- Performance - sem overhead de NAT
- Acesso a todos os serviços do host

⚠️ **Contras:**

- Menos isolamento
- Container usa portas do host diretamente
- Pode ter conflitos de porta

### Logs Úteis

```bash
# Logs do container
sudo docker logs moodle-cognus --tail 100

# Logs do MySQL
sudo tail -f /var/log/mysql/error.log

# Verificar processos
sudo docker exec moodle-cognus ps aux

# Ver variáveis de ambiente
sudo docker exec moodle-cognus env | grep MOODLE
```

### Verificação Final

Após iniciar o container, teste:

```bash
# 1. Container está rodando?
sudo docker ps | grep moodle-cognus

# 2. Apache está respondendo?
curl -I http://localhost:8008

# 3. MySQL conecta?
sudo docker exec moodle-cognus mysql -h localhost -u cognus -pcognus -e "SHOW DATABASES;"

# 4. Config.php foi criado?
sudo docker exec moodle-cognus cat /var/www/html/config.php | grep dbhost

# 5. Logs sem erros?
sudo docker logs moodle-cognus --tail 50
```

Se tudo estiver OK, você verá o Moodle carregando em: `https://cognus.edukativa.com.br`
