<?php

require_once base_path('app/Http/Controllers/Company/ContractorController.php');

$controller = new \App\Http\Controllers\Company\ContractorController($config, $db);

// --- Contractor list ---
$router->get('/company/contractors', [$controller, 'index']);

// --- Contractor create (full page + modal) ---
$router->get('/company/contractors/create', [$controller, 'createForm']);
$router->post('/company/contractors/create', [$controller, 'createSubmit']);

// --- INN autofill ---
$router->get('/company/requisites/lookup-by-inn', [$controller, 'lookupInn']);
$router->post('/company/requisites/lookup-by-inn', [$controller, 'lookupInn']);

// --- Contractor + Driver + Vehicle (full create) ---
$router->get('/company/contractors/create-full', [$controller, 'createFullForm']);
$router->post('/company/contractors/create-full', [$controller, 'createFullSubmit']);

// --- Add crew to existing contractor ---
$router->get('/company/contractors/{id}/add-crew', [$controller, 'addCrewForm']);
$router->post('/company/contractors/{id}/add-crew', [$controller, 'addCrewSubmit']);

// --- Contractor view ---
$router->get('/company/contractors/{id}', [$controller, 'show']);

// --- Contractor edit (full page) ---
$router->get('/company/contractors/{id}/edit', [$controller, 'editForm']);
$router->post('/company/contractors/{id}/edit', [$controller, 'editSubmit']);

// --- Contractor archive (full page) ---
$router->post('/company/contractors/{id}/archive', [$controller, 'archive']);

// --- Contractor modal view/edit/archive ---
$router->get('/company/contractors/{id}/modal-view', [$controller, 'modalView']);
$router->get('/company/contractors/{id}/modal-edit', [$controller, 'modalEditForm']);
$router->post('/company/contractors/{id}/modal-edit', [$controller, 'modalEditSubmit']);
$router->post('/company/contractors/{id}/modal-archive', [$controller, 'modalArchive']);

// --- Contractor contacts CRUD ---
$router->post('/company/contractors/{contractor_id}/contacts/create', [$controller, 'contactCreate']);
$router->post('/company/contractors/{contractor_id}/contacts/{contact_id}/edit', [$controller, 'contactEdit']);
$router->post('/company/contractors/{contractor_id}/contacts/{contact_id}/delete', [$controller, 'contactDelete']);
$router->post('/company/contractors/{contractor_id}/contacts/{contact_id}/set-primary', [$controller, 'contactSetPrimary']);
$router->post('/company/contractors/{contractor_id}/contacts/{contact_id}/set-document-email', [$controller, 'contactSetDocumentEmail']);

// --- Contractor tax history ---
$router->post('/company/contractors/{contractor_id}/tax-history/create', [$controller, 'taxHistoryCreate']);
