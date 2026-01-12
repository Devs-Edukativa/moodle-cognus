# local_edukativa_apis

Plugin customizado do Moodle que fornece APIs REST para integração com o Sistema de Gestão Acadêmica (SGA) da Edukativa.

## Visão Geral

Este plugin expõe endpoints HTTP que permitem ao backend do SGA comunicar-se com o Moodle para obter informações sobre usuários, cursos, certificados e permissões administrativas.

## Funcionalidades

- **Autenticação de usuários** - Valida credenciais de usuários no Moodle
- **Verificação de permissões** - Identifica administradores e gerentes do sistema
- **Obtenção de certificados** - Lista certificados emitidos para usuários
- **Avaliações de cursos** - Obtém ratings de cursos
- **Inscrições** - Lista inscrições de usuários em cursos
- **Campos customizados** - Acessa campos de cadastro personalizados

## Instalação

### Via Upload ZIP

1. Acesse o Moodle como administrador
2. Vá em _Administração do site > Plugins > Instalar plugins_
3. Faça upload do arquivo ZIP do plugin
4. Complete a instalação

### Instalação Manual

Copie a pasta do plugin para:

```
{moodle}/local/edukativa_apis
```

Depois, acesse _Administração do site > Notificações_ para completar a instalação.

Ou via linha de comando:

```bash
php admin/cli/upgrade.php
```

## Autenticação

Todas as requisições devem incluir um Bearer Token no header `Authorization`:

```http
Authorization: Bearer <token>
```

## Endpoints Principais

### Verificação de Permissões Administrativas

```http
POST /local/edukativa_apis/get_user_admin.php
Content-Type: application/json

{
  "userid": 123
}
```

**Response:**

```json
{
  "status": "success",
  "isAdmin": true,
  "role": "admin|manager",
  "message": "O usuário possui permissões administrativas."
}
```

**Roles consideradas como admin:**

- `admin` - Administradores do site (`is_siteadmin`)
- `manager` - Gerentes do sistema (role manager no contexto do sistema)

### Autenticação de Usuário

```http
POST /local/edukativa_apis/auth_user.php
```

### Obtenção de Certificados

```http
POST /local/edukativa_apis/get_certificates.php
```

### Inscrições

```http
POST /local/edukativa_apis/get_enrolls.php
```

### Logout (Encerramento de Sessão)

Encerra a sessão do usuário no Moodle e redireciona para uma URL externa.

```http
GET /local/edukativa_apis/logout.php?redirect=https://painel.agricultura.gov.br
```

**Parâmetros:**

| Parâmetro  | Tipo   | Obrigatório | Descrição                             |
| ---------- | ------ | ----------- | ------------------------------------- |
| `redirect` | string | Não         | URL para redirecionamento após logout |

**Comportamento:**

- Se o usuário está logado, encerra a sessão
- Redireciona para a URL especificada em `redirect`
- Se `redirect` não for fornecido, redireciona para a página inicial do Moodle

**Exemplo de uso no frontend:**

```javascript
// Redireciona o usuário para o endpoint de logout
window.location.href = `${MOODLE_URL}/local/edukativa_apis/logout.php?redirect=${encodeURIComponent(
  FRONTEND_URL
)}`;
```

### Limpeza de Referências Corrompidas

Limpa registros órfãos na tabela `question_set_references` que referenciam categorias de questões inexistentes.

```http
POST /local/edukativa_apis/cleanup_qsr.php
Content-Type: application/json

{
  "action": "check"
}
```

**Ações disponíveis:**

| Ação            | Descrição                                                         |
| --------------- | ----------------------------------------------------------------- |
| `check`         | Verifica quantos registros corrompidos existem sem executar ações |
| `backup_status` | Verifica se existe backup e quantos registros contém              |
| `clean`         | Limpa registros corrompidos e cria backup automático              |
| `rollback`      | Restaura dados do backup e remove a tabela de backup              |

**Response (check):**

```json
{
  "status": "success",
  "corrupt_records_found": 5,
  "message": "Encontrados 5 registro(s) corrompido(s).",
  "record_ids": [123, 456, 789, 101, 112]
}
```

**Response (clean):**

```json
{
  "status": "success",
  "message": "Limpeza concluída com sucesso.",
  "records_deleted": 5,
  "backup_table": "z_backup_qsr_corrupt"
}
```

**Response (rollback):**

```json
{
  "status": "success",
  "message": "Rollback concluído. Dados restaurados e tabela de backup removida.",
  "records_restored": 5
}
```

**⚠️ Importante:**

- O backup é criado automaticamente na tabela `z_backup_qsr_corrupt`
- Use `check` antes de executar `clean` para saber quantos registros serão afetados
- Use `backup_status` para verificar se existe um backup antes de fazer rollback
- O rollback remove a tabela de backup após restaurar os dados

## Versionamento

| Versão     | Data       | Alterações                              |
| ---------- | ---------- | --------------------------------------- |
| 2024080300 | 03/08/2024 | Versão inicial                          |
| 2025120500 | 05/12/2024 | Suporte para role manager como admin    |
| 2025120501 | 05/12/2024 | Endpoint de logout com redirect externo |

## Requisitos

- Moodle 4.0+ (versão mínima: 2022041900)

## Licença

2024 Rafael Dantas Boeira

2023 Andreas Koch

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see <https://www.gnu.org/licenses/>.
