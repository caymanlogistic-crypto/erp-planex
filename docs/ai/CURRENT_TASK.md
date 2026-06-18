# ERP PLANEX — текущая задача

## STATUS: DOCUMENT_LIFECYCLE_AUDITED — PARTIAL_VERIFIED_WITH_ISSUES

Проведён полный runtime-аудит документов по всем сущностям и точкам загрузки.

CREATE_WITH_DOCUMENTS: ACCEPTED (базовая загрузка при создании работает).
FULL_DOCUMENT_LIFECYCLE: функции загрузки/скачивания/замены/удаления реализованы для всех 7 entity_types, но обнаружены:
- BUG: orphan physical files при replace (старый файл не удаляется).
- BUG: orphan physical files при delete (soft delete, файл остаётся на диске).
- SECURITY GAP: document routes не проверяют entity_access_grants для logist.
- SECURITY GAP: replace handler сбрасывает uploaded_by_user_id, давая заменившему право на удаление.

## Проверенные сущности

| Сущность | entity_type | Create+docs | Upload | Download | Replace | Delete |
|----------|-------------|-------------|--------|----------|---------|--------|
| Перевозчик | contractor | OK | OK | OK | OK (no grant check) | OK (self-owned only) |
| Водитель | driver | OK | OK | OK | OK (no grant check) | OK (self-owned only) |
| Транспорт | vehicle_set | OK | OK | OK | OK (no grant check) | OK (self-owned only) |
| Водители+ТС | driver_vehicle_block | N/A (нет create+docs) | OK | OK | OK | OK |
| Клиент | client | N/A | OK | OK | OK | OK |
| Транспортная единица | vehicle_unit | N/A | OK | OK | OK | OK |
| Экипаж | crew | N/A | OK | OK | OK | OK |

## BUGS FOUND (pre-existing, не регрессии CREATE_WITH_DOCUMENTS)

### BUG-1: Orphan files after replace
- Replace создаёт новый физический файл (uniqid), обновляет stored_name в БД.
- Старый файл НЕ удаляется. Каждый replace оставляет orphan на диске.
- File: public/index.php, lines ~9760-9788 (replace handler).
- Impact: накопление мусора в storage.

### BUG-2: Orphan files after delete
- Delete делает soft delete (UPDATE deleted_at), НЕ удаляет физический файл.
- Файл остаётся на диске навсегда.
- File: public/index.php, lines ~9627-9687 (delete handler).
- Impact: накопление мусора в storage.

### BUG-3: Document routes bypass entity_access_grants
- Маршруты /company/documents (list, upload, download, replace) проверяют только role (requireRole), но НЕ проверяют entity_access_grants.
- Logist может получить доступ к документам любой сущности в компании, зная entity_type + entity_id.
- Карточки сущностей корректно блокируют доступ (проверяют grants), но страницы документов — нет.
- File: public/index.php, document routes (lines ~8913-9796).
- Impact: утечка данных между логистами одной компании.

### BUG-4: Replace resets uploaded_by_user_id
- Replace handler обновляет uploaded_by_user_id на ID заменившего пользователя.
- Это даёт заменившему право на удаление документа (delete handler проверяет uploaded_by_user_id для logist).
- File: public/index.php, lines ~9785-9786.

### SUMMARY TABLE

```
DB active docs: 20
Disk files: 29
Orphans: 9 (из-за BUG-1 и BUG-2)
```

## NEXT

Ожидает решения владельца по bugs BUG-1..BUG-4:
- Нужно ли исправлять orphan files в replace/delete?
- Нужно ли добавить entity_access_grants в document routes?
- Нужно ли изменить логику uploaded_by_user_id при replace?

