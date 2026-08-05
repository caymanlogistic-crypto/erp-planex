# P17_17 — Root cause TimeoutError кассовой операции

## Статус

`ROOT_CAUSE_CONFIRMED`

## Исходный отказ

Предыдущий P17 runtime остановился на ожидании:

```text
form[action$="/company/finance/cash/operation-create"]
```

Playwright завершил ожидание через 30 секунд с `TimeoutError`.

## Проверенная цепочка

- Страница: `GET /company/finance/cash`.
- Роль: `COMPANY_OWNER`.
- На странице одновременно присутствуют кнопки:
  - `Создать кассу`;
  - `Приход/Расход`;
  - `Перевод`.
- Форма кассовой операции действительно существует и после штатного открытия имеет action:
  - `/erpv2/company/finance/cash/operation-create`.
- Форма находится в модальном окне `cash-operation-create-modal`.
- До открытия нужной модалки форма не является целевым активным UI-контекстом.

## Точная причина

В старом P17 runner использовался неоднозначный selector:

```js
page.locator('button,a').filter({ hasText: /Внести|Приход|Создать/i }).first()
```

На странице первой подходящей кнопкой была `Создать кассу`, потому что слово `Создать` входило в регулярное выражение и кнопка расположена раньше `Приход/Расход` в DOM.

Runner открывал модалку создания кассы, а затем ожидал форму другой модалки — кассовой операции. Поэтому selector формы никогда не находился и возникал timeout.

## Что не являлось причиной

- URL страницы кассы корректен.
- Route `/company/finance/cash/operation-create` существует.
- Форма кассовой операции присутствует в продукте.
- Роль OWNER имеет доступ.
- Action формы корректен; suffix selector совместим с `/erpv2/...`.
- Увеличение timeout не устраняет причину.
- Production-дефект кассовой операции на этом этапе не подтверждён.

## Исправление

Runner обязан:

1. Находить точную кнопку `Приход/Расход` по exact text или подтверждённому modal target.
2. Нажимать её.
3. Проверять, что открыта именно модалка `cash-operation-create-modal`.
4. Проверять exact form action с учётом `/erpv2`.
5. При несовпадении сохранять DOM inventory и завершаться диагностическим FAIL, а не ждать 30 секунд.
6. Заполнять native date input в ISO-формате `YYYY-MM-DD`.

## Evidence

- Старый runtime: `P17_CRUD_RUNTIME_SUMMARY.json` — `TimeoutError` на target form.
- Старый runner: `.github/p17/P17_crud_trip_finance_runtime.js` — неоднозначный regex с `Создать` и `.first()`.
- P16 finance discovery: `P16_FINANCE_FORM_DISCOVERY.json` — успешное открытие по `/Приход/i`, modal `cash-operation-create-modal`, action `/erpv2/company/finance/cash/operation-create`.
- Screenshot discovery: `P16_CASH_DISCOVERY_1920x1080.png`.

## Классификация

- Тип: ошибка P17 browser harness.
- Severity продукта: `NONE_CONFIRMED`.
- Severity приёмочного контура: `BLOCKER_FOR_ACCEPTANCE_EVIDENCE`.
- Требуемое действие: новый независимый P17R run с чистым BrowserContext и новыми `UIUX_P17R_` данными.
