from pathlib import Path

source_path = Path(__file__).with_name('P17R_apply_zero_byte_document_fix.py')
source = source_path.read_text(encoding='utf-8')
old = '''client_anchor = """    $_FILES['custom_doc_file']['name'] = [];
"""'''
new = '''client_anchor = """    }
    $_FILES['custom_doc_file']['name'] = [];

    if (!empty($_FILES['custom_doc_file']['name']) && is_array($_FILES['custom_doc_file']['name'])) {
"""'''
if source.count(old) != 1:
    raise RuntimeError(f'P17R zero-byte v2 anchor mismatch: {source.count(old)}')
source = source.replace(old, new)
old_code = '''    $_FILES['custom_doc_file']['name'] = [];
"""'''
new_code = '''    }

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
# Replace only the client_code payload, not unrelated source text.
marker = 'client_code = """'
pos = source.find(marker)
end = source.find('"""\nreplace_once(client_path', pos)
if pos < 0 or end < 0:
    raise RuntimeError('P17R zero-byte v2 client_code block missing')
block = source[pos:end]
if block.count(old_code) != 1:
    raise RuntimeError(f'P17R zero-byte v2 client_code anchor mismatch: {block.count(old_code)}')
block = block.replace(old_code, new_code)
source = source[:pos] + block + source[end:]
exec(compile(source, str(source_path), 'exec'), {'__name__': '__main__', '__file__': str(source_path)})
