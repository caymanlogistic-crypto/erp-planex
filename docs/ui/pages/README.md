# ERP PLANEX — UI page templates

Эта папка хранит MD-шаблоны конкретных ERP-страниц.

Каждый файл в этой папке является источником истины для:
- `erp-uiux-designer`;
- `erp-coder`;
- `erp-qa-tester`;
- `erp-architect`.

## Правило

Если создаётся или меняется страница, дизайнер сначала создаёт или обновляет соответствующий MD-шаблон страницы.

Кодер реализует страницу строго по этому шаблону.

QA проверяет реализацию строго по этому шаблону.

## Базовый шаблон

Использовать:

```text
docs/ui/pages/_PAGE_TEMPLATE.md
```

## Примеры будущих файлов

```text
clients-list-page.md
client-card-page.md
contractors-list-page.md
contractor-card-page.md
trips-list-page.md
trip-card-page.md
superadmin-companies-page.md
```
