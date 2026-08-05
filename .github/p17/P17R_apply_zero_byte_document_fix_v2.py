from pathlib import Path

source_path = Path(__file__).with_name('P17R_apply_zero_byte_document_fix.py')
source = source_path.read_text(encoding='utf-8')

old_anchor = '''client_anchor = """    $_FILES['custom_doc_file']['name'] = [];
"""'''
new_anchor = '''client_anchor = """    }
    $_FILES['custom_doc_file']['name'] = [];

    if (!empty($_FILES['custom_doc_file']['name']) && is_array($_FILES['custom_doc_file']['name'])) {
"""'''
if source.count(old_anchor) != 1:
    raise RuntimeError(f'P17R zero-byte v2 anchor mismatch: {source.count(old_anchor)}')
source = source.replace(old_anchor, new_anchor)

start = source.find('client_code = """')
end_marker = '"""\nreplace_once(client_path'
end = source.find(end_marker, start)
if start < 0 or end < 0:
    raise RuntimeError('P17R zero-byte v2 client_code block missing')
end += 3
new_client_block = '''client_code = """    }

    $documentFileError = validateLegalEntityCreateDocumentFiles($legalEntityFiles);
    if ($documentFileError !== null) {
        $formError = $documentFileError;
        ob_start();
        require base_path('app/View/pages/company_clients_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $_FILES['custom_doc_file']['name'] = [];

    if (!empty($_FILES['custom_doc_file']['name']) && is_array($_FILES['custom_doc_file']['name'])) {
"""'''
source = source[:start] + new_client_block + source[end:]

exec(compile(source, str(source_path), 'exec'), {'__name__': '__main__', '__file__': str(source_path)})
