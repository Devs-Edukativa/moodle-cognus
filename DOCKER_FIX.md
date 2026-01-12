# Corrigindo o erro "exec format error"

## Diagnóstico

Execute estes comandos para verificar o problema:

```bash
# 1. Verificar arquitetura do servidor
uname -m
# Deve retornar: x86_64 (ou amd64)

# 2. Verificar arquitetura da imagem
sudo docker image inspect edukativa/moodle-cognus:latest | grep Architecture
# Deve mostrar: "Architecture": "amd64"

# 3. Verificar se buildx está disponível
docker buildx version
```

## Solução 1: Limpar cache e rebuild completo

```bash
# 1. Parar e remover container
sudo docker stop moodle-cognus 2>/dev/null || true
sudo docker rm moodle-cognus 2>/dev/null || true

# 2. Remover imagens antigas (CUIDADO: remove TODAS as imagens moodle-cognus)
sudo docker rmi edukativa/moodle-cognus:latest -f
sudo docker images | grep moodle-cognus | awk '{print $3}' | xargs -r sudo docker rmi -f

# 3. Limpar TUDO do cache do Docker
sudo docker builder prune -af
sudo docker system prune -af

# 4. Rebuild com --no-cache
cd /caminho/para/moodle-cognus
docker build --platform linux/amd64 --no-cache -t edukativa/moodle-cognus:latest .

# 5. Verificar a arquitetura da nova imagem
docker image inspect edukativa/moodle-cognus:latest | grep Architecture

# 6. Rodar o container
sudo docker run -d \
    --name moodle-cognus \
    --restart unless-stopped \
    -p 8008:80 \
    -v /var/www/cognus.edukativa.com.br/moodle-data:/var/www/moodledata \
    edukativa/moodle-cognus:latest

# 7. Verificar logs
sudo docker logs -f moodle-cognus
```

## Solução 2: Usar Docker Buildx (recomendado)

Se a Solução 1 não funcionar, use buildx:

```bash
# 1. Criar builder multi-plataforma
docker buildx create --name multiplatform --use
docker buildx inspect --bootstrap

# 2. Build com buildx
cd /caminho/para/moodle-cognus
docker buildx build \
    --platform linux/amd64 \
    --tag edukativa/moodle-cognus:latest \
    --load \
    .

# 3. Verificar
docker image inspect edukativa/moodle-cognus:latest | grep Architecture

# 4. Rodar container
sudo docker run -d \
    --name moodle-cognus \
    --restart unless-stopped \
    -p 8008:80 \
    -v /var/www/cognus.edukativa.com.br/moodle-data:/var/www/moodledata \
    edukativa/moodle-cognus:latest
```

## Solução 3: Build no próprio servidor (mais garantido)

Se você está fazendo build em uma máquina e rodando em outra:

```bash
# NO SERVIDOR onde vai rodar (edukativa-server):
cd /var/www/cognus.edukativa.com.br

# Clone o repo se ainda não tiver
git clone https://github.com/seu-usuario/moodle-cognus.git
cd moodle-cognus

# Build LOCALMENTE no servidor
sudo docker build --no-cache -t edukativa/moodle-cognus:latest .

# Rodar
sudo docker run -d \
    --name moodle-cognus \
    --restart unless-stopped \
    -p 8008:80 \
    -v /var/www/cognus.edukativa.com.br/moodle-data:/var/www/moodledata \
    edukativa/moodle-cognus:latest
```

## Verificação Final

```bash
# Container está rodando?
sudo docker ps | grep moodle-cognus

# Logs sem erros?
sudo docker logs moodle-cognus --tail 50

# Arquitetura correta?
sudo docker inspect moodle-cognus | grep -A 5 "Architecture"

# Teste HTTP
curl -I http://localhost:8008
```

## Se AINDA não funcionar

Pode ser um problema com arquivos binários no seu repositório. Verifique:

```bash
# Procurar por arquivos binários que podem estar causando problema
cd /caminho/para/moodle-cognus
find . -type f -executable -ls

# Se encontrar binários suspeitos, adicione ao .dockerignore:
cat >> .dockerignore << EOF
**/*.so
**/*.o
**/*.a
**/*.dylib
.git
.github
.vscode
*.log
EOF

# Rebuild
docker build --platform linux/amd64 --no-cache -t edukativa/moodle-cognus:latest .
```

## Atualizar Jenkinsfile para usar buildx

Se quiser que o Jenkins use buildx automaticamente:

```groovy
// No stage 'Build & Publish Docker Image'
sh """
    # Criar builder se não existir
    docker buildx create --name jenkins-builder --use 2>/dev/null || docker buildx use jenkins-builder
    
    # Build com buildx
    docker buildx build \
        --platform linux/amd64 \
        --tag ${DOCKER_IMAGE}:${imageTag} \
        --tag ${DOCKER_IMAGE}:latest \
        --push \
        .
"""
```

## Explicação do erro

O erro "exec format error" acontece quando:
- **Binário ARM tentando rodar em x86_64** (ou vice-versa)
- **Imagem construída em Mac M1/M2** (ARM) rodando em servidor Linux x86_64
- **Cache do Docker** mantendo a imagem antiga da arquitetura errada
- **Arquivo binário corrompido** no COPY do Dockerfile

A solução é garantir que a imagem seja construída especificamente para `linux/amd64`.
