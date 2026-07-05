<?php

    requireRole('superadmin');

    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    $pageTitle = 'Создать экспедитора';
    $pageContext = 'Реестр компаний';
    $errors = [];
    $old = [];
    $formError = null;

    if ($isAjax) {
        $leEntityType = 'company';
        $leFormAction = '/superadmin/companies/create';
        $leFormId = 'le-sa-company-create-form';
        $leIsModal = true;
        $leOld = $old;
        $leErrors = $errors;
        $leFormError = $formError;
        $leSubmitLabel = 'Создать компанию';
        $leShowContacts = false;
        $leShowBankDetails = false;
        $leShowDocuments = false;
        $leInnLookupUrl = app_url('/superadmin/requisites/lookup-by-inn');
        require base_path('app/View/partials/legal_entity_create_form.php');
        return;
    }

    ob_start();
    require base_path('app/View/pages/superadmin_companies_create.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
