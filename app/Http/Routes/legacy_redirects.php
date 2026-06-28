<?php

$router->get('/company/crews', function () {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    header('Location: /company/route-executors', true, 302);
    exit;
});

$router->get('/company/crews/create', function () {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    header('Location: /company/route-executors/create', true, 302);
    exit;
});

$router->get('/company/driver-vehicle-blocks', function () {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    header('Location: /company/route-executors', true, 302);
    exit;
});

$router->get('/company/driver-vehicle-blocks/create', function () {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    header('Location: /company/route-executors/create', true, 302);
    exit;
});

$router->get('/company/contractor-assignments', function () {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    header('Location: /company/responsible-assignments', true, 302);
    exit;
});
