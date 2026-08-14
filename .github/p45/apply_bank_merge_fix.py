from pathlib import Path
import re

service = Path('app/Service/FinanceBankReconciliationService.php')
text = service.read_text(encoding='utf-8')
pattern = r"    public static function checkDuplicates\(PDO \$localPdo, \?int \$accountId = null\): array\n    \{.*?\n    \}\n\n    public static function checkTransactionAggregates"
replacement = '''    public static function checkDuplicates(PDO $localPdo, ?int $accountId = null): array
    {
        $where = '';
        $params = [];
        if ($accountId !== null) {
            $where = ' WHERE account_id = ?';
            $params[] = $accountId;
        }

        $sql = 'SELECT bt.id, bt.account_id, bt.operation_date, bt.document_number,
                       bt.debit_amount, bt.credit_amount, bt.counterparty_name,
                       bt.purpose, bt.dedupe_hash, dup.hash_count
                FROM bank_transactions bt
                JOIN (
                    SELECT dedupe_hash, COUNT(*) AS hash_count
                    FROM bank_transactions' . $where . '
                    GROUP BY dedupe_hash
                    HAVING COUNT(*) > 1
                ) dup ON dup.dedupe_hash <=> bt.dedupe_hash';
        if ($accountId !== null) {
            $sql .= ' WHERE bt.account_id = ?';
            $params[] = $accountId;
        }
        $sql .= ' ORDER BY bt.account_id, bt.operation_date, bt.id';

        $stmt = $localPdo->prepare($sql);
        $stmt->execute($params);
        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($duplicates as $d) {
            $results[] = [
                'transaction_id' => (int)$d['id'],
                'account_id' => (int)$d['account_id'],
                'operation_date' => $d['operation_date'],
                'document_number' => $d['document_number'],
                'debit_amount' => $d['debit_amount'],
                'credit_amount' => $d['credit_amount'],
                'counterparty_name' => $d['counterparty_name'],
                'purpose' => $d['purpose'],
                'dedupe_hash' => $d['dedupe_hash'],
                'hash_count' => (int)$d['hash_count'],
            ];
        }

        return $results;
    }

    public static function checkTransactionAggregates'''
new_text, n = re.subn(pattern, replacement, text, count=1, flags=re.S)
if n != 1:
    raise SystemExit('checkDuplicates patch target not found')
service.write_text(new_text, encoding='utf-8')

view = Path('app/View/pages/company_bank_accounts.php')
v = view.read_text(encoding='utf-8')
old = '''                <?php if ($reconSummary['all_ok'] ?? false): ?>
                <span class="badge badge-ok"><span class="dot"></span> OK</span>
                <?php else: ?>
                <span class="badge badge-danger"><span class="dot"></span> Есть расхождения</span>
                <?php endif; ?>'''
new_status = '''                <?php if ($reconSummary['service_error'] ?? false): ?>
                <span class="badge badge-danger"><span class="dot"></span> Ошибка проверки</span>
                <?php elseif ($reconSummary['all_ok'] ?? false): ?>
                <span class="badge badge-ok"><span class="dot"></span> OK</span>
                <?php else: ?>
                <span class="badge badge-danger"><span class="dot"></span> Есть расхождения</span>
                <?php endif; ?>'''
if old not in v:
    raise SystemExit('reconciliation status view target not found')
v = v.replace(old, new_status, 1)
needle = '''            <?php if (($reconSummary['gap'] ?? 0) > 0): ?>
            <span class="bank-summary-separator">·</span>
            <span class="text-danger">Разрывов: <?= (int)$reconSummary['gap'] ?></span>
            <?php endif; ?>'''
extra = needle + '''
            <?php if (($reconSummary['duplicate'] ?? 0) > 0): ?>
            <span class="bank-summary-separator">·</span>
            <span class="text-danger">Дубликатов: <?= (int)$reconSummary['duplicate'] ?></span>
            <?php endif; ?>
            <?php if (($reconSummary['mismatch'] ?? 0) > 0): ?>
            <span class="bank-summary-separator">·</span>
            <span class="text-danger">Несовпадений оборотов: <?= (int)$reconSummary['mismatch'] ?></span>
            <?php endif; ?>'''
if needle not in v:
    raise SystemExit('reconciliation summary target not found')
v = v.replace(needle, extra, 1)
view.write_text(v, encoding='utf-8')

runtime = Path('.github/p41/P41_finance_runtime.js')
runtime.write_text("""'use strict';
const{chromium}=require('playwright');const B='https://plan-ex.ru/erpv2/';const ok=(v,m)=>{if(!v)throw Error(m)};
(async()=>{const cs=JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64||'','base64').toString('utf8'));const c=cs.find(x=>String(x.role).toUpperCase()==='OWNER');ok(c,'OWNER credentials');const b=await chromium.launch({headless:true});const x=await b.newContext({viewport:{width:1920,height:1080},locale:'ru-RU',timezoneId:'Europe/Moscow'});const p=await x.newPage();try{await p.goto(B+'login');await p.locator('input[name=\\\"username\\\"],input[name=\\\"email\\\"],input[type=\\\"text\\\"]').first().fill(c.username);await p.locator('input[type=\\\"password\\\"]').fill(c.password);await p.locator('button[type=\\\"submit\\\"],input[type=\\\"submit\\\"]').first().click();await p.waitForTimeout(700);ok(!p.url().includes('/login'),'login');let r=await p.goto(B+'company/finance/bank-accounts',{waitUntil:'domcontentloaded'});ok(r.status()===200,'bank HTTP');ok(await p.locator('#bank-classification-register').count()===0,'duplicate classification register removed');const table=p.locator('table.bank-transactions-table');ok(await table.count()===1,'single bank transactions table');const h=(await table.locator('th').allTextContents()).map(s=>s.trim());for(const s of ['Дата','Счёт','Контрагент','ИНН','Назначение','Дебет','Кредит','ЦФУ','Статья ДДС','Статус'])ok(h.includes(s),'bank header '+s);for(const n of ['date_from','date_to','q','classification_status'])ok(await p.locator(`form.bank-controls-filter [name=\\\"${n}\\\"]`).count(),'filter '+n);const recon=await p.request.get(B+'company/finance/bank-accounts/reconciliation/json');ok(recon.status()===200,'recon HTTP');const j=await recon.json();ok(j.status==='ok','reconciliation service '+(j.message||''));console.log('P41_RECON_SUMMARY='+JSON.stringify(j.reconciliation.summary||{}));r=await p.goto(B+'company/finance/settings/matching-rules',{waitUntil:'domcontentloaded'});ok(r.status()===200,'rules HTTP');await p.getByText('Справочник ЦФУ',{exact:true}).first().waitFor({state:'visible',timeout:10000});await p.locator('#matching-rule-create-btn').click();await p.waitForTimeout(500);const m=p.locator('#matching-rule-create-modal.is-open');await m.waitFor({state:'attached',timeout:10000});for(const n of ['target_cash_flow_center_id','target_dds_category_id','target_cash_account_id','priority'])ok(await m.locator(`[name=\\\"${n}\\\"]`).count(),'field '+n);ok(await m.locator('option[value=\\\"transfer_to_cash\\\"]').count(),'bank-to-cash');ok(!/(Fatal error|Parse error|Uncaught TypeError)/i.test(await p.locator('body').innerText()),'fatal');console.log('P41_FINANCE_RUNTIME_OK');}finally{await b.close();}})().catch(e=>{console.error(e);process.exit(1)});
""", encoding='utf-8')

partial = Path('app/View/partials/company_bank_classification_register.php')
if partial.exists():
    partial.unlink()
