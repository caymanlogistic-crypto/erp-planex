# ERP PLANEX — текущая задача

## STATUS: CREATE_WITH_DOCUMENTS_ACCEPTED

Задача: добавление загрузки документов в формы создания водителя/транспорта/перевозчика + поддержка WEBP.

IMPLEMENTED:
- /company/drivers/create: блок «Документы водителя», загрузка файлов (multiple), обработка после INSERT сущности.
- /company/vehicle-sets/create: блок «Документы транспорта», загрузка файлов (multiple), обработка после INSERT.
- /company/contractors/create: блок «Документы перевозчика», загрузка файлов (multiple), обработка после INSERT.
- WEBP добавлен в whitelist расширений и MIME во всех местах валидации (upload, replace, create-формы).
- Проверено создание сущностей без документов и с документами.
- Проверено, что невалидные файлы не создают metadata и не оставляют orphan-файлы.
- entity_type/entity_id задаются серверно по только что созданной сущности.

ARCHITECTURE DECISIONS (#36):
- Загрузка документов при создании: файлы обрабатываются ПОСЛЕ успешного INSERT сущности, но ДО выставления $success=true.
- Стратегия ошибок: Вариант Б — валидные файлы сохраняются, невалидные пропускаются с сообщением об ошибке.
- Физические orphan-файлы удаляются при ошибке сохранения metadata.
- entity_type/entity_id не принимаются из формы — entity_id = lastInsertId(), entity_type жёстко задан.
- Формат WEBP разрешён как image/webp во всех точках валидации.
- Существующая система документов (upload/download/replace/delete) не затронута, только webp в whitelist.

## NEXT: TBD (ожидает владельца)

