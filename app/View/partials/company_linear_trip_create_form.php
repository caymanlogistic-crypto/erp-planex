<?php

use App\Service\DocumentService;
use App\Service\LinearRouteService;

$routeFormMode = $routeFormMode ?? 'create';
$routeFormId = $routeFormId ?? 'linear-trip-create-form';
$routeFormAction = $routeFormAction ?? '/company/trips/linear/create';
$routeFormDomPrefix = $routeFormDomPrefix ?? ($routeFormMode === 'edit' ? 'linear-trip-edit' : 'linear-trip');
$routeFormClass = $routeFormClass ?? 'panel linear-trip-panel';
$existingDocsByCode = $existingDocsByCode ?? [];
$showActualDates = $routeFormMode === 'edit';
$validationErrors = $validationErrors ?? [];

$requestedRouteType = trim((string) ($_GET['route_type'] ?? ''));
if (!in_array($requestedRouteType, [LinearRouteService::ROUTE_TYPE_LINEAR, LinearRouteService::ROUTE_TYPE_AGENCY], true)) {
    $requestedRouteType = '';
}

$errorOf = static function (string $key) use ($validationErrors): string {
    return (string) ($validationErrors[$key] ?? '');
};

$currentRouteType = (string) ($old['route_type'] ?? $requestedRouteType);
$selectedClientId = (string) ($old['client_id'] ?? '');
$selectedCarrierId = (string) ($old['carrier_contractor_id'] ?? '');
$selectedRouteExecutorId = (string) ($old['route_executor_id'] ?? '');
$isAgencyRoute = $currentRouteType === LinearRouteService::ROUTE_TYPE_AGENCY;

$clientNameById = [];
foreach ($clients as $clientRow) {
    $clientNameById[(int) ($clientRow['id'] ?? 0)] = (string) ($clientRow['name'] ?? 'Клиент');
}
$contractorNameById = [];
foreach ($contractors as $contractorRow) {
    $contractorNameById[(int) ($contractorRow['id'] ?? 0)] = (string) ($contractorRow['name'] ?? 'Перевозчик');
}

$customerPayments = array_values((array) ($old['customer_payments'] ?? []));
if ($customerPayments === []) {
    $customerPayments = [[
        'amount' => '',
        'payment_type' => 'Без НДС',
        'payment_due_type' => '',
        'payment_due_days' => '',
        'payment_due_days_kind' => '',
    ]];
}

$carrierPayments = array_values((array) ($old['carrier_payments'] ?? []));
if ($carrierPayments === []) {
    $carrierPayments = [[
        'amount' => '',
        'payment_type' => 'Без НДС',
        'payment_due_type' => '',
        'payment_due_days' => '',
        'payment_due_days_kind' => '',
    ]];
}

$principalRows = array_values((array) ($old['principal_rows'] ?? []));
if ($principalRows === [] && $isAgencyRoute) {
    $defaultEntityKey = '';
    if ($selectedClientId !== '') {
        $defaultEntityKey = 'client:' . $selectedClientId;
    } elseif ($selectedCarrierId !== '') {
        $defaultEntityKey = 'contractor:' . $selectedCarrierId;
    }

    $principalRows = [[
        'entity_key' => $defaultEntityKey,
        'payments' => [[
            'amount' => '',
            'payment_type' => 'Без НДС',
            'payment_due_type' => '',
            'payment_due_days' => '',
            'payment_due_days_kind' => '',
        ]],
    ]];
}

$documentDefinitions = LinearRouteService::routeDocumentDefinitions($currentRouteType ?: LinearRouteService::ROUTE_TYPE_AGENCY);

$renderPaymentRows = static function (string $scopeName, array $rows, string $domKey) use ($errorOf): void {
    foreach (array_values($rows) as $paymentIndex => $payment) {
        $fieldBase = $scopeName . '[' . $paymentIndex . ']';
        $errorBase = str_replace(['[', ']'], ['.', ''], $fieldBase);
        $dueType = (string) ($payment['payment_due_type'] ?? '');
        $showDays = LinearRouteService::paymentDueTypeRequiresDays($dueType);
        ?>
        <div class="linear-trip-payment-row" data-payment-row>
            <div class="field<?= $errorOf($errorBase . '.amount') !== '' ? ' is-error' : '' ?>">
                <label class="field-label">Сумма <span class="req">*</span></label>
                <input type="text" name="<?= e($fieldBase) ?>[amount]" value="<?= e((string) ($payment['amount'] ?? '')) ?>" class="field-input" inputmode="numeric" placeholder="125000">
                <div class="field-msg"><?= e($errorOf($errorBase . '.amount')) ?></div>
            </div>

            <div class="field<?= $errorOf($errorBase . '.payment_type') !== '' ? ' is-error' : '' ?>">
                <label class="field-label">Тип оплаты <span class="req">*</span></label>
                <select name="<?= e($fieldBase) ?>[payment_type]" class="field-input">
                    <?php foreach (LinearRouteService::PAYMENT_TYPES as $paymentType): ?>
                    <option value="<?= e($paymentType) ?>" <?= ((string) ($payment['payment_type'] ?? 'Без НДС') === $paymentType) ? 'selected' : '' ?>><?= e($paymentType) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="field-msg"><?= e($errorOf($errorBase . '.payment_type')) ?></div>
            </div>

            <div class="field<?= $errorOf($errorBase . '.payment_due_type') !== '' ? ' is-error' : '' ?>">
                <label class="field-label">Срок оплаты <span class="req">*</span></label>
                <select name="<?= e($fieldBase) ?>[payment_due_type]" class="field-input" data-payment-due-type>
                    <option value="">— Выберите срок —</option>
                    <?php foreach (LinearRouteService::PAYMENT_DUE_TYPES as $dueType): ?>
                    <option value="<?= e($dueType) ?>" <?= ($dueType === (string) ($payment['payment_due_type'] ?? '')) ? 'selected' : '' ?>><?= e($dueType) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="field-msg"><?= e($errorOf($errorBase . '.payment_due_type')) ?></div>
            </div>

            <div class="field<?= $errorOf($errorBase . '.payment_due_days') !== '' ? ' is-error' : '' ?><?= $showDays ? '' : ' is-hidden' ?>" data-payment-due-days-wrapper>
                <label class="field-label">Дней <span class="req">*</span></label>
                <input type="number" min="1" name="<?= e($fieldBase) ?>[payment_due_days]" value="<?= e((string) ($payment['payment_due_days'] ?? '')) ?>" class="field-input">
                <div class="field-msg"><?= e($errorOf($errorBase . '.payment_due_days')) ?></div>
            </div>

            <div class="field<?= $errorOf($errorBase . '.payment_due_days_kind') !== '' ? ' is-error' : '' ?><?= $showDays ? '' : ' is-hidden' ?>" data-payment-due-days-kind-wrapper>
                <label class="field-label">Тип дней <span class="req">*</span></label>
                <select name="<?= e($fieldBase) ?>[payment_due_days_kind]" class="field-input">
                    <option value="">— Выберите тип —</option>
                    <?php foreach (LinearRouteService::PAYMENT_DUE_DAYS_KINDS as $daysKind => $daysLabel): ?>
                    <option value="<?= e($daysKind) ?>" <?= ((string) ($payment['payment_due_days_kind'] ?? '') === $daysKind) ? 'selected' : '' ?>><?= e($daysLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="field-msg"><?= e($errorOf($errorBase . '.payment_due_days_kind')) ?></div>
            </div>

            <button type="button" class="linear-trip-row-remove<?= count($rows) > 1 ? '' : ' is-hidden' ?>" data-remove-payment-row aria-label="Удалить оплату">×</button>
        </div>
        <?php
    }
};
?>
<form method="post" action="<?= e($routeFormAction) ?>" class="<?= e($routeFormClass) ?>" id="<?= e($routeFormId) ?>" enctype="multipart/form-data" data-linear-trip-form>
    <div class="entity-form-layout driver-layout linear-trip-layout">
        <div class="entity-form-main driver-layout-main linear-trip-layout-main">
            <?php if (!empty($formError)): ?>
            <div class="form-alert alert-error">
                <div class="alert-body">
                    <div class="alert-body-title">Ошибка при сохранении</div>
                    <div class="alert-body-sub"><?= e($formError) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="section-title">Основное</div>

            <div class="field-row field-row-group linear-trip-row linear-trip-row--compact">
                <div class="field<?= $errorOf('route_type') !== '' ? ' is-error' : '' ?>">
                    <label class="field-label" for="<?= e($routeFormDomPrefix) ?>-route-type">Тип рейса <span class="req">*</span></label>
                    <select name="route_type" id="<?= e($routeFormDomPrefix) ?>-route-type" class="field-input" data-linear-trip-route-type>
                        <option value="">— Выберите тип —</option>
                        <option value="linear" <?= $currentRouteType === LinearRouteService::ROUTE_TYPE_LINEAR ? 'selected' : '' ?>>Линейная перевозка</option>
                        <option value="agency" <?= $currentRouteType === LinearRouteService::ROUTE_TYPE_AGENCY ? 'selected' : '' ?>>Агентский договор</option>
                    </select>
                    <div class="field-msg"><?= e($errorOf('route_type')) ?></div>
                </div>

                <div class="field<?= $errorOf('planned_loading_date') !== '' ? ' is-error' : '' ?>">
                    <label class="field-label">Плановая дата загрузки <span class="req">*</span></label>
                    <input type="text" name="planned_loading_date" value="<?= e((string) ($old['planned_loading_date'] ?? '')) ?>" class="field-input js-erp-date-picker" placeholder="дд.мм.гггг" inputmode="numeric" autocomplete="off">
                    <div class="field-msg"><?= e($errorOf('planned_loading_date')) ?></div>
                </div>

                <?php if ($routeFormMode === 'edit'): ?>
                <div class="field<?= $errorOf('planned_unloading_date') !== '' ? ' is-error' : '' ?>">
                    <label class="field-label">Плановая дата выгрузки <span class="req">*</span></label>
                    <input type="text" name="planned_unloading_date" value="<?= e((string) ($old['planned_unloading_date'] ?? '')) ?>" class="field-input js-erp-date-picker" placeholder="дд.мм.гггг" inputmode="numeric" autocomplete="off">
                    <div class="field-msg"><?= e($errorOf('planned_unloading_date')) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <div class="field-row field-row-group linear-trip-row linear-trip-row--compact">
                <div class="field<?= $errorOf('client_id') !== '' ? ' is-error' : '' ?>">
                    <label class="field-label">Заказчик <span class="req">*</span></label>
                    <select name="client_id" class="field-input">
                        <option value="">— Выберите заказчика —</option>
                        <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>" <?= $selectedClientId === (string) $client['id'] ? 'selected' : '' ?>>
                            <?= e($client['name']) ?><?= !empty($client['inn']) ? ' · ИНН ' . e($client['inn']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-msg"><?= e($errorOf('client_id')) ?></div>
                </div>

                <div class="field<?= $errorOf('carrier_contractor_id') !== '' ? ' is-error' : '' ?>">
                    <label class="field-label">Перевозчик <span class="req">*</span></label>
                    <select name="carrier_contractor_id" class="field-input">
                        <option value="">— Выберите перевозчика —</option>
                        <?php foreach ($contractors as $contractor): ?>
                        <option value="<?= (int) $contractor['id'] ?>" <?= $selectedCarrierId === (string) $contractor['id'] ? 'selected' : '' ?>>
                            <?= e($contractor['name']) ?><?= !empty($contractor['inn']) ? ' · ИНН ' . e($contractor['inn']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-msg"><?= e($errorOf('carrier_contractor_id')) ?></div>
                </div>
            </div>

            <div class="field-row field-row-group linear-trip-row linear-trip-row--compact">
                <div class="field<?= $errorOf('route_executor_id') !== '' ? ' is-error' : '' ?>">
                    <label class="field-label">Исполнитель рейса <span class="req">*</span></label>
                    <select name="route_executor_id" class="field-input">
                        <option value="">— Выберите исполнителя рейса —</option>
                        <?php foreach ($routeExecutors as $executor): ?>
                            <?php
                            $executorLabel = trim((string) (($executor['contractor_name'] ?? '') . ' · ' . ($executor['driver_name'] ?? '')));
                            $plates = trim((string) ($executor['primary_plate'] ?? ''));
                            if (!empty($executor['secondary_plate'])) {
                                $plates .= ' + ' . $executor['secondary_plate'];
                            }
                            ?>
                            <option value="<?= (int) $executor['id'] ?>" <?= $selectedRouteExecutorId === (string) $executor['id'] ? 'selected' : '' ?>>
                                <?= e($executorLabel) ?><?= $plates !== '' ? ' · ' . e($plates) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-msg"><?= e($errorOf('route_executor_id')) ?></div>
                </div>

                <div class="field<?= $errorOf('cargo_type_name') !== '' ? ' is-error' : '' ?>">
                    <label class="field-label">Тип груза <span class="req">*</span></label>
                    <div class="linear-trip-lookup">
                        <input type="text" name="cargo_type_name" value="<?= e((string) ($old['cargo_type_name'] ?? '')) ?>" class="field-input" autocomplete="off" placeholder="Начните вводить тип груза" data-linear-trip-cargo-input>
                        <div class="custom-doc-suggestions" data-linear-trip-cargo-suggestions></div>
                    </div>
                    <div class="field-msg"><?= e($errorOf('cargo_type_name')) ?></div>
                </div>
            </div>

            <?php if ($showActualDates): ?>
            <div class="field-row field-row-group linear-trip-row linear-trip-row--compact">
                <div class="field<?= $errorOf('actual_loading_date') !== '' ? ' is-error' : '' ?>">
                    <label class="field-label">Фактическая дата загрузки</label>
                    <input type="text" name="actual_loading_date" value="<?= e((string) ($old['actual_loading_date'] ?? '')) ?>" class="field-input js-erp-date-picker" placeholder="дд.мм.гггг" inputmode="numeric" autocomplete="off">
                    <div class="field-msg"><?= e($errorOf('actual_loading_date')) ?></div>
                </div>

                <div class="field<?= $errorOf('actual_unloading_date') !== '' ? ' is-error' : '' ?>">
                    <label class="field-label">Фактическая дата выгрузки</label>
                    <input type="text" name="actual_unloading_date" value="<?= e((string) ($old['actual_unloading_date'] ?? '')) ?>" class="field-input js-erp-date-picker" placeholder="дд.мм.гггг" inputmode="numeric" autocomplete="off">
                    <div class="field-msg"><?= e($errorOf('actual_unloading_date')) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="section-title">Финансовые условия</div>

            <div class="linear-trip-payment-card">
                <div class="linear-trip-block-head">
                    <div>
                        <div class="linear-trip-block-title">Заказчик</div>
                        <div class="field-msg"><?= e($errorOf('customer_payments')) ?></div>
                    </div>
                    <button type="button" class="btn btn-ghost" data-add-payment-row="customer_payments">+ Добавить оплату</button>
                </div>
                <div class="linear-trip-payment-rows" data-payment-container="customer_payments">
                    <?php $renderPaymentRows('customer_payments', $customerPayments, 'customer'); ?>
                </div>
            </div>

            <div class="linear-trip-payment-card">
                <div class="linear-trip-block-head">
                    <div>
                        <div class="linear-trip-block-title">Перевозчик</div>
                        <div class="field-msg"><?= e($errorOf('carrier_payments')) ?></div>
                    </div>
                    <button type="button" class="btn btn-ghost" data-add-payment-row="carrier_payments">+ Добавить оплату</button>
                </div>
                <div class="linear-trip-payment-rows" data-payment-container="carrier_payments">
                    <?php $renderPaymentRows('carrier_payments', $carrierPayments, 'carrier'); ?>
                </div>
            </div>

            <div class="linear-trip-principals-section is-agency-only<?= $isAgencyRoute ? '' : ' is-hidden' ?>">
                <div class="section-title">Принциалы</div>
                <div class="field-msg"><?= e($errorOf('principal_rows')) ?></div>
                <div class="linear-trip-principal-cards" data-principal-container>
                    <?php foreach ($principalRows as $principalIndex => $principalRow): ?>
                        <?php
                        $principalPayments = array_values((array) ($principalRow['payments'] ?? []));
                        if ($principalPayments === []) {
                            $principalPayments = [[
                                'amount' => '',
                                'payment_type' => 'Без НДС',
                                'payment_due_type' => '',
                                'payment_due_days' => '',
                                'payment_due_days_kind' => '',
                            ]];
                        }
                        ?>
                    <div class="linear-trip-principal-card" data-principal-row>
                        <div class="linear-trip-block-head">
                            <div>
                                <div class="linear-trip-block-title">Принципал</div>
                                <div class="field-msg"><?= e($errorOf('principal_rows.' . $principalIndex . '.payments')) ?></div>
                            </div>
                            <button type="button" class="linear-trip-row-remove<?= count($principalRows) > 1 ? '' : ' is-hidden' ?>" data-remove-principal-row aria-label="Удалить принципала">×</button>
                        </div>

                        <div class="field<?= $errorOf('principal_rows.' . $principalIndex . '.entity_key') !== '' ? ' is-error' : '' ?>">
                            <label class="field-label">Юрлицо принципала <span class="req">*</span></label>
                            <select name="principal_rows[<?= (int) $principalIndex ?>][entity_key]" class="field-input" data-principal-entity-select>
                                <option value="">— Выберите юрлицо —</option>
                                <?php if ($selectedClientId !== ''): ?>
                                <option value="client:<?= e($selectedClientId) ?>" <?= ((string) ($principalRow['entity_key'] ?? '') === 'client:' . $selectedClientId) ? 'selected' : '' ?>>
                                    Заказчик: <?= e($clientNameById[(int) $selectedClientId] ?? 'Клиент') ?>
                                </option>
                                <?php endif; ?>
                                <?php if ($selectedCarrierId !== ''): ?>
                                <option value="contractor:<?= e($selectedCarrierId) ?>" <?= ((string) ($principalRow['entity_key'] ?? '') === 'contractor:' . $selectedCarrierId) ? 'selected' : '' ?>>
                                    Перевозчик: <?= e($contractorNameById[(int) $selectedCarrierId] ?? 'Перевозчик') ?>
                                </option>
                                <?php endif; ?>
                            </select>
                            <div class="field-msg"><?= e($errorOf('principal_rows.' . $principalIndex . '.entity_key')) ?></div>
                        </div>

                        <div class="linear-trip-payment-card linear-trip-payment-card--nested">
                            <div class="linear-trip-block-head">
                                <div class="linear-trip-block-title">Оплаты принципала</div>
                                <button type="button" class="btn btn-ghost" data-add-principal-payment-row>+ Добавить оплату</button>
                            </div>
                            <div class="linear-trip-payment-rows" data-principal-payment-container>
                                <?php $renderPaymentRows('principal_rows[' . $principalIndex . '][payments]', $principalPayments, 'principal-' . $principalIndex); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn btn-ghost" data-add-principal-row>+ Добавить принципала</button>
            </div>

            <div class="section-title">Комментарий</div>
            <div class="field field-w-comment">
                <textarea name="comments" class="field-textarea linear-trip-textarea" rows="4" placeholder="Дополнительная информация по рейсу"><?= e((string) ($old['comments'] ?? '')) ?></textarea>
                <div class="field-msg"></div>
            </div>
        </div>

        <div class="entity-form-docs driver-layout-docs linear-trip-layout-docs">
            <div>
                <div class="section-title">Документы</div>
                <div class="field-msg">
                    <?= $routeFormMode === 'edit'
                        ? 'Можно заменить существующие файлы или добавить недостающие.'
                        : 'Файлы прикрепляются к карточке рейса сразу после успешного создания.' ?>
                </div>
            </div>

            <div class="file-list">
                <?php foreach ($documentDefinitions as $inputName => $meta): ?>
                    <?php
                    $existingDocs = $existingDocsByCode[$inputName] ?? [];
                    $existingDoc = $existingDocs[0] ?? null;
                    $rowState = DocumentService::detectDocumentBadge(
                        $existingDoc['original_name'] ?? $existingDoc['stored_name'] ?? null,
                        $existingDoc['mime_type'] ?? null
                    );
                    $hasExistingDoc = $existingDoc !== null;
                    $badgeClass = $hasExistingDoc ? 'file-type-badge ' . $rowState['badge_class'] : 'file-type-badge file-type-badge-empty';
                    $badgeText = $hasExistingDoc ? $rowState['badge_text'] : '—';
                    $fileMeta = $hasExistingDoc
                        ? (string) ($existingDoc['original_name'] ?? $existingDoc['stored_name'] ?? 'Файл')
                        : ($inputName === 'principal_document' ? 'Документ доступен только для агентского рейса.' : 'Файл не выбран');
                    ?>
                <div class="file-item file-item-predef document-file-row<?= $hasExistingDoc ? ' has-file has-existing-file' : ' is-empty' ?><?= $inputName === 'principal_document' ? ' is-agency-only' . ($isAgencyRoute ? '' : ' is-hidden') : '' ?>">
                    <div class="<?= e($badgeClass) ?>"><?= e($badgeText) ?></div>
                    <div class="file-info">
                        <div class="file-name"><?= e($meta['name']) ?></div>
                        <div class="file-meta"><?= e($fileMeta) ?></div>
                        <?php if ($hasExistingDoc): ?>
                        <div class="driver-doc-meta">
                            <a href="/company/documents/view?id=<?= (int) $existingDoc['id'] ?>" target="_blank" rel="noopener" class="js-doc-popup-window">Просмотр</a>
                            <a href="/company/documents/download?id=<?= (int) $existingDoc['id'] ?>" download>Скачать</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <label class="btn btn-secondary file-action-btn">
                        <span><?= $hasExistingDoc ? 'Заменить' : 'Выбрать' ?></span>
                        <input type="file" name="<?= e($inputName) ?>" class="file-input-hidden">
                    </label>
                </div>
                <?php endforeach; ?>

                <?php if ($routeFormMode === 'edit' && !empty($existingDocsByCode['other'])): ?>
                    <?php foreach ($existingDocsByCode['other'] as $otherDocument): ?>
                        <?php $otherState = DocumentService::detectDocumentBadge($otherDocument['original_name'] ?? $otherDocument['stored_name'] ?? null, $otherDocument['mime_type'] ?? null); ?>
                <div class="file-item file-item-predef document-file-row has-file has-existing-file">
                    <div class="file-type-badge <?= e($otherState['badge_class']) ?>"><?= e($otherState['badge_text']) ?></div>
                    <div class="file-info">
                        <div class="file-name"><?= e($otherDocument['document_type'] ?? $otherDocument['type_name'] ?? 'Документ') ?></div>
                        <div class="file-meta"><?= e($otherDocument['original_name'] ?? $otherDocument['stored_name'] ?? 'Файл') ?></div>
                        <div class="driver-doc-meta">
                            <a href="/company/documents/view?id=<?= (int) $otherDocument['id'] ?>" target="_blank" rel="noopener" class="js-doc-popup-window">Просмотр</a>
                            <a href="/company/documents/download?id=<?= (int) $otherDocument['id'] ?>" download>Скачать</a>
                            <a href="/company/documents/upload?entity_type=linear_route&entity_id=<?= (int) ($old['id'] ?? 0) ?>&replace=<?= (int) $otherDocument['id'] ?>">Заменить</a>
                        </div>
                    </div>
                </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="section-title section-title--inner">Произвольные документы</div>
                <div class="field-msg">Введите название документа и выберите файл.</div>
                <div class="file-list" data-custom-docs-container></div>
                <button type="button" class="btn btn-ghost" data-add-custom-doc-btn>+ Добавить документ</button>
            </div>
        </div>
    </div>
</form>
