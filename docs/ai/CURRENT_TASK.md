# ERP PLANEX — текущая задача

## STATUS: ROLLED_BACK_TO_PRE_CREATE_WITH_DOCUMENTS

Задача «CREATE_WITH_DOCUMENTS + WEBP» откатана по решению владельца.

Откатанные commits:
- 8b38dd0 feat: add document upload to create forms + WEBP support → reverted by 19c3f32
- 80e57eb docs: document lifecycle audit report → reverted by 4b71463

Текущее состояние:
- Формы создания водителя/транспорта/перевозчика — без inline-загрузки документов.
- WEBP не в whitelist (возвращён к состоянию до задачи).
- Отдельный documents module (/company/documents/*) сохранён.
- Исправление доступа логиста (commit 10cc204) сохранено.
- Меню Подрядчики/Перевозчики/Водители+ТС/Водители/Транспорт сохранено.
- БД и storage не очищались.

## NEXT: TBD (ожидает владельца)
