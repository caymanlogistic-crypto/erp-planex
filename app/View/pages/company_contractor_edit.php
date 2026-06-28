<?php

require_once __DIR__ . '/../components/status_badge.php';
require_once __DIR__ . '/../components/contractor_contact_fields.php';

$contactValues = $old['contacts'] ?? $contacts ?? [];
$contactErrors = $errors['contacts'] ?? [];

?>

<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            РљРѕРјРїР°РЅРёСЏ РЅРµ РЅР°Р№РґРµРЅР°. <a href="/company/contractors">в†ђ Рљ СЃРїРёСЃРєСѓ</a>
        </div>
    </div>
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РїРµСЂРµРІРѕР·С‡РёРєР°</h1>
        <p class="text-muted">РљРѕРјРїР°РЅРёСЏ: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    РљРѕРјРїР°РЅРёСЏ РЅР°С…РѕРґРёС‚СЃСЏ РІ СЃС‚Р°С‚СѓСЃРµ В«<?= e($company['status']) ?>В».
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions mt-4">
            <a href="/company/contractors" class="btn btn-ghost">в†ђ Рљ СЃРїРёСЃРєСѓ</a>
        </div>
    </div>
</div>

<?php elseif ($contractor === null): ?>

<div class="page-head">
    <div>
        <h1>РџРµСЂРµРІРѕР·С‡РёРє РЅРµ РЅР°Р№РґРµРЅ</h1>
        <p class="text-muted">РљРѕРјРїР°РЅРёСЏ: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">в†ђ Рљ СЃРїРёСЃРєСѓ</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            РџРµСЂРµРІРѕР·С‡РёРє СЃ СѓРєР°Р·Р°РЅРЅС‹Рј ID РЅРµ РЅР°Р№РґРµРЅ.
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РїРµСЂРµРІРѕР·С‡РёРєР°</h1>
        <p class="text-muted"><?= e($contractor['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors/<?= $contractor['id'] ?>" class="btn btn-ghost">в†ђ Рљ РєР°СЂС‚РѕС‡РєРµ</a>
    </div>
</div>

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/contractors/<?= $contractor['id'] ?>/edit" class="panel" data-contractor-contact-form>
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">РћСЃРЅРѕРІРЅС‹Рµ РґР°РЅРЅС‹Рµ</h3>

            <div class="field">
                <label class="field-label">РќР°РёРјРµРЅРѕРІР°РЅРёРµ <span class="req">*</span></label>
                <input type="text" name="name" class="field-input<?= !empty($errors['name']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['name'] ?? $contractor['name']) ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">РРќРќ <span class="req">*</span></label>
                <input type="text" name="inn" class="field-input<?= !empty($errors['inn']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['inn'] ?? $contractor['inn']) ?>">
                <?php if (!empty($errors['inn'])): ?>
                    <div class="field-msg is-error"><?= e($errors['inn']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">РљРџРџ</label>
                <input type="text" name="kpp" class="field-input"
                       value="<?= e($old['kpp'] ?? $contractor['kpp'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">РћР“Р Рќ</label>
                <input type="text" name="ogrn" class="field-input"
                       value="<?= e($old['ogrn'] ?? $contractor['ogrn'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">РўРёРї РїРµСЂРµРІРѕР·С‡РёРєР°</label>
                <select name="contractor_type" class="field-select">
                    <?php $contractorType = $old['contractor_type'] ?? $contractor['contractor_type'] ?? ''; ?>
                    <option value="">вЂ” РќРµ СѓРєР°Р·Р°РЅ вЂ”</option>
                    <option value="legal_entity" <?= $contractorType === 'legal_entity' ? 'selected' : '' ?>>Р®СЂРёРґРёС‡РµСЃРєРѕРµ Р»РёС†Рѕ</option>
                    <option value="individual" <?= $contractorType === 'individual' ? 'selected' : '' ?>>РРЅРґРёРІРёРґСѓР°Р»СЊРЅС‹Р№ РїСЂРµРґРїСЂРёРЅРёРјР°С‚РµР»СЊ</option>
                    <option value="self_employed" <?= $contractorType === 'self_employed' ? 'selected' : '' ?>>РЎР°РјРѕР·Р°РЅСЏС‚С‹Р№</option>
                    <option value="private_person" <?= $contractorType === 'private_person' ? 'selected' : '' ?>>Р¤РёР·РёС‡РµСЃРєРѕРµ Р»РёС†Рѕ</option>
                </select>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">РђРґСЂРµСЃР°</h3>

            <div class="field">
                <label class="field-label">Р®СЂРёРґРёС‡РµСЃРєРёР№ Р°РґСЂРµСЃ</label>
                <textarea name="legal_address" class="field-textarea" rows="2"><?= e($old['legal_address'] ?? $contractor['legal_address'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label class="field-label">Р¤Р°РєС‚РёС‡РµСЃРєРёР№ Р°РґСЂРµСЃ</label>
                <textarea name="physical_address" class="field-textarea" rows="2"><?= e($old['physical_address'] ?? $contractor['physical_address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">РљРѕРЅС‚Р°РєС‚С‹</h3>
            <?php renderContractorContactFields($contactValues, $contactErrors); ?>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Р‘Р°РЅРєРѕРІСЃРєРёРµ СЂРµРєРІРёР·РёС‚С‹</h3>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Р Р°СЃС‡С‘С‚РЅС‹Р№ СЃС‡С‘С‚</label>
                    <input type="text" name="bank_account" class="field-input"
                           value="<?= e($old['bank_account'] ?? $contractor['bank_account'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field-label">Р‘РРљ</label>
                    <input type="text" name="bank_bik" class="field-input"
                           value="<?= e($old['bank_bik'] ?? $contractor['bank_bik'] ?? '') ?>">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Р‘Р°РЅРє</label>
                    <input type="text" name="bank_name" class="field-input"
                           value="<?= e($old['bank_name'] ?? $contractor['bank_name'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field-label">РљРѕСЂСЂ. СЃС‡С‘С‚</label>
                    <input type="text" name="bank_corr_account" class="field-input"
                           value="<?= e($old['bank_corr_account'] ?? $contractor['bank_corr_account'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">РЎС‚Р°С‚СѓСЃ Рё РєРѕРјРјРµРЅС‚Р°СЂРёР№</h3>

            <div class="field">
                <label class="field-label">РЎС‚Р°С‚СѓСЃ <span class="req">*</span></label>
                <select name="status" class="field-select<?= !empty($errors['status']) ? ' is-error' : '' ?>">
                    <?php
                    $statuses = ['active' => 'РђРєС‚РёРІРµРЅ', 'inactive' => 'РќРµР°РєС‚РёРІРµРЅ', 'archived' => 'РђСЂС…РёРІ'];
                    $currentStatus = $old['status'] ?? $contractor['status'];
                    foreach ($statuses as $val => $label):
                    ?>
                    <option value="<?= $val ?>" <?= $currentStatus === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['status'])): ?>
                    <div class="field-msg is-error"><?= e($errors['status']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">РљРѕРјРјРµРЅС‚Р°СЂРёР№</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? $contractor['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">РЎРѕС…СЂР°РЅРёС‚СЊ</button>
            <a href="/company/contractors/<?= $contractor['id'] ?>" class="btn btn-ghost">РћС‚РјРµРЅР°</a>
        </div>

    </div>
</form>

<script>
(function () {
    function bind() {
        var form = document.querySelector('[data-contractor-contact-form]');
        if (!form || !window.initContactFields) {
            return false;
        }
        window.initContactFields(form, { fieldPrefix: 'contacts' });
        return true;
    }
    if (bind()) {
        return;
    }
    window.addEventListener('load', bind, { once: true });
})();
</script>

<?php endif; ?>
