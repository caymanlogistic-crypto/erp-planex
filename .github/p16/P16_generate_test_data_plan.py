from __future__ import annotations

import json
from pathlib import Path

plan = {
    "prompt": "P16",
    "tenant_id": 27,
    "company": "UIUX TEST EXPEDITOR",
    "prefix": "UIUX_P16_",
    "idempotency": {
        "strategy": "Find by deterministic test_id in tenant 27; create only when absent; update only matching UIUX_P16_ records.",
        "production_company_mutation": False,
        "cleanup": "Use application UI archive/delete only for UIUX_P16_ records after P17 owner review."
    },
    "records": []
}


def add(entity, purposes, owner, visibility, statuses="active", related=None, scenarios=None):
    if isinstance(statuses, str):
        statuses = [statuses] * len(purposes)
    if scenarios is None:
        scenarios = ["create/reopen/update/search/filter/sort"] * len(purposes)
    for index, purpose in enumerate(purposes, 1):
        plan["records"].append({
            "entity": entity,
            "test_id": f"UIUX_P16_{entity.upper()}_{index:02d}",
            "purpose": purpose,
            "owner_role": owner,
            "expected_visibility": visibility,
            "status": statuses[index - 1],
            "related_entities": related or [],
            "ui_state": "populated",
            "functional_scenario": scenarios[index - 1],
            "cleanup_policy": "retain through P17; remove only after owner review"
        })


add("client", [
    "short LLC", "very long legal name", "sole proprietor", "multiple contacts",
    "multiple phones", "multiple emails", "long legal address", "long actual address",
    "long comment", "fully populated", "minimum valid", "documents/invoices/trips"
], "COMPANY_OWNER", "OWNER/SENIOR; assigned LOGIST according to grants",
   ["active"] * 10 + ["archived", "restored"])

add("contractor", [
    "carrier", "processor", "sole proprietor", "LLC", "short name", "long name",
    "multiple phones", "director", "bank details", "long address", "linked drivers/vehicles",
    "documents/archive/restore"
], "COMPANY_OWNER", "OWNER/SENIOR; assigned LOGIST according to grants",
   ["active"] * 10 + ["archived", "restored"])

add("driver", [
    "short name", "long name", "multiple phones", "full data", "minimum valid", "documents",
    "carrier A", "carrier B", "assigned owner", "archived", "restored", "in trip/no trip"
], "COMPANY_OWNER", "OWNER/SENIOR; assigned LOGIST according to grants",
   ["active"] * 9 + ["archived", "restored", "active"])

add("vehicle", [
    "tractor", "trailer", "single vehicle", "short make", "long make/model", "free", "busy",
    "with documents", "without optional docs", "active", "archived", "restored"
], "COMPANY_OWNER", "OWNER/SENIOR; assigned LOGIST according to grants",
   ["active"] * 10 + ["archived", "restored"])

add("vehicle_set", ["tractor+trailer", "single vehicle set", "long model set", "restored set"],
    "COMPANY_OWNER", "OWNER/SENIOR; assigned LOGIST according to grants",
    ["active", "active", "active", "restored"], ["UIUX_P16_VEHICLE_*"])

add("crew", ["driver+vehicle A", "driver+vehicle B", "busy crew", "restored crew"],
    "COMPANY_OWNER", "OWNER/SENIOR; assigned LOGIST according to grants",
    ["active", "active", "active", "restored"], ["UIUX_P16_DRIVER_*", "UIUX_P16_VEHICLE_*"])

add("trip", [
    "planned short", "planned long name", "one application", "many applications", "logist 1",
    "logist 2", "customer prepayment", "customer after documents", "carrier working days",
    "future dates", "past/overdue finance", "vehicle set", "documents", "active populated",
    "archive/restore"
], "COMPANY_OWNER", "OWNER/SENIOR; assigned or granted LOGIST only",
   ["active"] * 14 + ["restored"],
   ["UIUX_P16_CLIENT_*", "UIUX_P16_CONTRACTOR_*", "UIUX_P16_DRIVER_*", "UIUX_P16_VEHICLE_SET_*"])

add("bank_account", ["primary synthetic account", "secondary synthetic account"],
    "COMPANY_OWNER", "OWNER only")

add("cash_operation", [
    "income 1", "income 2", "income 3", "income 4", "income 5",
    "expense 1", "expense 2", "expense 3", "expense 4", "expense 5"
], "COMPANY_OWNER", "OWNER only", ["posted"] * 9 + ["cancelled"], ["UIUX_P16_DDS_CATEGORY_*"])

add("invoice", [
    "outgoing unpaid", "incoming unpaid", "outgoing partial", "incoming partial", "outgoing paid",
    "incoming paid", "overdue", "future", "trip-linked", "client-linked", "contractor-linked",
    "long comment"
], "COMPANY_OWNER", "OWNER only",
   ["unpaid", "unpaid", "partial", "partial", "paid", "paid", "overdue", "future", "unpaid", "paid", "partial", "unpaid"],
   ["UIUX_P16_TRIP_*", "UIUX_P16_CLIENT_*", "UIUX_P16_CONTRACTOR_*"])

add("bank_operation", [
    "income", "expense", "unallocated", "partially allocated", "fully allocated",
    "unknown counterparty", "long purpose", "invoice-linked", "trip-linked", "cancelled",
    "income 2", "expense 2", "unallocated 2", "partial 2", "full 2"
], "COMPANY_OWNER", "OWNER only", ["posted"] * 9 + ["cancelled"] + ["posted"] * 5,
   ["UIUX_P16_INVOICE_*", "UIUX_P16_TRIP_*"])

add("dds_category", [
    "transport income", "carrier expense", "fuel", "salary", "tax", "cash transfer",
    "other income", "other expense"
], "COMPANY_OWNER", "OWNER only")

add("matching_rule", ["invoice number", "counterparty INN", "payment purpose", "trip reference"],
    "COMPANY_OWNER", "OWNER only")

add("document", [
    "PDF", "PNG", "JPG", "XLSX", "long Russian filename", "filename with spaces",
    "replacement candidate", "soft-delete/restore candidate"
], "COMPANY_OWNER", "entity-role access", "active",
   ["UIUX_P16_CLIENT_12", "UIUX_P16_CONTRACTOR_12", "UIUX_P16_DRIVER_06", "UIUX_P16_TRIP_13"])

Path("P16_01_TEST_DATA_PLAN.json").write_text(
    json.dumps(plan, ensure_ascii=False, indent=2) + "\n",
    encoding="utf-8",
    newline="\n"
)
print(f"P16_DATA_PLAN=PASS records={len(plan['records'])}")
