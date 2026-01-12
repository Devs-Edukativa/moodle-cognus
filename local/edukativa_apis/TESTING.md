# Testing Guide - local_edukativa_apis

## Implemented Features

### 1. User Authentication Migration (`fixers.php`)

#### Functions Implemented:

- `fix_auth_type()` - Migrates users from 'email' and 'enrolkey' to 'manual'
- `fix_restore_auth_types()` - Rollback function to restore previous auth methods

#### API Endpoints:

```bash
# Migrate users
POST /local/edukativa_apis/fixers.php
Body: {"fixer": "fix_auth_type"}

# Rollback migration
POST /local/edukativa_apis/fixers.php
Body: {"fixer": "restore_auth_types"}
```

#### Expected Behavior:

1. **Migration:**

   - Creates `mdl_user_auth_backup` table if it doesn't exist
   - Backs up all users with auth='email' or auth='enrolkey'
   - Changes their auth to 'manual'
   - Returns counts: email_count, enrolkey_count, total_migrated

2. **Rollback:**
   - Reads backup table
   - Restores original auth methods for all backed-up users
   - Clears backup table
   - Returns: restored_count, backup_entries_removed

#### Test Cases:

**TC1: Migrate users with email auth**

```bash
curl -X POST http://localhost/moodle/local/edukativa_apis/fixers.php \
  -H "Authorization: Bearer 4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3" \
  -H "Content-Type: application/json" \
  -d '{"fixer": "fix_auth_type"}'
```

Expected:

- Success: true
- email_count: 4084 (based on screenshot)
- backup_created: true

**TC2: Rollback migration**

```bash
curl -X POST http://localhost/moodle/local/edukativa_apis/fixers.php \
  -H "Authorization: Bearer 4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3" \
  -H "Content-Type: application/json" \
  -d '{"fixer": "restore_auth_types"}'
```

Expected:

- Success: true
- restored_count: 4084
- Users should be able to login with original auth methods

**TC3: Attempt rollback without migration**
Expected:

- Success: false
- Message: "Tabela de backup não encontrada"

---

### 2. Course Field Formatting (`format.php`)

#### Functions Implemented:

- `parse_course_name_and_turma($fullname)` - Regex parser for course names
- `get_course_custom_field_ids()` - Gets field IDs for nome_curso and cadastro_turma
- `check_course_custom_fields($courseid, $fieldids)` - Checks if fields already populated
- `update_course_custom_fields()` - Updates/inserts custom field data
- `process_all_courses()` - Main endpoint to process all courses

#### API Endpoints:

```bash
# Process all courses
POST /local/edukativa_apis/format.php
Body: {"action": "process_all_courses"}
```

#### Expected Behavior:

1. Reads all courses (id > 1)
2. Parses fullname using regex pattern
3. Skips courses that don't match pattern
4. Skips courses with both fields already set
5. Updates only missing fields

#### Regex Patterns Supported:

- ✅ "Nome do Curso - 2025 - Turma 12"
- ✅ "Nome do Curso - Turma 5"
- ✅ "Nome do Curso - 2024 - turma 3" (case insensitive)
- ❌ "Nome do Curso" (ignored, no turma)
- ❌ "Nome Sem Padrão 123" (ignored)

#### Test Cases:

**TC4: Process courses with valid pattern**

```bash
curl -X POST http://localhost/moodle/local/edukativa_apis/format.php \
  -H "Authorization: Bearer 4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3" \
  -H "Content-Type: application/json" \
  -d '{"action": "process_all_courses"}'
```

Expected:

- Success: true
- processed: total number of courses
- updated: courses that were modified
- skipped_no_pattern: courses without "Turma X" pattern
- courses_updated: array with details of updated courses

**TC5: Run again after processing**
Expected:

- updated: 0 (all already set)
- skipped_already_set: same as previous 'updated' count

**TC6: Custom fields don't exist**
Expected:

- Success: false
- Message: "Campos customizados ... não encontrados"

---

## Manual Verification Steps

### After Migration:

1. Check `mdl_user_auth_backup` table exists:

   ```sql
   SELECT COUNT(*) FROM mdl_user_auth_backup;
   ```

2. Verify users migrated:

   ```sql
   SELECT COUNT(*) FROM mdl_user WHERE auth='manual';
   SELECT COUNT(*) FROM mdl_user WHERE auth='email';
   SELECT COUNT(*) FROM mdl_user WHERE auth='enrolkey';
   ```

3. Test user login with manual account

### After Course Formatting:

1. Check custom field data:

   ```sql
   SELECT c.id, c.fullname,
          cfd1.value as nome_curso,
          cfd2.value as turma
   FROM mdl_course c
   LEFT JOIN mdl_customfield_data cfd1 ON cfd1.instanceid = c.id
     AND cfd1.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname='nome_curso')
   LEFT JOIN mdl_customfield_data cfd2 ON cfd2.instanceid = c.id
     AND cfd2.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname='cadastro_turma')
   WHERE c.id > 1
   LIMIT 20;
   ```

2. Verify in Moodle UI:
   - Go to Course management
   - Edit a course
   - Check "nome_curso" and "cadastro_turma" fields are populated

---

## Rollback Procedures

### If Migration Fails:

1. Use restore endpoint immediately
2. Check backup table for records
3. Verify users restored to original auth

### If Course Formatting Fails:

- No rollback needed
- Data only inserted if fields were empty
- Re-run is idempotent (won't overwrite existing data)

---

## Common Issues

### Issue: "xmldb_table not found"

**Cause:** Missing ddllib.php include
**Solution:** Already fixed - `require_once($CFG->libdir . '/ddllib.php');`

### Issue: "context_course not found"

**Cause:** Missing accesslib.php include
**Solution:** Already fixed - `require_once($CFG->libdir . '/accesslib.php');`

### Issue: "Bearer token inválido"

**Solution:** Check Authorization header format:

```
Authorization: Bearer 4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3
```

---

## Performance Expectations

### Migration (4000+ users):

- Execution time: ~2-5 seconds
- Memory usage: Low (bulk SQL operations)
- Database load: Moderate (one-time operation)

### Course Formatting (500 courses):

- Execution time: ~3-10 seconds
- Memory usage: Low
- Database load: Low (efficient queries)

---

## Success Criteria

✅ **Migration:**

- All users with auth='email' changed to 'manual'
- All users with auth='enrolkey' changed to 'manual'
- Backup table populated
- Users can still login

✅ **Rollback:**

- All users restored to original auth
- Backup table cleared
- Users can login with original method

✅ **Course Formatting:**

- Courses matching pattern have nome_curso populated
- Courses matching pattern have cadastro_turma populated
- Courses without pattern are skipped (not errored)
- Courses with existing data are not modified
- Statistics returned accurately

---

## Files Modified

1. `fixers.php` - Added migration and rollback functions
2. `format.php` - Complete rewrite with course formatting
3. `API_USAGE.md` - Comprehensive API documentation
4. `TESTING.md` - This file

## Next Steps

1. Deploy to staging/development Moodle instance
2. Run TC1-TC6 test cases
3. Verify manual verification steps
4. Test rollback functionality
5. Deploy to production after successful staging tests
