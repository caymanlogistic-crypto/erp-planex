# UPDATE — 2026-06-13 — COMPANY STORAGE KEY APPROVED

Owner-approved rule for the first SUPERADMIN Companies Registry implementation:

Storage folder for each expeditor/company is generated/named by company ID.

Do not generate company storage folder from slug/key in the first implementation.

Recommended first-stage pattern:

```text
storage/
  companies/
    {company_id}/
      clients/
      contractors/
      drivers/
      vehicles/
      crews/
      trips/
      templates/
```

`{company_id}` means the numeric/central company ID created by SUPERADMIN registry.

Older `{company_key}` wording below is superseded where it conflicts with this rule.

---

# ERP PLANEX — DOCUMENT_STORAGE_MODEL

## Основной принцип

Файлы документов хранятся физически в `/storage`.

В БД хранятся:
- путь к файлу;
- тип документа;
- сущность;
- статус;
- метаданные;
- кто загрузил;
- когда загрузил.

## Пример storage

```text
storage/
  clients/
  contractors/
  drivers/
  vehicles/
  crews/
  trips/
  templates/
  company/
```

## Документы не должны храниться в public

Публичный доступ к файлам запрещён.

Скачивание должно идти через контроллер с проверкой прав.

## Статусы документов

Будущие статусы:

```text
uploaded
verified
rejected
```

На первом этапе глубокая реализация проверки документов не требуется, но поля нужно предусмотреть.

---

## Уточнение 2026-06-13 — storage экспедитора

Для каждого экспедитора / локальной ERP создается отдельная storage-папка.

В эту папку попадают только загруженные документы и файлы, относящиеся к данным конкретного экспедитора.

Кодовая база остается общей и не копируется в папку экспедитора:
- PHP-скрипты не копируются;
- CSS/JS не копируются;
- миграции не копируются;
- системные конфиги и секреты не хранятся в storage.

Рекомендуемая структура для следующего этапа:

```text
storage/
  companies/
    {company_key}/
      clients/
      contractors/
      drivers/
      vehicles/
      crews/
      trips/
      templates/
```

`{company_key}` и правило его генерации должны быть утверждены отдельной архитектурной задачей перед кодингом.

В БД должен храниться путь к файлу относительно storage-пространства конкретного экспедитора, а скачивание должно идти через контроллер с проверкой прав.
