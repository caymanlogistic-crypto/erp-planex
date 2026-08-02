# ERP PLANEX — архитектурные решения

## Finance migration compatibility hotfix (2026-07-30)

### Решение: старые checksum можно принимать только точечно и с проверкой схемы
Для локальных миграций компании запрещён общий bypass checksum mismatch. Допустим только явный whitelist для конкретного файла миграции и конкретного старого checksum, если отдельная проверка доказывает совместимость фактической схемы с целевым состоянием.

### Решение: finance errors не маскировать как DB connection
Finance controllers не должны превращать любое исключение в «Не удалось подключиться к базе данных компании». Сообщение должно отражать реальную стадию сбоя: подключение, миграция, загрузка данных или runtime.

### Решение: production web-entrypoint должен явно грузить finance services
CLI-smoke и web runtime должны использовать один набор service require. Если controller action использует finance service прямо или косвенно, `public/index.php` обязан подгружать этот сервис. Для финального финансового блока обязательны `FinanceAllocationService` и `FinanceBankReconciliationService`.

## Production deploy gate (2026-07-30) — Final finance block

### Решение: публикация считается принятой только после backup и server smoke
Для финального финансового блока production-публикация засчитывается только если до загрузки создан backup DB/файлов, production `.env` и `storage/companies` сохранены, миграции выполнены через `/usr/bin/php8.3`, post-deploy smoke проверил company DB, local migrations, finance tables/services и owner-only доступ.

### Решение: browser owner visual check не заменяет server smoke
Если нет пароля владельца компании, Codex не должен подменять визуальную owner-проверку догадками. Серверный smoke подтверждает backend/runtime; визуальный контроль в рабочей owner-сессии выполняет владелец.

## Stage 9C (2026-07-30) — Local runtime acceptance gate

### Решение: Stage 8 expected-results должен быть runtime-verified
Статический PASS больше не считается достаточным для финальной приёмки финансового блока. Stage 8 считается принятым только когда expected-results сверены с реальными данными локальной тестовой компании и тест выходит с кодом 0.

### Решение: production только после полного локального PASS
Публикация разрешена только после `LOCAL_RUNTIME_ACCEPTED`: Stage 7 PASS, Stage 8 PASS, Stage 9 owner+finance runtime PASS, отсутствие mojibake/секретов, `git diff --check` PASS. Перед production обязательны backup DB/файлов, проверка миграций и план отката.

### Решение: финансовый доступ остаётся owner-only
Финансовый блок, документы, JSON, AJAX и POST endpoints остаются доступными только `company_owner`. `senior_logist`, `logist` и другие роли не получают финансовый доступ до отдельной задачи владельца.

## Stage 6 (2026-07-29) — Matching Rules

### Решение: Scoring engine для правил разнесения
- Каждый фактор (direction, bank_account, INN, invoice_pattern, purpose_contains, purpose_regex, amount_from, amount_to) имеет фиксированный вес
- Общий score определяет confidence: 45+ = high, 20-44 = medium, 1-19 = low
- Auto-apply ТОЛЬКО при confidence >= 45 И auto_apply=1
- Single-factor правила (только purpose_contains, только INN) НЕ могут авто-применяться — защита от ложных срабатываний
- Решение мотивировано: broad ambiguous rule не должен автоматически закрывать счёт

### Решение: Приоритет правил
- Правила сортируются по `priority ASC` при поиске `findBestMatchingRule`
- Первое правило с `auto_apply=1` и достаточным score применяется немедленно (early return)
- Если auto_apply не найдено, возвращается лучшее non-auto_apply совпадение с max score
- Preview показывает все непроведённые транзакции, соответствующие условиям правила

### Решение: Интеграция в importParsedData
- `BankFinanceService::importParsedData()` вызывает `applyAutoMatchToOperation()` после `createOperationFromBankTransaction()`
- Matching rules не вмешиваются в transfer confirmation (который происходит раньше)
- Аудит применения правила через `FinanceAuditLogService::log()` с action='rule_apply'

### Решение: Owner-only доступ
- Все 9 action files защищены `requireRole(['company_owner'])`
- Правила разнесения доступны только руководителю компании
- JSON/AJAX endpoints (preview, test, reorder) также под owner guard

### Решение: UTF-8 поддержка в regex
- Invoice number pattern и purpose_regex используют модификатор `/iu` для корректной работы с кириллицей
- `purpose_contains` использует `mb_strtolower()` для case-insensitive сравнения

## Stage 5 (2026-07-29, corrected 2026-07-29) — Transfers & Bank Confirmation

### Решение: PENDING_CONFIRMATION для bank-involved transfers
- Трансферы, где хотя бы один счёт BANK, создаются со статусом `PENDING_CONFIRMATION`
- Чисто кассовые трансферы (CASH→CASH) остаются сразу `POSTED` (без изменений)
- `PENDING_CONFIRMATION` не влияет на баланс (только POSTED учитывается)
- Статус визуализируется как `badge badge-warning` (желтый)

### Решение: Matching engine для bank transaction → transfer
- `findMatchingTransferForBankTransaction()` ищет подходящий transfer по:
  - bank account → money_account_id
  - направление (debit → out, credit → in)
  - точная сумма
  - окно дат ±3 дня
  - частичное совпадение назначения (purpose)
- Если один кандидат → `match_quality: 'exact'` — автоматическое подтверждение
- Если несколько кандидатов → `match_quality: 'ambiguous'` — требует ручного подтверждения

### Решение: Intercept в importParsedData
- `BankFinanceService::importParsedData()` при импорте каждой bank транзакции вызывает `findMatchingTransferForBankTransaction()`
- При exact match → `confirmTransfer()` + **не создаётся** INCOME/EXPENSE операция
- При no match / ambiguous → `createOperationFromBankTransaction()` как обычно
- Никакой второй операции не создаётся — дублирование исключено

### Решение: Transaction ownership pattern в confirmTransfer
- `confirmTransfer()` использует `$startedTransaction` + `inTransaction()` для безопасного вызова как изолированно, так и внутри внешней транзакции
- `SELECT ... FOR UPDATE` выполняется только после начала транзакции (ownership decision)
- commit/rollback происходит только если метод сам начал транзакцию
- Предотвращает ошибку "There is already an active transaction" при вызове из `importParsedData()`

### Решение: Bank transaction reuse guard
- `findMatchingTransferForBankTransaction()`: проверяет, что bank_transaction_id не привязан к non-cancelled операции, иначе возвращает null
- `confirmTransfer()`: проверяет и выбрасывает RuntimeException при попытке повторного использования
- Cancelled операции не блокируют повторное использование (соответствует семантике отмены)

### Решение: Аудирование lifecycle
- Создание pending transfer: `action='create_pending'` через `FinanceAuditLogService`
- Подтверждение transfer: `action='confirm'`
- Отмена transfer: `action='cancel'` (уже было в Stage 4A)
- Все изменения проходят через `FinanceAuditLogService`, нигде нет raw INSERT

## Stage 4B (2026-07-29) — Cancellation & Audit UI

### Решение: Стандартизированное модальное окно отмены
- Создан отдельный partial `company_finance_cancel_modal.php` с:
  - Поле причины (required HTML + JS + server-side)
  - CSRF-защита
  - AJAX-отправка с ререндером родительского modal view
  - Кнопка «Подтвердить отмену» с `btn-danger`
- Модальное окно отмены размещается на странице (не внутри AJAX-loaded modal view), а кнопки в modal view ссылаются на его ID
- Dynamic action URL устанавливается через JS перед открытием модального окна

### Решение: История изменений
- Existing `company_finance_history_view.php` используется для обоих типов сущностей (operation + invoice)
- History endpoint уже существовал для operations; для invoices добавлен `InvoiceActions/history.php`
- JS fetch-загрузка по кнопке «История» в обоих modal view
- Пустое состояние: «Нажмите «История» для загрузки.» до первого запроса

### Решение: Аннулирование счетов
- Переименовано из «Удалить» в «Аннулировать» (фактически это cancel, не delete)
- `modal_delete.php` теперь принимает reason из POST (ранее не передавался)
- AJAX-поддержка: при успехе возвращается обновлённый invoice modal view
- Пустая причина → RuntimeException

### Решение: Owner-only security (подтверждено)
- Все 9 endpoint files проверены: `requireRole(['company_owner'])` присутствует
- AJAX/json endpoints (`route_payments`, `counterparty_list`): inline company_owner check
- History endpoints: company_owner only
- Эта архитектура уже зафиксирована в Stage 4A и не менялась для Stage 4B

## Stage 4A (2026-07-29) — Finance audit & cancellation (corrected)

### Решение: Единый сервис аудита
- `FinanceAuditLogService` — единственный класс, содержащий raw `INSERT INTO finance_audit_log`.
- Все application service classes вызывают `FinanceAuditLogService::log()` вместо inline SQL.
- Статический guard в тесте проверяет отсутствие raw INSERT в сервисах.
- Решение мотивировано: единая точка контроля формата old/new values + user_id/role + created_at.

### Решение: Причина отмены обязательна
- `cancelInvoice()` в `FinanceInvoiceService` — выбрасывает `\RuntimeException` при пустой/whitespace причине.
- В Stage 4A добавлено `cancelInvoice(..., ?string $reason = null)`. Если `$reason` null/whitespace — throw.
- Fallback-причина «Аннулирован вручную» удалена. Клиентский код (UI) обязан передавать reason.
- Тест проверяет source-код на наличие throw и отсутствие fallback.

### Решение: Transfer атомарная отмена
- `FinanceOperationService::cancelTransferAtomic()` — обе ноги TRANSFER в одной транзакции с FOR UPDATE.
- Если одна нога не может быть отменена, обе остаются POSTED.

## Stage 3 (2026-07-29) — Settlement cascade

### Решение: Каскадный пересчёт через единый сервис
- `FinanceSettlementCascadeService` — единственный сервис, который обновляет `paid_amount`, `first_paid_at`, `fully_paid_at`, `status` для invoice и route payment.
- `FinanceAllocationService::createAllocation()` / `cancelAllocation()` — вызывают cascade сервис.
- View-код не использует raw SQL для paid/remaining — только `FinanceSettlementCascadeService::getInvoiceRemainingAmount()` и т.д.
- Решение мотивировано: гарантия консистентности при распределении/отмене.

### Решение: Invoice lifecycle status
- 6 статусов для invoice display: unpaid, partially_paid, paid, overdue, overdue_partial, cancelled.
- `overdue_partial` — новый статус для частично оплаченных счетов с просроченным planned_payment_date.
- Статус вычисляется в `computeInvoiceDisplayStatus()`, не хранится в БД.
- Badge для overdue_partial использует `badge badge-danger`.

## Stage 2 (2026-07-29) — Payment condition model

### Решение: Legacy backfill safety
- `legacyPaymentDueTypeToConditionType()` возвращает NULL для неоднозначных legacy значений.
- NULL значения маркируются как ambiguous, требуют ручного разбора.
- `Предоплата на загрузке` и `До выгрузки` — не мапятся автоматически.

## B2 (2026-07-28) — Balance source of truth

### Решение: Model B для банковских счетов
- Для BANK счетов: `FinanceBalanceService` использует `bank_daily_balances.closing_balance`.
- Для CASH счетов: `opening_balance + SUM(POSTED operations)`.
- `FinanceOperationService::ensureMoneyAccountsForBankAccounts()` создаёт BANK money_accounts с `opening_balance = 0.00`.

## B1 (2026-07-28) — Access hardening

### Решение: Finance company-flow owner-only
- Все finance routes/actions/AJAX/JSON: `requireRole(['company_owner'])`.
- Sidebar: finance секция скрыта для `senior_logist` и `logist`.
- Серверная защита, а не только UI hiding.
