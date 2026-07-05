<?php
$vehicleSetRules = function_exists('vehicleSetTypeRules') ? vehicleSetTypeRules() : [];
$predefinedSections = function_exists('vehicleSetPredefinedDocumentSections')
    ? vehicleSetPredefinedDocumentSections()
    : ['primary' => [], 'secondary' => []];
$selectedSetType = (string) ($old['set_type'] ?? '');
$currentRule = $vehicleSetRules[$selectedSetType] ?? null;
$visibleRoles = !empty($currentRule['units']) && is_array($currentRule['units'])
    ? array_keys($currentRule['units'])
    : [];
$unitDefaults = [
    'plate_number' => '',
    'brand' => '',
    'model' => '',
    'vin' => '',
    'capacity_tons' => '',
    'volume_m3' => '',
    'diagnostic_card_number' => '',
    'diagnostic_card_date' => '',
    'unit_type' => '',
];
$unitValues = [];
foreach (['primary', 'secondary'] as $role) {
    $posted = isset($old['units'][$role]) && is_array($old['units'][$role])
        ? $old['units'][$role]
        : [];
    $unitValues[$role] = array_merge($unitDefaults, $posted);
}
$customDocRowsByRole = ['primary' => [], 'secondary' => []];
$oldCustomTypes = isset($old['custom_doc_type']) && is_array($old['custom_doc_type'])
    ? $old['custom_doc_type']
    : [];
$oldCustomNewTypes = isset($old['custom_doc_type_new']) && is_array($old['custom_doc_type_new'])
    ? $old['custom_doc_type_new']
    : [];
foreach (['primary', 'secondary'] as $role) {
    $roleTypes = isset($oldCustomTypes[$role]) && is_array($oldCustomTypes[$role])
        ? $oldCustomTypes[$role]
        : [];
    $roleNewTypes = isset($oldCustomNewTypes[$role]) && is_array($oldCustomNewTypes[$role])
        ? $oldCustomNewTypes[$role]
        : [];
    $rowCount = max(count($roleTypes), count($roleNewTypes));
    for ($i = 0; $i < $rowCount; $i++) {
        $selectedType = trim((string) ($roleTypes[$i] ?? ''));
        $newType = trim((string) ($roleNewTypes[$i] ?? ''));
        if ($selectedType === '' && $newType === '') {
            continue;
        }
        $customDocRowsByRole[$role][] = [
            'selected' => $selectedType,
            'new' => $newType,
            'visible' => $selectedType !== '' ? $selectedType : $newType,
        ];
    }
}
$getUnitError = static function (array $errors, string $role, string $field): string {
    return (string) ($errors['units'][$role][$field] ?? '');
};
$getDocumentDisplayTitle = static function (string $role, array $pdoc): string {
    $code = (string) ($pdoc['doc_code'] ?? '');

    return match ($code) {
        'sts', 'registration_certificate' => 'СТС',
        'diagnostic_card' => 'Диагностическая карта',
        'photo' => 'Фотография',
        default => (string) ($pdoc['name'] ?? ''),
    };
};
$getUnitPresentation = static function (string $setType, string $role, ?array $unitConfig): array {
    $label = (string) ($unitConfig['label'] ?? ($role === 'secondary' ? 'Дополнительная единица' : 'Основная единица'));
    $docsTitle = (string) ($unitConfig['docs_title'] ?? ($role === 'secondary' ? 'Документы дополнительной единицы' : 'Документы транспортной единицы'));

    if ($setType === 'single') {
        return [
            'label' => 'ТРАНСПОРТНАЯ ЕДИНИЦА',
            'docs_title' => 'Документы транспортной единицы',
            'custom_docs_title' => 'Произвольные документы транспортной единицы',
        ];
    }
    if ($setType === 'coupling') {
        return [
            'label' => $role === 'secondary' ? 'ПОЛУПРИЦЕП' : 'ТЯГАЧ',
            'docs_title' => $role === 'secondary' ? 'Документы полуприцепа' : 'Документы тягача',
            'custom_docs_title' => $role === 'secondary' ? 'Произвольные документы полуприцепа' : 'Произвольные документы тягача',
        ];
    }
    if ($setType === 'road_train') {
        return [
            'label' => $role === 'secondary' ? 'ПРИЦЕП' : 'ПЕРВАЯ ЧАСТЬ',
            'docs_title' => $role === 'secondary' ? 'Документы прицепа' : 'Документы первой части',
            'custom_docs_title' => $role === 'secondary' ? 'Произвольные документы прицепа' : 'Произвольные документы первой части',
        ];
    }

    return [
        'label' => $label,
        'docs_title' => $docsTitle,
        'custom_docs_title' => 'Произвольные документы',
    ];
};
$isCouplingSelected = $selectedSetType === 'coupling';
?>
<?php if ($company === null): ?>
<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">Подрядчики</span>
        <h1 class="page-title">Создать транспорт</h1>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/vehicle-sets') ?>" class="btn btn-secondary">&larr; К списку</a>
    </div>
</div>
<div class="page-content">
    <div class="form-alert alert-warning">
        <div class="alert-body">
            <div class="alert-body-title">Компания не найдена</div>
            <div class="alert-body-sub">В сессии не найден company_id.</div>
        </div>
    </div>
</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">
            Подрядчики / <?= e(mb_strtoupper($company['name'])) ?>
        </span>
        <h1 class="page-title">Создать транспорт</h1>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/vehicle-sets') ?>" class="btn btn-secondary">&larr; К списку</a>
    </div>
</div>
<div class="page-content">
    <div class="form-alert alert-warning">
        <div class="alert-body">
            <div class="alert-body-title">Компания неактивна</div>
            <div class="alert-body-sub">
                Текущий статус: <?= e($company['status']) ?>.
                Создание транспорта недоступно.
            </div>
        </div>
    </div>
</div>
<?php elseif ($success): ?>
<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">
            Подрядчики / <?= e(mb_strtoupper($company['name'])) ?>
        </span>
        <h1 class="page-title">Транспорт создан</h1>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/vehicle-sets/create') ?>" class="btn btn-secondary">Создать ещё</a>
        <a href="/company/vehicle-sets/<?= (int) ($createdVehicleSet['id'] ?? 0) ?>" class="btn btn-primary">Открыть карточку</a>
    </div>
</div>
<div class="page-content">
    <div class="panel">
        <div class="panel-body">
            <div class="form-alert alert-success">
                <div class="alert-body">
                    <div class="alert-body-title">Транспорт успешно создан</div>
                    <?php if (!empty($uploadedDocs)): ?>
                    <div class="alert-body-sub">
                        Загружено документов: <?= count($uploadedDocs) ?>.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($docErrors)): ?>
            <div class="form-alert alert-warning">
                <div class="alert-body">
                    <div class="alert-body-title">Часть документов не загружена</div>
                    <div class="alert-body-sub"><?= e(implode(' | ', $docErrors)) ?></div>
                </div>
            </div>
            <?php endif; ?>
            <div class="form-section">
                <div class="section-title">Сводка</div>
                <dl class="dl">
                    <dt>Тип комплекта</dt>
                    <dd><?= e(ui_set_type($createdVehicleSet['set_type'] ?? null)) ?></dd>
                    <dt>Статус</dt>
                    <dd><?= e(($createdVehicleSet['status'] ?? 'active') === 'inactive' ? 'Неактивен' : 'Активен') ?></dd>
                    <dt>Комментарий</dt>
                    <dd><?= e($createdVehicleSet['comments'] ?? '') ?: '-' ?></dd>
                </dl>
            </div>
            <?php if (!empty($createdVehicleSet['units']) && is_array($createdVehicleSet['units'])): ?>
            <div class="vehicle-set-summary-grid">
                <?php foreach ($createdVehicleSet['units'] as $role => $unit): ?>
                <div class="vehicle-set-unit-card">
                    <div class="section-title">
                        <?= e($unit['label'] ?? ($role === 'secondary' ? 'Дополнительная единица' : 'Основная единица')) ?>
                    </div>
                    <dl class="dl">
                        <dt>Госномер</dt>
                        <dd><?= e($unit['plate_number'] ?? '-') ?></dd>
                        <dt>Марка</dt>
                        <dd><?= e($unit['brand'] ?? '-') ?></dd>
                        <dt>Модель</dt>
                        <dd><?= e($unit['model'] ?? '-') ?></dd>
                        <dt>VIN</dt>
                        <dd><?= e($unit['vin'] ?? '-') ?></dd>
                        <dt>Номер диагностической карты</dt>
                        <dd><?= e($unit['diagnostic_card_number'] ?? '-') ?></dd>
                        <dt>Дата получения диагностической карты</dt>
                        <dd><?= e($unit['diagnostic_card_date'] ?? '-') ?></dd>
                    </dl>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">
            Подрядчики / <?= e(mb_strtoupper($company['name'])) ?>
        </span>
        <h1 class="page-title">Создать транспорт</h1>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/vehicle-sets') ?>" class="btn btn-secondary">&larr; К списку</a>
    </div>
</div>
<div class="page-content">
<?php require base_path('app/View/partials/company_vehicle_set_create_form.php'); ?>
</div>
<?php endif; ?>
