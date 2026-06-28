# ROUTE_MAP_AFTER_E7

Generated from `app/Http/Routes/*.php` after the E7 index split.

## auth.php
- `GET /login`
- `POST /login`
- `GET /logout`

## company_clients.php
- `GET /company/clients`
- `GET /company/clients/create`
- `POST /company/clients/create`
- `GET /company/clients/{id}`
- `GET /company/clients/{id}/edit`
- `POST /company/clients/{id}/edit`
- `POST /company/clients/{id}/archive`
- `GET /company/clients/{id}/modal-view`
- `GET /company/clients/{id}/modal-edit`
- `POST /company/clients/{id}/modal-edit`
- `POST /company/clients/{id}/modal-archive`

## company_contractor_assignments.php
- `GET /company/contractor-assignments`
- `POST /company/contractor-assignments/{id}/assign`

## company_contractors.php
- `GET /company/contractors`
- `GET /company/contractors/create`
- `POST /company/requisites/lookup-by-inn`
- `GET /company/contractors/create-full`
- `POST /company/contractors/create`
- `POST /company/contractors/create-full`
- `GET /company/contractors/{id}/add-crew`
- `POST /company/contractors/{id}/add-crew`
- `GET /company/contractors/{id}`
- `GET /company/contractors/{id}/edit`
- `POST /company/contractors/{id}/edit`
- `POST /company/contractors/{id}/archive`
- `GET /company/contractors/{id}/modal-view`
- `GET /company/contractors/{id}/modal-edit`
- `POST /company/contractors/{id}/modal-edit`
- `POST /company/contractors/{id}/modal-archive`
- `POST /company/contractors/{contractor_id}/contacts/create`
- `POST /company/contractors/{contractor_id}/contacts/{contact_id}/edit`
- `POST /company/contractors/{contractor_id}/contacts/{contact_id}/delete`
- `POST /company/contractors/{contractor_id}/contacts/{contact_id}/set-primary`
- `POST /company/contractors/{contractor_id}/contacts/{contact_id}/set-document-email`
- `POST /company/contractors/{contractor_id}/tax-history/create`

## company_crews_legacy.php
- `GET /company/crews`
- `GET /company/crews/create`
- `POST /company/crews/create`
- `GET /company/crews/{id}`
- `GET /company/crews/{id}/edit`
- `POST /company/crews/{id}/edit`
- `POST /company/crews/{id}/archive`

## company_dashboard.php
- `GET /company/dashboard`
- `POST /company/access-grants/grant`
- `POST /company/access-grants/{id}/revoke`

## company_documents.php
- `GET /company/documents`
- `GET /company/documents/upload`
- `POST /company/documents/upload`
- `GET /company/documents/download`
- `GET /company/documents/view`
- `POST /company/documents/delete`
- `POST /company/documents/replace`
- `GET /company/document-types`
- `GET /company/document-types/create`
- `POST /company/document-types/create`
- `GET /company/document-types/{id}/edit`
- `POST /company/document-types/{id}/edit`
- `POST /company/document-types/{id}/delete`

## company_driver_vehicle_blocks_legacy.php
- `GET /company/driver-vehicle-blocks`
- `GET /company/driver-vehicle-blocks/create`
- `POST /company/driver-vehicle-blocks/create`
- `GET /company/driver-vehicle-blocks/{id}`
- `GET /company/driver-vehicle-blocks/{id}/edit`
- `POST /company/driver-vehicle-blocks/{id}/edit`
- `POST /company/driver-vehicle-blocks/{id}/archive`

## company_drivers.php
- `GET /company/drivers`
- `GET /company/drivers/create`
- `POST /company/drivers/create`
- `POST /company/drivers/modal-create`
- `GET /company/drivers/{id}`
- `GET /company/drivers/{id}/edit`
- `POST /company/drivers/{id}/edit`
- `POST /company/drivers/{id}/archive`
- `GET /company/drivers/{id}/modal-view`
- `GET /company/drivers/{id}/modal-edit`
- `POST /company/drivers/{id}/modal-edit`
- `POST /company/drivers/{id}/modal-delete`
- `POST /company/drivers/{driver_id}/phones/create`
- `POST /company/drivers/{driver_id}/phones/{phone_id}/edit`
- `POST /company/drivers/{driver_id}/phones/{phone_id}/delete`
- `POST /company/drivers/{driver_id}/phones/{phone_id}/set-main`

## company_logists.php
- `GET /company/logists`
- `GET /company/logists/create`
- `POST /company/logists/create`
- `GET /company/logists/{id}`
- `GET /company/logists/{id}/edit`
- `POST /company/logists/{id}/edit`
- `POST /company/logists/{id}/reset-password`
- `POST /company/logists/{id}/archive`

## company_responsible_assignments.php
- `GET /company/responsible-assignments`
- `POST /company/responsible-assignments/reassign`

## company_route_executors.php
- `GET /company/route-executors`
- `GET /company/route-executors/create`
- `POST /company/route-executors/create`
- `GET /company/route-executors/{id}`
- `GET /company/route-executors/{id}/edit`
- `POST /company/route-executors/{id}/edit`
- `POST /company/route-executors/{id}/archive`

## company_vehicle_sets.php
- `GET /company/vehicle-sets`
- `GET /company/vehicle-sets/create`
- `POST /company/vehicle-sets/create`
- `GET /company/vehicle-sets/{id}/modal-view`
- `GET /company/vehicle-sets/{id}/modal-edit`
- `POST /company/vehicle-sets/{id}/modal-edit`
- `POST /company/vehicle-sets/{id}/modal-delete`
- `GET /company/vehicle-sets/{id}`
- `GET /company/vehicle-sets/{id}/edit`
- `POST /company/vehicle-sets/{id}/edit`
- `POST /company/vehicle-sets/{id}/archive`

## company_vehicles.php
- `GET /company/vehicles`
- `GET /company/vehicles/create`
- `POST /company/vehicles/create`
- `GET /company/vehicles/{id}`
- `GET /company/vehicles/{id}/edit`
- `POST /company/vehicles/{id}/edit`
- `POST /company/vehicles/{id}/archive`

## core.php
- `GET /favicon.ico`
- `GET /`
- `GET /dev/ui-foundation`
- `GET /test`
- `GET /test-db`

## superadmin.php
- `GET /superadmin/companies`
- `GET /superadmin/companies/create`
- `POST /superadmin/companies/create`
- `GET /superadmin/companies/{id}`
- `GET /superadmin/companies/{id}/edit`
- `POST /superadmin/companies/{id}/edit`
- `GET /superadmin/companies/{id}/create-owner`
- `POST /superadmin/companies/{id}/create-owner`
- `GET /superadmin/companies/{id}/owner`
- `GET /superadmin/companies/{id}/owner/edit`
- `POST /superadmin/companies/{id}/owner/edit`
- `POST /superadmin/companies/{id}/owner/reset-password`

## superadmin_company_delete.php
- `POST /superadmin/companies/{company_id}/users/owner/{user_id}/activate`
- `POST /superadmin/companies/{company_id}/users/owner/{user_id}/block`
- `POST /superadmin/companies/{company_id}/users/owner/{user_id}/archive`
- `GET /superadmin/companies/{id}/delete`
- `POST /superadmin/companies/{id}/delete`

## superadmin_management.php
- `POST /superadmin/companies/{id}/activate`
- `POST /superadmin/companies/{id}/block`
- `POST /superadmin/companies/{id}/archive`
- `POST /superadmin/companies/{id}/deactivate`
- `GET /superadmin/companies/{id}/users`
- `GET /superadmin/companies/{id}/directories`
- `GET /superadmin/companies/{id}/clients`
- `GET /superadmin/companies/{id}/contractors`
- `GET /superadmin/companies/{id}/drivers`
- `GET /superadmin/companies/{id}/vehicles`
- `GET /superadmin/companies/{id}/crews`
- `GET /superadmin/companies/{id}/documents`
- `GET /superadmin/companies/{company_id}/documents/{document_id}/download`
- `GET /superadmin/companies/{id}/access-grants`
- `POST /superadmin/companies/{id}/access-grants/{grant_id}/revoke`
- `GET /superadmin/companies/{id}/users/logists/create`
- `GET /superadmin/companies/{company_id}/users/logists/{user_id}`
- `GET /superadmin/companies/{company_id}/users/logists/{user_id}/edit`
- `POST /superadmin/companies/{company_id}/users/logists/{user_id}/edit`
- `POST /superadmin/companies/{company_id}/users/logists/{user_id}/reset-password`
- `POST /superadmin/companies/{company_id}/users/logists/{user_id}/activate`
- `POST /superadmin/companies/{company_id}/users/logists/{user_id}/block`
- `POST /superadmin/companies/{company_id}/users/logists/{user_id}/archive`
- `POST /superadmin/companies/{id}/users/logists/create`

## legacy_redirects.php
- `GET /company/crews` -> `/company/route-executors`
- `GET /company/crews/create` -> `/company/route-executors/create`
- `GET /company/driver-vehicle-blocks` -> `/company/route-executors`
- `GET /company/driver-vehicle-blocks/create` -> `/company/route-executors/create`
- `GET /company/contractor-assignments` -> `/company/responsible-assignments`
