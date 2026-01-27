# Event Observers - Backend Sync

Sistema de observadores de eventos do Moodle que sincroniza automaticamente dados com o backend SGA.

## 📋 Estrutura

```
observer/
├── base_observer.php       # Classe base com lógica HTTP compartilhada
├── enrol_observer.php      # Observa eventos de matrícula
├── course_observer.php     # Observa eventos de cursos
├── user_observer.php       # Observa eventos de usuários
└── completion_observer.php # Observa eventos de conclusão
```

## 🔄 Eventos Observados

### Matrículas (enrol_observer.php)

- ✅ `user_enrolment_created` → POST /enrolls (crud=c)
- ✅ `user_enrolment_deleted` → DELETE /enrolls (crud=d)

### Cursos (course_observer.php)

- ✅ `course_updated` → POST /courses (crud=c)
- ✅ `course_deleted` → DELETE /courses (crud=d)

### Usuários (user_observer.php)

- ✅ `user_created` → POST /users (crud=c)
- ✅ `user_updated` → POST /users (crud=u)
- ✅ `user_deleted` → DELETE /users (crud=d)

### Conclusões (completion_observer.php)

- ✅ `course_completed` → POST /enrolls/completed (crud=c)
- ✅ `course_module_completion_updated` → POST /enrolls (crud=c)
  - Envia dados quando um módulo/atividade é concluída

## ⚙️ Configuração

### Configuração via observer/config.php

As credenciais são carregadas de um arquivo de configuração separado:

1. **Copie o arquivo de exemplo:**

   ```bash
   cd local/edukativa_apis/observer/
   cp config.example.php config.php
   ```

2. **Edite o config.php com suas credenciais:**
   ```php
   $config = [
       'backend_url' => 'https://sga-back.agricultura.gov.br',
       'x_url' => 'sistemasweb.agricultura.gov.br/avaenagro',
       'x_wstoken' => 'seu_token_aqui',
       'mkey' => 'gestao_academica',
       'bearer_token' => 'seu_jwt_token_aqui',
       'sync_enabled' => true,
       'debug_mode' => true,
   ];
   ```

**⚠️ IMPORTANTE:**

- O arquivo `config.php` está no `.gitignore` e NÃO será commitado
- Sempre use `config.example.php` como referência
- Para desabilitar sync temporariamente: `'sync_enabled' => false`

## 🚀 Ativação

1. **Faça upgrade do plugin:**

   ```bash
   php admin/cli/upgrade.php
   ```

   Ou acesse: `Site administration → Notifications`

2. **Verifique os observers registrados:**

   ```bash
   php admin/cli/scheduled_task.php --list
   ```

3. **Os observers são ativados automaticamente!** Não precisa configurar nada adicional.

## 📊 Logging

### Debug Logs

Os logs são salvos em:

- **Moodle debugging:** Visível quando `$CFG->debug = DEBUG_DEVELOPER`
- **Arquivo de log:** `$CFG->dataroot/local_edukativa_apis.log`

### Formato do Log

```
2025-12-16 18:30:45 [local_edukativa_apis] POST /enrolls | HTTP 201 | Data: {"userid":"12345","courseid":"10","crud":"c"} | Response: {"success":true} | Error: none
```

### Ativar Debug

Em `config.php`:

```php
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;
```

## 🧪 Testes

### Testar Matrícula

```php
// Inscrever usuário em curso
enrol_user($userid, $courseid);

// Desinscrever usuário
unenrol_user($userid, $courseid);
```

### Testar Conclusão de Módulo

1. Acesse um curso como aluno
2. Complete uma atividade (quiz, assignment, etc)
3. Verifique se o POST foi enviado para `/enrolls`

### Ver Logs em Tempo Real

```bash
tail -f /var/www/html/moodledata/local_edukativa_apis.log
```

## 🔍 Troubleshooting

### Observers não estão sendo executados

1. **Limpar cache:**

   ```bash
   php admin/cli/purge_caches.php
   ```

2. **Verificar se plugin foi atualizado:**

   ```sql
   SELECT * FROM mdl_config_plugins WHERE plugin = 'local_edukativa_apis';
   ```

3. **Testar manualmente:**
   ```php
   // Em um script PHP
   require_once('config.php');
   $event = \core\event\user_enrolment_created::create([...]);
   $event->trigger();
   ```

### Backend não está recebendo dados

1. **Verificar logs:** `tail -f local_edukativa_apis.log`
2. **Testar cURL manualmente:**

   ```bash
   curl -X POST https://sga-back.agricultura.gov.br/enrolls \
     -H "Content-Type: application/json" \
     -H "x-wstoken: 72623eb2acdb24f771d388fd9bfc69a3" \
     -d '{"userid":"123","crud":"c"}'
   ```

3. **Verificar firewall/SSL:** Os observers desabilitam verificação SSL por padrão

### Erro de Token Expirado

Atualize o `BEARER_TOKEN` em `base_observer.php` com um token válido.

## 📝 Dados Enviados

### POST /enrolls (Matrícula)

```json
{
  "userid": "12345",
  "courseid": "10",
  "crud": "c"
}
```

### POST /enrolls (Módulo Concluído)

```json
{
  "userid": "12345",
  "courseid": "10",
  "cmid": "567",
  "crud": "c"
}
```

### POST /courses (Curso)

```json
{
  "cursoid": "10",
  "crud": "c"
}
```

### POST /users (Usuário)

```json
{
  "userid": "12345",
  "crud": "c"
}
```

## 🔐 Segurança

- ✅ Credenciais hardcoded (não expostas em configurações)
- ✅ HTTPS obrigatório
- ✅ Headers de autenticação em todas requisições
- ⚠️ SSL verification desabilitado (ajustar em produção se necessário)

## 📦 Migração dos Triggers

Os observers **substituem completamente** os triggers antigos do plugin tool_trigger. Vantagens:

1. ✅ **Execução garantida** - Não falha silenciosamente
2. ✅ **Logs detalhados** - Fácil debugging
3. ✅ **Performance** - Não depende de cron
4. ✅ **Manutenção** - Código organizado e testável

Você pode **desativar os triggers antigos** em:
`Site administration → Plugins → Admin tools → Trigger`
