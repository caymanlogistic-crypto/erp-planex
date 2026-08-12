from pathlib import Path
p=Path('app/Service/LocalMigrationService.php')
s=p.read_text(encoding='utf-8')
needle="""            '051_restrict_finance_invoice_links_fk.sql' => [
"""
insert="""            '027_vehicle_unit_document_types.sql' => [
                'efa2e433b05ce1e2a000b175e3925590a48ccef8f8ac47d5f6c241651c778fbd',
            ],
            '039_add_soft_delete_columns.sql' => [
                '34524bfbfb0a8f83232c2076e0d1081d10c8eedc38aa5f6a69e84d2b43948038',
            ],
            '040_create_linear_routes.sql' => [
                '88586161f063688f6f1a0133e200a237e2f86320fab1d42335375d7f5ef1bb1f',
            ],
            '041_linear_route_repeatable_principals_payments.sql' => [
                '4d29448de5084d46a430b756ea2709baa9d7a77076734df989627546a0a3925f',
            ],
            '051_restrict_finance_invoice_links_fk.sql' => [
"""
if needle not in s:
    raise SystemExit('needle not found')
if "'027_vehicle_unit_document_types.sql' =>" not in s:
    s=s.replace(needle,insert,1)
p.write_text(s,encoding='utf-8')
print('P39 checksum compatibility patch applied')
