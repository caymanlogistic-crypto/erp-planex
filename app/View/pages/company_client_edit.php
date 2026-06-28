<?php
require_once __DIR__ . '/../components/client_contact_fields.php';

$contactValues = $old['contacts'] ?? $contacts ?? [];
$contactErrors = $errors['contacts'] ?? [];
?>

<?php if ($company === null): ?>

<div class="notice warn">
    РљРѕРјРїР°РЅРёСЏ РЅРµ РЅР°Р№РґРµРЅР°. РЈРєР°Р¶РёС‚Рµ РєРѕСЂСЂРµРєС‚РЅС‹Р№ company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РєР»РёРµРЅС‚Р°</h1>
        <p class="text-muted">РљРѕРјРїР°РЅРёСЏ: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients" class="btn btn-ghost">в†ђ Рљ СЃРїРёСЃРєСѓ</a>
    </div>
</div>

<div class="notice warn">
    РљРѕРјРїР°РЅРёСЏ РЅР°С…РѕРґРёС‚СЃСЏ РІ СЃС‚Р°С‚СѓСЃРµ В«<?= e($company['status']) ?>В».
</div>

<?php elseif ($client === null): ?>

<div class="page-head">
    <div>
        <h1>Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РєР»РёРµРЅС‚Р°</h1>
        <p class="text-muted">РљРѕРјРїР°РЅРёСЏ: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients" class="btn btn-ghost">в†ђ Рљ СЃРїРёСЃРєСѓ</a>
    </div>
</div>

<div class="notice warn">
    РљР»РёРµРЅС‚ РЅРµ РЅР°Р№РґРµРЅ.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РєР»РёРµРЅС‚Р°</h1>
        <p class="text-muted">РљРѕРјРїР°РЅРёСЏ: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients/<?= $client['id'] ?? '' ?>" class="btn btn-ghost">в†ђ Рљ РєР°СЂС‚РѕС‡РєРµ РєР»РёРµРЅС‚Р°</a>
    </div>
</div>

<div class="notice danger">
    <?= e($dbError) ?>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РєР»РёРµРЅС‚Р°</h1>
        <p class="text-muted"><?= e($client['name']) ?> вЂ” РљРѕРјРїР°РЅРёСЏ: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients/<?= $client['id'] ?>" class="btn btn-ghost">в†ђ Рљ РєР°СЂС‚РѕС‡РєРµ РєР»РёРµРЅС‚Р°</a>
    </div>
</div>

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/clients/<?= $client['id'] ?>/edit" class="panel" data-contact-form>
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">РћСЃРЅРѕРІРЅС‹Рµ РґР°РЅРЅС‹Рµ</h3>

            <div class="field">
                <label class="field-label">РќР°РёРјРµРЅРѕРІР°РЅРёРµ <span class="req">*</span></label>
                <input type="text" name="name" class="field-input<?= !empty($errors['name']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['name'] ?? $client['name']) ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">РРќРќ <span class="req">*</span></label>
                <input type="text" name="inn" class="field-input<?= !empty($errors['inn']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['inn'] ?? $client['inn']) ?>">
                <?php if (!empty($errors['inn'])): ?>
                    <div class="field-msg is-error"><?= e($errors['inn']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">РљРџРџ</label>
                <input type="text" name="kpp" class="field-input"
                       value="<?= e($old['kpp'] ?? $client['kpp'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">РћР“Р Рќ</label>
                <input type="text" name="ogrn" class="field-input"
                       value="<?= e($old['ogrn'] ?? $client['ogrn'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">РЎС‚Р°С‚СѓСЃ <span class="req">*</span></label>
                <select name="status" class="field-select<?= !empty($errors['status']) ? ' is-error' : '' ?>">
                    <?php
                    $statuses = ['active' => 'РђРєС‚РёРІРµРЅ', 'inactive' => 'РќРµР°РєС‚РёРІРµРЅ', 'archived' => 'РђСЂС…РёРІ'];
                    $currentStatus = $old['status'] ?? $client['status'];
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
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? $client['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">РђРґСЂРµСЃР°</h3>

            <div class="field">
                <label class="field-label">Р®СЂРёРґРёС‡РµСЃРєРёР№ Р°РґСЂРµСЃ</label>
                <textarea name="legal_address" class="field-textarea" rows="2"><?= e($old['legal_address'] ?? $client['legal_address'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label class="field-label">Р¤Р°РєС‚РёС‡РµСЃРєРёР№ Р°РґСЂРµСЃ</label>
                <textarea name="physical_address" class="field-textarea" rows="2"><?= e($old['physical_address'] ?? $client['physical_address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">РљРѕРЅС‚Р°РєС‚С‹</h3>
            <?php renderClientContactFields($contactValues, $contactErrors); ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">РЎРѕС…СЂР°РЅРёС‚СЊ</button>
            <a href="/company/clients/<?= $client['id'] ?>" class="btn btn-ghost">РћС‚РјРµРЅР°</a>
        </div>

    </div>
</form>

<script>
(function () {
    function bind() {
        var form = document.querySelector('[data-contact-form]');
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
