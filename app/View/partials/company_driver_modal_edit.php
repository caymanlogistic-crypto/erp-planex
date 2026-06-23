<?php
/**
 * Modal edit partial - driver edit mode.
 *
 * Variables expected:
 *   $driver       array   driver row
 *   $phones       array   driver_phones rows (from DB, is_main DESC)
 *   $docsByType   array   ['passport'=>[], 'license'=>[], 'snils'=>[], 'other'=>[]]
 *   $errors       array   field-level errors (on validation failure)
 *   $formError    ?string top-level form error
 */

$old = [
    'full_name' => $driver['full_name'] ?? '',
    'phone' => $driver['phone'] ?? '',
    'email' => $driver['email'] ?? '',
    'passport_number' => $driver['passport_number'] ?? '',
    'passport_department_code' => $driver['passport_department_code'] ?? '',
    'passport_issued_by' => $driver['passport_issued_by'] ?? '',
    'passport_issue_date' => $driver['passport_issue_date'] ?? '',
    'license_number' => $driver['license_number'] ?? '',
    'license_issue_date' => $driver['license_issue_date'] ?? '',
    'snils' => $driver['snils'] ?? '',
    'comments' => $driver['comments'] ?? '',
];

$extraPhones = [];
$extraPhoneComments = [];
if (!empty($phones)) {
    foreach ($phones as $ph) {
        if (!empty($ph['is_main'])) {
            continue;
        }
        $extraPhones[] = $ph['phone'] ?? '';
        $extraPhoneComments[] = $ph['comment'] ?? '';
    }
}
$old['extra_phones'] = $extraPhones;
$old['extra_phone_comments'] = $extraPhoneComments;

if (!empty($errors)) {
    $old = array_merge($old, $_POST);
    if (isset($_POST['extra_phones'])) {
        $old['extra_phones'] = $_POST['extra_phones'];
    }
    if (isset($_POST['extra_phone_comments'])) {
        $old['extra_phone_comments'] = $_POST['extra_phone_comments'];
    }
}

$driverExistingDocsByCode = [
    'passport' => $docsByType['passport'] ?? [],
    'driver_license' => $docsByType['license'] ?? [],
    'snils' => $docsByType['snils'] ?? [],
];

$driverFormId = 'driver-edit-form';
$driverFormDomPrefix = 'driver-edit-' . (int) $driver['id'];
$driverFormAction = '/company/drivers/' . $driver['id'] . '/modal-edit';
$driverCreateFormMode = 'edit';
$driverFormClass = '';
$driverFormShowDocs = true;

$predefDocs = $predefDocs ?? [
    ['name' => 'Паспорт', 'code' => 'passport', 'ext' => 'PDF'],
    ['name' => 'Водительское удостоверение', 'code' => 'driver_license', 'ext' => 'PDF'],
    ['name' => 'СНИЛС', 'code' => 'snils', 'ext' => 'PDF'],
];

ob_start();
?>
<div class="file-list">
    <?php
    $editDocSections = [
        'Паспорт' => $docsByType['passport'] ?? [],
        'Водительское удостоверение' => $docsByType['license'] ?? [],
        'СНИЛС' => $docsByType['snils'] ?? [],
        'Прочие документы' => $docsByType['other'] ?? [],
    ];
    foreach ($editDocSections as $sectionTitle => $sectionDocs):
        foreach ($sectionDocs as $doc):
            $docId = (int) ($doc['id'] ?? 0);
            if ($docId <= 0) {
                continue;
            }
            $name = $doc['original_name'] ?? $doc['stored_name'] ?? '';
            $metaText = $name ?: 'Файл';
            $parts = explode('.', $name);
            $ext = count($parts) > 1 ? strtoupper(end($parts)) : '';
            $mime = $doc['mime_type'] ?? '';
            $badgeCls = 'file-type-badge';
            $badgeTxt = '—';
            if ($ext === '') {
                if (strpos($mime, 'pdf') !== false) {
                    $ext = 'PDF';
                } elseif (strpos($mime, 'image') !== false) {
                    $ext = 'IMG';
                }
            }
            if ($ext === 'PDF') {
                $badgeCls .= ' is-pdf';
                $badgeTxt = 'PDF';
            } elseif ($ext === 'DOC' || $ext === 'DOCX' || $ext === 'RTF' || $ext === 'ODT') {
                $badgeCls .= ' is-doc';
                $badgeTxt = 'DOC';
            } elseif ($ext === 'XLS' || $ext === 'XLSX' || $ext === 'CSV' || $ext === 'ODS') {
                $badgeCls .= ' is-xls';
                $badgeTxt = 'XLS';
            } elseif (in_array($ext, ['JPG', 'JPEG', 'PNG', 'WEBP', 'GIF', 'BMP', 'TIF', 'TIFF', 'HEIC', 'HEIF'], true)) {
                $badgeCls .= ' is-img';
                $badgeTxt = 'IMG';
            } else {
                $badgeCls .= ' is-other';
                $badgeTxt = $ext ?: 'FILE';
            }
            $rowTitle = $sectionTitle === 'Прочие документы' && !empty($doc['document_type'])
                ? (string) $doc['document_type']
                : $sectionTitle;
            $rowKey = 'existing-' . $docId;
    ?>
    <div class="file-item file-item-predef document-file-row has-file has-existing-file driver-doc-view-item"
         id="<?= e($driverFormDomPrefix) ?>frow-<?= $rowKey ?>"
         data-file-row="predef"
         data-file-code="<?= e($rowKey) ?>"
         data-has-existing="1"
         data-existing-badge-class="<?= e($badgeCls) ?>"
         data-existing-badge-text="<?= e($badgeTxt) ?>"
         data-existing-meta="<?= e($metaText) ?>"
         data-existing-button-text="Заменить">
        <div class="<?= $badgeCls ?>" id="<?= e($driverFormDomPrefix) ?>fbadge-<?= $rowKey ?>"><?= $badgeTxt ?></div>
        <div class="file-info">
            <div class="file-name"><?= e($rowTitle) ?></div>
            <div class="file-meta" id="<?= e($driverFormDomPrefix) ?>fname-<?= $rowKey ?>"><?= e($metaText) ?></div>
        </div>
        <button type="button"
                class="btn btn-secondary file-action-btn js-file-pick-btn"
                data-file-input="<?= e($driverFormDomPrefix) ?>predef-file-<?= $rowKey ?>">
            <span id="<?= e($driverFormDomPrefix) ?>fbtn-<?= $rowKey ?>">Заменить</span>
        </button>
        <button type="button"
                class="predef-file-clear"
                id="<?= e($driverFormDomPrefix) ?>fclear-<?= $rowKey ?>"
                title="Удалить файл">×</button>
        <input type="file"
               id="<?= e($driverFormDomPrefix) ?>predef-file-<?= $rowKey ?>"
               class="file-input-hidden js-predef-file-input"
               name="existing_doc_file[<?= $docId ?>]"
               data-label="<?= e($driverFormDomPrefix) ?>fname-<?= $rowKey ?>"
               data-badge="<?= e($driverFormDomPrefix) ?>fbadge-<?= $rowKey ?>"
               data-button-label="<?= e($driverFormDomPrefix) ?>fbtn-<?= $rowKey ?>"
               data-clear="<?= e($driverFormDomPrefix) ?>fclear-<?= $rowKey ?>">
        <input type="hidden" name="delete_existing_doc[<?= $docId ?>]" value="0" data-delete-predef-doc>
    </div>
    <?php
        endforeach;
    endforeach;
    ?>
</div>

<div class="form-section driver-edit-docs-actions">
    <div id="<?= e($driverFormDomPrefix) ?>custom-docs-container" class="file-list" data-custom-docs-container></div>
    <button type="button" class="btn btn-ghost add-custom-doc-btn" id="<?= e($driverFormDomPrefix) ?>add-custom-doc-btn" data-add-custom-doc-btn>+ Добавить документ</button>
</div>
<?php
$driverFormDocContent = ob_get_clean();

$docTypes = $docTypes ?? [];
?>
<div class="modal-body">
  <?php require base_path('app/View/partials/company_driver_create_form.php'); ?>
</div>

<div class="modal-foot is-spaced">
  <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
  <div class="modal-foot-actions">
    <button type="button" class="btn btn-ghost" data-driver-cancel-edit-btn>Отмена</button>
    <button type="submit" form="driver-edit-form" class="btn btn-primary">Сохранить</button>
  </div>
</div>
