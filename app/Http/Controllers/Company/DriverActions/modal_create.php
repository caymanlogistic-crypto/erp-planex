<?php
/** @var DriverService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
require_once base_path('app/Support/driver_create_handler.php');
handleCompanyDriverCreate($config, $db, 'modal');
