(function () {
    var allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

    function getExtension(name) {
        var parts = String(name || '').toLowerCase().split('.');
        return parts.length > 1 ? parts.pop() : '';
    }

    function getKind(name) {
        var ext = getExtension(name);
        if (ext === 'pdf') return 'PDF';
        if (['doc', 'docx'].indexOf(ext) !== -1) return 'DOC';
        if (['xls', 'xlsx'].indexOf(ext) !== -1) return 'XLS';
        if (['jpg', 'jpeg', 'png', 'webp'].indexOf(ext) !== -1) return 'IMG';
        return 'FILE';
    }

    function badgeClass(kind) {
        if (kind === 'PDF') return 'is-pdf';
        if (kind === 'DOC') return 'is-doc';
        if (kind === 'XLS') return 'is-xls';
        if (kind === 'IMG') return 'is-img';
        return 'is-other';
    }

    function fileSummary(input) {
        if (!input.files || input.files.length === 0) {
            return { text: 'Файл не выбран', badge: '—', badgeClassName: 'file-type-badge-empty', filled: false };
        }

        if (input.files.length === 1) {
            var kind = getKind(input.files[0].name);
            return {
                text: input.files[0].name,
                badge: kind,
                badgeClassName: badgeClass(kind),
                filled: true
            };
        }

        return {
            text: 'Выбрано файлов: ' + input.files.length,
            badge: 'FILE',
            badgeClassName: 'is-other',
            filled: true
        };
    }

    function paintRow(row, summary) {
        var badge = row.querySelector('.file-type-badge');
        var meta = row.querySelector('.file-meta');
        var buttonLabel = row.querySelector('.file-action-btn span');
        var clearButton = row.querySelector('.predef-file-clear, .file-remove');

        if (badge) {
            badge.className = 'file-type-badge ' + (summary.filled ? summary.badgeClassName : 'file-type-badge-empty');
            badge.textContent = summary.badge;
        }
        if (meta) {
            meta.textContent = summary.text;
            meta.classList.toggle('is-hidden', !summary.filled && row.classList.contains('custom-doc-row'));
        }
        if (buttonLabel) {
            buttonLabel.textContent = summary.filled ? 'Заменить' : 'Выбрать';
        }
        if (clearButton) {
            clearButton.classList.toggle('is-hidden', !summary.filled && clearButton.classList.contains('predef-file-clear'));
        }

        row.classList.toggle('is-empty', !summary.filled);
        row.classList.toggle('has-file', summary.filled);
    }

    function validateInputFile(input) {
        if (!input.files || input.files.length === 0) {
            return '';
        }

        var ext = getExtension(input.files[0].name);
        if (allowedExtensions.indexOf(ext) === -1) {
            return 'Недопустимый формат файла';
        }

        return '';
    }

    function setDocumentError(input, message) {
        var row = input.closest('.document-file-row');
        var titleField = row ? row.querySelector('.custom-doc-title-field') : null;
        var titleMessage = titleField ? titleField.querySelector('.field-msg') : null;
        if (row) {
            row.classList.add('is-error', 'has-error');
        }
        if (titleMessage && !titleMessage.textContent) {
            titleMessage.textContent = message;
        }
    }

    function clearDocumentError(input) {
        var row = input.closest('.document-file-row');
        if (row) {
            row.classList.remove('is-error', 'has-error');
        }
    }

    function syncCustomType(row, docTypes) {
        var hiddenType = row.querySelector('input[name="custom_doc_type[]"]');
        var customType = row.querySelector('input[name="custom_doc_type_new[]"]');
        if (!customType) {
            return;
        }

        customType.value = String(customType.value || '').trim().replace(/\s+/g, ' ');
        if (!hiddenType) {
            return;
        }

        hiddenType.value = '';
        var typed = customType.value.toLowerCase();
        for (var i = 0; i < docTypes.length; i++) {
            if (String(docTypes[i].name || '').trim().toLowerCase() === typed) {
                hiddenType.value = String(docTypes[i].name || '');
                break;
            }
        }
    }

    function bindSuggestions(row, docTypes) {
        var customType = row.querySelector('input[name="custom_doc_type_new[]"]');
        if (!customType || !docTypes.length) {
            return;
        }

        var field = row.querySelector('.custom-doc-title-field');
        var suggestions = row.querySelector('.custom-doc-suggestions');
        if (!field || !suggestions || customType.dataset.leSuggestReady === '1') {
            return;
        }
        customType.dataset.leSuggestReady = '1';

        customType.addEventListener('input', function () {
            syncCustomType(row, docTypes);
            field.classList.remove('is-error');
            var titleMessage = field.querySelector('.field-msg');
            if (titleMessage) {
                titleMessage.textContent = '';
            }

            var query = String(customType.value || '').trim().toLowerCase();
            if (!query) {
                suggestions.innerHTML = '';
                suggestions.classList.remove('is-open');
                return;
            }

            var matched = docTypes.filter(function (item) {
                return String(item.name || '').toLowerCase().indexOf(query) !== -1;
            }).slice(0, 5);

            if (!matched.length) {
                suggestions.innerHTML = '';
                suggestions.classList.remove('is-open');
                return;
            }

            suggestions.innerHTML = matched.map(function (item) {
                return '<div class="custom-doc-suggestion">' +
                    String(item.name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') +
                    '</div>';
            }).join('');
            suggestions.classList.add('is-open');
        });

        suggestions.addEventListener('mousedown', function (event) {
            if (!event.target.classList.contains('custom-doc-suggestion')) {
                return;
            }

            customType.value = event.target.textContent;
            syncCustomType(row, docTypes);
            suggestions.innerHTML = '';
            suggestions.classList.remove('is-open');
        });

        customType.addEventListener('blur', function () {
            window.setTimeout(function () {
                suggestions.classList.remove('is-open');
            }, 120);
        });
    }

    function bindFileRow(row, docTypes) {
        if (!row || row.dataset.leDocReady === '1') {
            return;
        }
        row.dataset.leDocReady = '1';

        var pickButton = row.querySelector('.js-file-pick-btn');
        var input = row.querySelector('.js-predef-file-input, .js-custom-file-input');
        var clearButton = row.querySelector('.predef-file-clear, .file-remove');

        if (pickButton && input) {
            pickButton.addEventListener('click', function () {
                input.click();
            });
        }

        if (input) {
            input.addEventListener('change', function () {
                clearDocumentError(input);
                var error = validateInputFile(input);
                if (error) {
                    setDocumentError(input, error);
                }
                paintRow(row, fileSummary(input));
            });
        }

        if (clearButton && input) {
            clearButton.addEventListener('click', function () {
                input.value = '';
                clearDocumentError(input);

                if (row.classList.contains('custom-doc-row')) {
                    var customType = row.querySelector('input[name="custom_doc_type_new[]"]');
                    var hiddenType = row.querySelector('input[name="custom_doc_type[]"]');
                    var hasTitle = !!String(customType && customType.value ? customType.value : '').trim();
                    if (!hasTitle) {
                        row.remove();
                        return;
                    }
                    if (hiddenType) {
                        hiddenType.value = '';
                    }
                    paintRow(row, { text: '', badge: '—', badgeClassName: 'file-type-badge-empty', filled: false });
                    return;
                }

                paintRow(row, { text: 'Файл не выбран', badge: '—', badgeClassName: 'file-type-badge-empty', filled: false });
            });
        }

        bindSuggestions(row, docTypes);
    }

    function buildCustomRow(container, docTypes) {
        var index = container.children.length;
        var badgeId = 'le-custom-badge-' + index;
        var metaId = 'le-custom-meta-' + index;
        var labelId = 'le-custom-label-' + index;
        var inputId = 'le-custom-input-' + index;
        var row = document.createElement('div');

        row.className = 'file-item custom-doc-row document-file-row is-empty';
        row.innerHTML =
            '<div class="file-type-badge file-type-badge-empty" id="' + badgeId + '">—</div>' +
            '<div class="file-info">' +
                '<div class="field custom-doc-title-field">' +
                    '<input type="hidden" name="custom_doc_type[]" value="">' +
                    '<input type="text" name="custom_doc_type_new[]" class="field-input" placeholder="Введите название">' +
                    '<div class="custom-doc-suggestions"></div>' +
                    '<div class="field-msg"></div>' +
                '</div>' +
                '<div class="file-meta is-hidden" id="' + metaId + '"></div>' +
            '</div>' +
            '<button type="button" class="btn btn-secondary file-action-btn js-file-pick-btn" data-file-input="' + inputId + '">' +
                '<span id="' + labelId + '">Выбрать</span>' +
            '</button>' +
            '<button type="button" class="file-remove" title="Удалить документ">×</button>' +
            '<input type="file" id="' + inputId + '" class="file-input-hidden js-custom-file-input" name="custom_doc_file[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" data-label="' + metaId + '" data-badge="' + badgeId + '" data-button-label="' + labelId + '">';

        container.appendChild(row);
        bindFileRow(row, docTypes);
    }

    window.initLegalEntityDocuments = function (form) {
        if (!form || form.dataset.leDocsReady === '1') {
            return;
        }
        form.dataset.leDocsReady = '1';

        var docTypesNode = form.querySelector('[data-doc-types]');
        var docTypes = [];
        if (docTypesNode) {
            try {
                docTypes = JSON.parse(docTypesNode.textContent || '[]');
            } catch (e) {
                docTypes = [];
            }
        }

        form.querySelectorAll('.document-file-row').forEach(function (row) {
            bindFileRow(row, docTypes);
        });

        var customContainer = form.querySelector('#le-custom-docs-container');
        var addButton = form.querySelector('#le-add-custom-doc-btn');
        if (customContainer && addButton && addButton.dataset.leDocsBound !== '1') {
            addButton.dataset.leDocsBound = '1';
            addButton.addEventListener('click', function () {
                buildCustomRow(customContainer, docTypes);
            });
        }
    };

    window.validateLegalEntityDocuments = function (form) {
        if (!form) {
            return true;
        }

        var ok = true;
        form.querySelectorAll('.js-predef-file-input, .js-custom-file-input').forEach(function (input) {
            clearDocumentError(input);
            var error = validateInputFile(input);
            if (error) {
                setDocumentError(input, error);
                ok = false;
            }
        });

        form.querySelectorAll('.custom-doc-row').forEach(function (row) {
            var titleField = row.querySelector('.custom-doc-title-field');
            var titleInput = row.querySelector('input[name="custom_doc_type_new[]"]');
            var hiddenType = row.querySelector('input[name="custom_doc_type[]"]');
            var input = row.querySelector('.js-custom-file-input');
            var titleMessage = titleField ? titleField.querySelector('.field-msg') : null;
            var hasTitle = !!String(titleInput && titleInput.value ? titleInput.value : '').trim();
            var hasSelectedType = !!String(hiddenType && hiddenType.value ? hiddenType.value : '').trim();
            var hasFile = !!(input && input.files && input.files.length);

            if (titleField) {
                titleField.classList.remove('is-error');
            }
            if (titleMessage) {
                titleMessage.textContent = '';
            }

            if (!hasTitle && !hasSelectedType && !hasFile) {
                return;
            }

            if (!hasTitle && !hasSelectedType) {
                if (titleField) {
                    titleField.classList.add('is-error');
                }
                if (titleMessage) {
                    titleMessage.textContent = 'Введите название документа';
                }
                ok = false;
            }

            if (!hasFile) {
                if (input) {
                    setDocumentError(input, 'Выберите файл');
                }
                ok = false;
            }
        });

        return ok;
    };
})();
