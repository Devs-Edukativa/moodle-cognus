# API Usage Guide - local_edukativa_apis

## Visão Geral

Este plugin fornece APIs REST para gerenciamento de dados do Moodle, incluindo:

- Migração de métodos de autenticação de usuários
- Formatação automática de campos customizados de cursos

## Autenticação

Todas as requisições requerem Bearer Token no header:

```
Authorization: Bearer 4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3
```

## 1. Migração de Autenticação de Usuários

### 1.1 Migrar usuários para "manual"

Migra todos os usuários com métodos de autenticação `email` e `enrolkey` para `manual`. Cria backup automático para rollback.

**Endpoint:** `POST /local/edukativa_apis/fixers.php`

**Request Body:**

```json
{
  "fixer": "fix_auth_type"
}
```

**Response (Sucesso):**

```json
{
  "success": true,
  "message": "Migração concluída com sucesso",
  "data": {
    "email_count": 4084,
    "enrolkey_count": 186,
    "total_migrated": 4270,
    "backup_created": true,
    "backup_table": "mdl_user_auth_backup"
  }
}
```

**Response (Sem usuários para migrar):**

```json
{
  "success": true,
  "message": "Nenhum usuário encontrado para migração",
  "data": {
    "email_count": 0,
    "enrolkey_count": 0,
    "total_migrated": 0,
    "backup_created": false
  }
}
```

**Comportamento:**

- ✅ Cria tabela `mdl_user_auth_backup` automaticamente se não existir
- ✅ Faz backup de todos os usuários antes de alterar
- ✅ Usa transações para garantir integridade dos dados
- ✅ Ignora usuários deletados (`deleted = 0`)
- ✅ Permite rollback posterior

### 1.2 Rollback - Restaurar autenticação anterior

Reverte a migração, restaurando os métodos de autenticação originais dos usuários.

**Endpoint:** `POST /local/edukativa_apis/fixers.php`

**Request Body:**

```json
{
  "fixer": "restore_auth_types"
}
```

**Response (Sucesso):**

```json
{
  "success": true,
  "message": "Rollback concluído com sucesso",
  "data": {
    "restored_count": 4270,
    "backup_entries_removed": 4270
  }
}
```

**Response (Sem backup):**

```json
{
  "success": false,
  "message": "Tabela de backup não encontrada. Nenhuma migração foi feita anteriormente.",
  "data": null
}
```

**Comportamento:**

- ✅ Restaura autenticação de todos os usuários no backup
- ✅ Limpa tabela de backup após restauração bem-sucedida
- ✅ Usa transações para garantir integridade
- ✅ Falha gracefully se backup não existir

---

## 2. Formatação de Campos de Cursos

### 2.1 Processar todos os cursos

Extrai automaticamente `nome_curso` e `cadastro_turma` do campo `fullname` dos cursos e popula os campos customizados correspondentes.

**Endpoint:** `POST /local/edukativa_apis/format.php`

**Request Body:**

```json
{
  "action": "process_all_courses"
}
```

**Response (Sucesso):**

```json
{
  "success": true,
  "message": "Processamento concluído",
  "data": {
    "total_courses": 523,
    "processed": 523,
    "updated": 245,
    "skipped_already_set": 178,
    "skipped_no_pattern": 100,
    "errors": 0,
    "courses_updated": [
      {
        "id": 42,
        "fullname": "Curso de Recursos Genéticos para Alimentação e Agricultura - 2025 - Turma 12",
        "nome_curso": "Curso de Recursos Genéticos para Alimentação e Agricultura",
        "turma": 12
      },
      {
        "id": 85,
        "fullname": "Modelagem de Processos - 2024 - Turma 3",
        "nome_curso": "Modelagem de Processos",
        "turma": 3
      }
    ]
  }
}
```

**Response (Erro - Campos não encontrados):**

```json
{
  "success": false,
  "message": "Campos customizados \"nome_curso\" ou \"cadastro_turma\" não encontrados no Moodle",
  "data": null
}
```

**Comportamento:**

✅ **Padrões aceitos para fullname:**

- `{Nome do Curso} - {Ano} - Turma {Número}`
- `{Nome do Curso} - Turma {Número}`
- `{Nome do Curso} - 2025 - turma 10` (case insensitive)

❌ **Padrões IGNORADOS (não processados):**

- Cursos sem "Turma X" no nome
- Cursos com formato diferente do padrão
- Estes são contabilizados em `skipped_no_pattern`

✅ **Lógica de processamento:**

- Processa apenas cursos onde `nome_curso` OU `cadastro_turma` estão vazios
- Pula cursos que já têm AMBOS os campos preenchidos
- Não sobrescreve dados existentes
- Usa regex para extração robusta

✅ **Segurança:**

- Verifica se campos customizados existem antes de processar
- Insere/atualiza registros de forma segura
- Tratamento de erros com try-catch

---

## 3. Exemplos de Uso

### Exemplo com cURL - Migração de Autenticação

```bash
# Migrar usuários
curl -X POST https://seu-moodle.com.br/local/edukativa_apis/fixers.php \
  -H "Authorization: Bearer 4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3" \
  -H "Content-Type: application/json" \
  -d '{"fixer": "fix_auth_type"}'

# Fazer rollback
curl -X POST https://seu-moodle.com.br/local/edukativa_apis/fixers.php \
  -H "Authorization: Bearer 4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3" \
  -H "Content-Type: application/json" \
  -d '{"fixer": "restore_auth_types"}'
```

### Exemplo com cURL - Formatação de Cursos

```bash
curl -X POST https://seu-moodle.com.br/local/edukativa_apis/format.php \
  -H "Authorization: Bearer 4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3" \
  -H "Content-Type: application/json" \
  -d '{"action": "process_all_courses"}'
```

### Exemplo JavaScript/Fetch

```javascript
// Migrar autenticação
const migrateAuth = async () => {
  const response = await fetch(
    "https://seu-moodle.com.br/local/edukativa_apis/fixers.php",
    {
      method: "POST",
      headers: {
        Authorization:
          "Bearer 4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ fixer: "fix_auth_type" }),
    }
  );

  const result = await response.json();
  console.log(result);
};

// Formatar cursos
const formatCourses = async () => {
  const response = await fetch(
    "https://seu-moodle.com.br/local/edukativa_apis/format.php",
    {
      method: "POST",
      headers: {
        Authorization:
          "Bearer 4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ action: "process_all_courses" }),
    }
  );

  const result = await response.json();
  console.log(result);
};
```

---

## 4. Tabelas do Banco de Dados

### 4.1 Tabela de Backup - `mdl_user_auth_backup`

Criada automaticamente na primeira execução de `fix_auth_type`:

| Campo          | Tipo        | Descrição                                |
| -------------- | ----------- | ---------------------------------------- |
| id             | INT(10)     | Primary key                              |
| userid         | INT(10)     | ID do usuário                            |
| old_auth       | VARCHAR(20) | Método de auth anterior (email/enrolkey) |
| new_auth       | VARCHAR(20) | Novo método (manual)                     |
| migration_date | INT(10)     | Timestamp da migração                    |

**Índices:**

- PRIMARY KEY (`id`)
- INDEX (`userid`)

### 4.2 Campos Customizados Utilizados

**mdl_customfield_field:**

- `shortname = 'nome_curso'` - Nome do curso
- `shortname = 'cadastro_turma'` - Número da turma

**mdl_customfield_data:**

- Armazena valores dos campos customizados por curso
- `instanceid` = ID do curso
- `fieldid` = ID do campo customizado
- `value` = Valor do campo

---

## 5. Fluxo de Trabalho Recomendado

### Migração de Autenticação:

1. ✅ **Liste fixers disponíveis** (opcional)

   ```json
   { "fixer": "list_available_fixers" }
   ```

2. ✅ **Execute migração**

   ```json
   { "fixer": "fix_auth_type" }
   ```

3. ✅ **Verifique resultado** - backup criado em `mdl_user_auth_backup`

4. ✅ **Teste sistema** - verifique se usuários conseguem fazer login

5. ⚠️ **Se houver problemas, execute rollback:**
   ```json
   { "fixer": "restore_auth_types" }
   ```

### Formatação de Cursos:

1. ✅ **Verifique campos customizados** no Moodle:

   - Acesse: Site administration → Courses → Course custom fields
   - Confirme existência de `nome_curso` e `cadastro_turma`

2. ✅ **Execute processamento**

   ```json
   { "action": "process_all_courses" }
   ```

3. ✅ **Analise estatísticas** retornadas:

   - `updated`: cursos atualizados com sucesso
   - `skipped_already_set`: já tinham dados (não alterados)
   - `skipped_no_pattern`: não seguem padrão (ignorados)
   - `errors`: falhas no processamento

4. ✅ **Verifique cursos atualizados** na interface do Moodle

---

## 6. Troubleshooting

### Erro: "Bearer token inválido"

```json
{
  "status": "error",
  "message": "Unauthorized",
  "code": 401
}
```

**Solução:** Verifique se o token no header está correto.

### Erro: "Campos customizados não encontrados"

```json
{
  "success": false,
  "message": "Campos customizados \"nome_curso\" ou \"cadastro_turma\" não encontrados no Moodle"
}
```

**Solução:**

1. Acesse Site administration → Courses → Course custom fields
2. Crie campos com shortnames exatos: `nome_curso` e `cadastro_turma`
3. Execute novamente

### Erro: "Tabela de backup não encontrada"

```json
{
  "success": false,
  "message": "Tabela de backup não encontrada. Nenhuma migração foi feita anteriormente."
}
```

**Solução:** Isso é esperado se você tentar fazer rollback sem ter executado migração antes.

---

## 7. Limpeza de Referências Corrompidas (Question Set References)

### 7.1 Verificar registros corrompidos

Verifica quantos registros órfãos existem na tabela `question_set_references` sem executar nenhuma ação.

**Endpoint:** `POST /local/edukativa_apis/cleanup_qsr.php`

**Request Body:**

```json
{
  "action": "check"
}
```

**Response:**

```json
{
  "status": "success",
  "corrupt_records_found": 5,
  "message": "Encontrados 5 registro(s) corrompido(s).",
  "record_ids": [123, 456, 789, 101, 112]
}
```

### 7.2 Verificar status do backup

Verifica se existe uma tabela de backup e quantos registros ela contém.

**Endpoint:** `POST /local/edukativa_apis/cleanup_qsr.php`

**Request Body:**

```json
{
  "action": "backup_status"
}
```

**Response (com backup):**

```json
{
  "status": "success",
  "backup_exists": true,
  "backup_table": "z_backup_qsr_corrupt",
  "records_in_backup": 5,
  "message": "Backup encontrado com 5 registro(s)."
}
```

**Response (sem backup):**

```json
{
  "status": "success",
  "backup_exists": false,
  "message": "Nenhum backup encontrado."
}
```

### 7.3 Limpar registros corrompidos

Limpa registros órfãos e cria backup automático na tabela `z_backup_qsr_corrupt`.

**Endpoint:** `POST /local/edukativa_apis/cleanup_qsr.php`

**Request Body:**

```json
{
  "action": "clean"
}
```

**Response (Sucesso):**

```json
{
  "status": "success",
  "message": "Limpeza concluída com sucesso.",
  "records_deleted": 5,
  "backup_table": "z_backup_qsr_corrupt"
}
```

**Response (Nenhum registro encontrado):**

```json
{
  "status": "success",
  "message": "Nenhum registro corrompido encontrado.",
  "records_found": 0
}
```

### 7.4 Restaurar backup (Rollback)

Restaura os dados do backup e remove a tabela de backup.

**Endpoint:** `POST /local/edukativa_apis/cleanup_qsr.php`

**Request Body:**

```json
{
  "action": "rollback"
}
```

**Response (Sucesso):**

```json
{
  "status": "success",
  "message": "Rollback concluído. Dados restaurados e tabela de backup removida.",
  "records_restored": 5
}
```

**Response (Backup não encontrado):**

```json
{
  "status": "error",
  "message": "Tabela de backup {z_backup_qsr_corrupt} não encontrada."
}
```

### 7.5 Fluxo de trabalho recomendado

```bash
# 1. Verificar quantos registros corrompidos existem
curl -X POST https://moodle.site/local/edukativa_apis/cleanup_qsr.php \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action": "check"}'

# 2. Executar limpeza (cria backup automaticamente)
curl -X POST https://moodle.site/local/edukativa_apis/cleanup_qsr.php \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action": "clean"}'

# 3. Se necessário, verificar status do backup
curl -X POST https://moodle.site/local/edukativa_apis/cleanup_qsr.php \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action": "backup_status"}'

# 4. Se precisar desfazer, executar rollback
curl -X POST https://moodle.site/local/edukativa_apis/cleanup_qsr.php \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action": "rollback"}'
```

### 7.6 Exemplo de uso com Postman

1. **Method:** POST
2. **URL:** `https://moodle.site/local/edukativa_apis/cleanup_qsr.php`
3. **Headers:**
   - `Authorization: Bearer 4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3`
   - `Content-Type: application/json`
4. **Body (raw JSON):**
   ```json
   {
     "action": "check"
   }
   ```

---

## 8. Segurança

⚠️ **IMPORTANTE:**

- ✅ Todas as APIs requerem Bearer token
- ✅ Transações garantem integridade dos dados
- ✅ Backup automático antes de alterações críticas
- ✅ Validações em todas as operações
- ✅ **cleanup_qsr**: Usa transações e backup automático para segurança máxima
- ⚠️ Token está hardcoded - considere implementar gestão dinâmica de tokens
- ⚠️ Recomenda-se executar em ambiente de homologação primeiro

---

## 9. Performance

### Formatação de Cursos

- **Processamento:** Sequencial, curso por curso
- **Volume esperado:** ~500-1000 cursos em poucos segundos
- **Otimizações:**
  - Pula cursos já processados
  - Ignora cursos sem padrão válido
  - Usa queries eficientes

### Migração de Autenticação

- **Processamento:** Em massa com transações
- **Volume esperado:** 4000+ usuários em segundos
- **Otimizações:**
  - Backup e update em SQL otimizado
  - Transações para rollback automático em caso de erro

---

## Contato

**Desenvolvido por:** Rafael Dantas  
**Data:** 13/12/2025  
**Package:** local_edukativa_apis
