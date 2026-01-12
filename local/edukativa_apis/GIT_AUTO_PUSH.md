# Auto-Commit de Plugins no Moodle

Este observer detecta quando um plugin é instalado ou atualizado no Moodle e automaticamente:

1. Faz commit das alterações no Git
2. Push para o GitHub (opcional)
3. Dispara build no Jenkins via webhook (opcional)

## Configuração

### 1. Edite o arquivo `observer/config.php`

```php
'git_auto_push' => [
    'enabled' => true,  // Habilita o auto-commit
    'auto_push' => true, // Habilita push automático para GitHub
    'branch' => 'main',  // Branch para fazer push
    'user_name' => 'Moodle Auto-Commit',
    'user_email' => 'moodle@cognus.edukativa.com.br',

    // GitHub Configuration (para repositórios privados)
    'github_token' => 'ghp_seu_token_aqui', // Personal Access Token do GitHub
    'github_repo_url' => 'github.com/usuario/moodle-cognus.git', // URL do repositório

    // Jenkins webhook (opcional)
    'jenkins_webhook' => 'http://seu-jenkins.com/generic-webhook-trigger/invoke?token=SEU_TOKEN',
],
```

### 2. Crie um Personal Access Token no GitHub

Para repositórios privados, você precisa de um token de autenticação:

1. Acesse: https://github.com/settings/tokens
2. Click em "Generate new token" > "Generate new token (classic)"
3. Dê um nome: "Moodle Auto-Commit"
4. Selecione o escopo: **repo** (Full control of private repositories)
5. Click em "Generate token"
6. Copie o token (ex: `ghp_xxxxxxxxxxxxxxxxxxxx`)
7. Cole no `config.php` em `github_token`

⚠️ **Importante:** Guarde o token em local seguro! Você não poderá vê-lo novamente.

### 3. Configure as credenciais Git (NÃO é mais necessário SSH key)

Com o token, o push será feito via HTTPS automaticamente. O observer configurará o remote temporariamente durante o push.

#### No Jenkins:

1. Instale o plugin "Generic Webhook Trigger"
2. No seu Jenkinsfile, adicione:

```groovy
triggers {
    GenericTrigger(
        genericVariables: [
            [key: 'ref', value: '$.ref']
        ],
        token: 'SEU_TOKEN_SECRETO',
        causeString: 'Triggered by Moodle plugin installation',
        printContributedVariables: true,
        printPostContent: true
    )
}
```

3. Use o webhook URL:

```
http://jenkins.your-domain.com/generic-webhook-trigger/invoke?token=SEU_TOKEN_SECRETO
```

### 4. Atualize o banco de dados do Moodle

Após adicionar os observers, execute:

```bash
# Via CLI
cd /var/www/cognus.edukativa.com.br
sudo -u www-data php admin/cli/upgrade.php

# Ou via Web
# Acesse: https://cognus.edukativa.com.br/admin/index.php
```

## Exemplo de Configuração Completa

```php
<?php
defined('MOODLE_INTERNAL') || die();

$config = [
    'backend_url' => 'https://backend.edukativa.com.br',
    'x_url' => 'cognus.edukativa.com.br',
    'x_wstoken' => 'seu_webservice_token',
    'mkey' => 'sua_mkey',
    'bearer_token' => 'seu_bearer_token',
    'sync_enabled' => true,
    'debug_mode' => false,

    'git_auto_push' => [
        'enabled' => true,
        'auto_push' => true,
        'branch' => 'main',
        'user_name' => 'Moodle Auto-Commit',
        'user_email' => 'moodle@cognus.edukativa.com.br',

        // GitHub Personal Access Token (Settings > Developer settings > Personal access tokens)
        'github_token' => 'ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'github_repo_url' => 'github.com/edukativa/moodle-cognus.git',

        // Jenkins webhook URL
        'jenkins_webhook' => 'http://jenkins.edukativa.com.br:8080/generic-webhook-trigger/invoke?token=moodle-cognus-auto-build',
    ],
];

return $config;
```

## Como funciona

Quando você instala ou atualiza um plugin via:

- Interface web (Site administration > Plugins > Install plugins)
- CLI (`php admin/cli/install_plugin.php`)
- Upload manual + upgrade

O observer irá:

1. ✅ Detectar o evento `plugin_installed` ou `plugin_updated`
2. ✅ Executar `git add .`
3. ✅ Criar commit com mensagem: `[auto-commit] Plugin installed: mod/quiz`
4. ✅ Fazer push para GitHub (se `auto_push = true`)
5. ✅ Disparar webhook do Jenkins (se configurado)
6. ✅ Jenkins executará o build da imagem Docker automaticamente

## Fluxo Completo

```
Instalar Plugin no Moodle
    ↓
Observer detecta evento
    ↓
Git commit automático
    ↓
Git push para GitHub
    ↓
Webhook dispara Jenkins
    ↓
Jenkins faz build da imagem
    ↓
Jenkins faz push para Docker Hub
    ↓
Jenkins faz deploy no servidor
```

## Logs

Para ver os logs do observer:

```bash
# Logs do Moodle
tail -f /var/www/cognus.edukativa.com.br/moodle-data/temp/cron.log

# Durante instalação de plugin via web, ative debug:
# Site administration > Development > Debugging > Developer level
```

## Troubleshooting

### Git push falha com "Permission denied" ou "Authentication failed"

- Verifique se o GitHub token está correto e não expirou
- Certifique-se de que o token tem permissão **repo** (full control)
- Teste o token manualmente:

```bash
cd /var/www/cognus.edukativa.com.br
git remote set-url origin https://SEU_TOKEN@github.com/usuario/moodle-cognus.git
git push origin main
```

### Observer não é executado

- Verifique se executou `admin/cli/upgrade.php`
- Verifique se `enabled = true` no config.php
- Ative debugging no Moodle para ver mensagens

### Jenkins não é disparado

- Verifique se o webhook URL está correto
- Teste manualmente: `curl -X POST "http://jenkins/generic-webhook-trigger/invoke?token=TOKEN"`
- Verifique logs do Jenkins

## Segurança

⚠️ **Importante:**

- Nunca commite o arquivo `observer/config.php` (já está no .gitignore)
- Use tokens com permissões mínimas necessárias
- Restrinja acesso ao webhook do Jenkins por IP se possível
- Considere usar branches separadas para auto-commits

## Desabilitar

Para desabilitar o auto-commit:

```php
'git_auto_push' => [
    'enabled' => false,
    // ...
],
```
