# ERP PLANEX — WINDOWS POWERSHELL COMMAND RULES

## Назначение

Проект ERP PLANEX работает на **Windows**. Агенты (KILO, DeepSeek) выполняют команды через shell-инструмент, который использует **Windows PowerShell 5.1**.

PowerShell **не является** Linux shell (bash/sh). Команды с Linux-style синтаксисом (`curl -s`, `curl -o NUL -w`, `grep`, `ls -la` и т.д.) ломаются или ведут себя непредсказуемо в PowerShell.

Этот документ фиксирует обязательные правила выполнения команд в Windows PowerShell, чтобы агенты не ломали runtime-проверки Linux-style командами.

---

## Главное правило

**Запрещено использовать Linux-style синтаксис в PowerShell без проверки совместимости.**

Команда, которая сломалась из-за несовместимости с оболочкой — это не `ACCEPTED`, не `BLOCKED` и не показатель ошибки приложения. Это ошибка агента: **нужно повторить корректной командой для текущей платформы.**

---

## curl в Windows PowerShell

### Проблема

В Windows PowerShell `curl` является **alias для `Invoke-WebRequest`**, а не настоящим `curl.exe`.

| Синтаксис | Linux bash | Windows PowerShell |
|---|---|---|
| `curl -s` | silent mode | **не работает** в Invoke-WebRequest |
| `curl -o NUL` | вывод в /dev/null | **не работает** — NUL не файл в этом контексте |
| `curl -w "%{http_code}"` | вывод HTTP-кода | **не работает** — нет опции `-w` |
| `curl http://host` | простой GET | работает, но через Invoke-WebRequest |

### Пример сломанной команды

```powershell
# НЕ РАБОТАЕТ в PowerShell — зависает/ломается
curl -s -o NUL -w "%{http_code}" http://127.0.0.1:8015/superadmin
```

Именно эта команда сломала QA-проверку SUPERADMIN Stage 1 и вызвала зависание.

### Правильные способы HTTP-проверок в Windows

#### Способ 1: Invoke-WebRequest (рекомендуемый)

```powershell
# Проверка HTTP-статуса
powershell -Command "(Invoke-WebRequest -Uri 'http://127.0.0.1:8015/superadmin' -UseBasicParsing).StatusCode"

# Для главной страницы
powershell -Command "(Invoke-WebRequest -Uri 'http://127.0.0.1:8015/' -UseBasicParsing).StatusCode"

# Проверка контента
powershell -Command "(Invoke-WebRequest -Uri 'http://127.0.0.1:8015/superadmin' -UseBasicParsing).Content"
```

#### Способ 2: Проверка HTTP 404 с try/catch

Invoke-WebRequest выбрасывает исключение при HTTP 4xx/5xx. Для проверки, что 404 возвращается корректно, используй try/catch:

```powershell
powershell -Command "try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8015/nonexistent' -UseBasicParsing; $r.StatusCode } catch { $_.Exception.Response.StatusCode.value__ }"
```

**Важно**: без try/catch Invoke-WebRequest прерывает выполнение как ошибку при 4xx/5xx, что может зависать/останавливать проверку.

#### Способ 3: Настоящий curl через cmd.exe

Если нужен именно curl (например, для совместимости со скриптами), запускать через `cmd.exe /c`:

```powershell
cmd.exe /c "curl -s -o NUL -w '%{http_code}' http://127.0.0.1:8015/superadmin"
```

Или напрямую вызвать `curl.exe`, если он установлен:

```powershell
curl.exe -s -o NUL -w "%{http_code}" http://127.0.0.1:8015/superadmin
```

---

## Другие Linux-aliases в PowerShell

PowerShell имеет встроенные aliases для совместимости, но их поведение отличается:

| Команда | В PowerShell на самом деле | Работает как Linux? |
|---|---|---|
| `curl` | `Invoke-WebRequest` | **НЕТ** (разные флаги) |
| `wget` | `Invoke-WebRequest` | **НЕТ** |
| `ls` | `Get-ChildItem` | частично (другие флаги) |
| `cat` | `Get-Content` | частично |
| `rm` | `Remove-Item` | частично |
| `cp` | `Copy-Item` | частично |
| `mv` | `Move-Item` | частично |
| `echo` | `Write-Output` | частично |
| `sleep` | `Start-Sleep` | частично (секунды, не миллисекунды) |
| `grep` / `rg` | **нет alias** | используй `Select-String` |

### Правило для Git в PowerShell

В спорных случаях, когда Git в PowerShell даёт мусорный/битый вывод (например, из-за проблем с кодировкой или pager), используй:

```powershell
cmd.exe /c "git log --oneline -5"
```

Или отключай pager:

```powershell
git --no-pager log --oneline -5
```

---

## Обязательные правила для агентов

1. **Перед выполнением любой shell-команды агент должен понимать, в какой оболочке он работает**: Windows PowerShell 5.1.

2. **Не копировать Linux-команды из документации/интернета** без адаптации к PowerShell.

3. **Для HTTP-проверок на Windows использовать `Invoke-WebRequest`**, а не `curl`.

4. **Для проверки HTTP 4xx/5xx использовать try/catch**, иначе Invoke-WebRequest прервёт скрипт.

5. **Если нужен настоящий curl**, вызывать `curl.exe` или `cmd.exe /c curl ...`.

6. **Для Git в PowerShell при проблемах** использовать `cmd.exe /c` или `--no-pager`.

7. **Команды должны быть проверяемыми и не должны зависать**. Если команда зависла — это ошибка агента, нужно использовать другой метод.

8. **Если команда сломалась из-за оболочки**, статус не `ACCEPTED`. Нужно повторить корректной командой.

9. **Если команда возвращает неожиданный результат** (пустой вывод, ошибка, виснет), агент обязан:
   - проверить, используется ли правильный синтаксис для PowerShell;
   - не делать ложных выводов о статусе приложения на основе сломанной команды;
   - повторить с корректным синтаксисом.

---

## Примеры QA-проверок на Windows

### Проверка HTTP 200

```powershell
powershell -Command "(Invoke-WebRequest -Uri 'http://127.0.0.1:8015/' -UseBasicParsing).StatusCode"
```

### Проверка HTTP 200 для конкретной страницы

```powershell
powershell -Command "(Invoke-WebRequest -Uri 'http://127.0.0.1:8015/superadmin' -UseBasicParsing).StatusCode"
```

### Проверка HTTP 404

```powershell
powershell -Command "try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8015/nonexistent' -UseBasicParsing; $r.StatusCode } catch { $_.Exception.Response.StatusCode.value__ }"
```

### Проверка контента страницы (наличие ключевого слова)

```powershell
powershell -Command "(Invoke-WebRequest -Uri 'http://127.0.0.1:8015/superadmin' -UseBasicParsing).Content -match 'SUPERADMIN'"
```

### Проверка PHP-синтаксиса

```powershell
php -l path/to/file.php
```

Работает одинаково на всех платформах.

### Проверка git status

```powershell
git status
```

Работает, но при проблемах с кодировкой можно:

```powershell
cmd.exe /c "git status"
```

---

## Инцидент, вызвавший этот документ

**Дата**: 2026-06-12

**Ситуация**: при QA-проверке SUPERADMIN Stage 1 агент `erp-qa-tester` использовал команду:

```powershell
curl -s -o NUL -w "%{http_code}" http://127.0.0.1:8015/superadmin
```

PowerShell воспринял `curl` как alias для `Invoke-WebRequest`. Флаги `-s`, `-o NUL`, `-w` несовместимы с `Invoke-WebRequest`. Команда сломалась, QA-проверка зависла/остановилась.

**Решение**: создать этот документ и зафиксировать обязательные правила для всех агентов.

---

## Связанные документы

- `docs/ai/KILO_PROJECT_RULES.md` — содержит ссылку на этот документ.
- `docs/ai/QA_CHECKLIST.md` — содержит обязательный пункт PowerShell-safe HTTP-проверок.
- `docs/ai/DEEPSEEK_CODER_RULES.md` — содержит ссылку на этот документ.
- `docs/ai/TASK_TEMPLATE.md` — содержит блок WINDOWS COMMAND RULES.
- `docs/ai/DECISIONS_LOG.md` — решение DECISION-0020.

---

## Последнее обновление

2026-06-12 — создан в ответ на инцидент с QA-проверкой SUPERADMIN Stage 1.
