# ERP PLANEX — DATABASE_DRAFT

## Статус

Черновик. Не является финальной схемой БД.

Цель — зафиксировать основные сущности для будущего проектирования.

---

## Центральная БД SUPERADMIN

### companies

```text
id
key
name
folder_path
db_name
storage_path
is_active
created_at
updated_at
```

### features

```text
id
code
name
type
description
is_active
created_at
updated_at
```

### company_features

```text
id
company_id
feature_code
is_enabled
enabled_from
enabled_until
created_at
updated_at
```

### superadmin_users

```text
id
name
email
password_hash
is_active
created_at
updated_at
```

---

## Локальная БД компании

### company_profile

```text
id
name
short_name
entity_type          # legal_entity / ip
tax_system
vat_mode
international_transport_allowed
created_at
updated_at
```

### company_requisites_versions

```text
id
company_profile_id
valid_from
valid_to
requisites_json
comment
created_at
updated_at
```

### users

```text
id
name
email
password_hash
role_id
is_active
created_at
updated_at
```

### roles

```text
id
code
name
description
is_system
created_at
updated_at
```

### permissions

```text
id
code
name
description
created_at
updated_at
```

### role_permissions

```text
id
role_id
permission_code
is_allowed
created_at
updated_at
```

### local_feature_cache

```text
id
feature_code
is_enabled
synced_at
```

---

## Контрагенты

### clients

```text
id
name
short_name
entity_type          # legal_entity / ip
tax_system
vat_mode
is_active
created_at
updated_at
```

### contractors

```text
id
name
short_name
entity_type          # legal_entity / ip
tax_system
vat_mode
is_active
created_at
updated_at
```

---

## Подрядчик: водители, машины, экипажи

### contractor_drivers

```text
id
contractor_id
full_name
phone
license_data
is_active
created_at
updated_at
```

### contractor_vehicles

```text
id
contractor_id
plate_number
vehicle_type
vehicle_data
is_active
created_at
updated_at
```

### crews

```text
id
contractor_id
driver_id
vehicle_id
name
is_active
created_at
updated_at
```

Важно:
Экипаж фиксирует жёсткую пару водитель + машина внутри подрядчика.

---

## Документы

### document_types

```text
id
code
name
applies_to_entity
applies_to_trip_type
is_required
is_active
sort_order
created_at
updated_at
```

### documents

```text
id
document_type_id
entity_type
entity_id
file_path
original_filename
mime_type
file_size
status              # uploaded / verified / rejected
uploaded_by
uploaded_at
verified_by
verified_at
rejected_by
rejected_at
reject_reason
metadata_json
created_at
updated_at
```

На первом этапе статусы можно зарезервировать, но не реализовывать глубоко.

---

## Шаблоны и версии

### document_templates

```text
id
code
name
template_type
file_path
is_active
created_at
updated_at
```

### document_template_versions

```text
id
template_id
version_number
valid_from
valid_to
file_path
metadata_json
created_at
updated_at
```

---

## Заявки и рейсы

### client_requests

```text
id
client_id
request_number
status
created_by
created_at
updated_at
```

### trips

```text
id
trip_number
trip_type              # linear / consolidated_reserved
client_request_id
client_id
contractor_id
crew_id
loading_address
unloading_address
conditions_text
status
contractor_name_snapshot
driver_name_snapshot
vehicle_plate_snapshot
created_by
created_at
updated_at
```

Первый этап:
- реализуется только `linear`;
- `consolidated_reserved` не реализуется функционально.
