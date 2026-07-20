(function () {
    function normalizeName(value) {
        return (value || '').replace(/\s+/g, ' ').trim();
    }

    function splitNames(rawValue) {
        return (rawValue || '')
            .split(/[\n,]/)
            .map(normalizeName)
            .filter(function (name) {
                return name.length > 0;
            });
    }

    function initTagPicker(container) {
        if (!container || container.dataset.enhanced === 'true') {
            return;
        }

        var select = container.querySelector('[data-tag-select]');
        var newTagsInput = container.querySelector('[data-tag-new-input]');
        var enhanced = container.querySelector('[data-tag-enhanced]');

        if (!select || !newTagsInput || !enhanced) {
            return;
        }

        container.dataset.enhanced = 'true';

        var shell = enhanced.querySelector('[data-tag-shell]');
        var chipList = enhanced.querySelector('[data-tag-chip-list]');
        var searchInput = enhanced.querySelector('[data-tag-search-input]');
        var suggestionsEl = enhanced.querySelector('[data-tag-suggestions]');

        if (!shell || !chipList || !searchInput || !suggestionsEl) {
            return;
        }

        enhanced.classList.remove('d-none');
        select.classList.add('d-none');
        newTagsInput.classList.add('d-none');

        var selectedIds = new Set();
        var newNames = [];
        var suggestions = [];
        var highlightedIndex = -1;

        Array.from(select.options).forEach(function (option) {
            if (option.selected) {
                selectedIds.add(String(option.value));
            }
        });

        splitNames(newTagsInput.value).forEach(function (name) {
            if (!hasName(name)) {
                newNames.push(name);
            }
        });

        function optionById(id) {
            return Array.from(select.options).find(function (option) {
                return String(option.value) === String(id);
            });
        }

        function optionByName(name) {
            var normalized = normalizeName(name).toLowerCase();

            return Array.from(select.options).find(function (option) {
                return normalizeName(option.textContent).toLowerCase() === normalized;
            });
        }

        function hasName(name) {
            var normalized = normalizeName(name).toLowerCase();
            if (!normalized) {
                return false;
            }

            for (var i = 0; i < newNames.length; i += 1) {
                if (normalizeName(newNames[i]).toLowerCase() === normalized) {
                    return true;
                }
            }

            var ids = Array.from(selectedIds);
            for (var j = 0; j < ids.length; j += 1) {
                var option = optionById(ids[j]);
                if (option && normalizeName(option.textContent).toLowerCase() === normalized) {
                    return true;
                }
            }

            return false;
        }

        function removeMatchingNewName(name) {
            var normalized = normalizeName(name).toLowerCase();
            if (!normalized) {
                return;
            }

            newNames = newNames.filter(function (existing) {
                return normalizeName(existing).toLowerCase() !== normalized;
            });
        }

        function syncFields() {
            Array.from(select.options).forEach(function (option) {
                option.selected = selectedIds.has(String(option.value));
            });

            newTagsInput.value = newNames.join(', ');
        }

        function createChip(label, type, value) {
            var chip = document.createElement('span');
            chip.className = 'hl-tag-chip';
            chip.dataset.type = type;
            chip.dataset.value = String(value);

            var chipText = document.createElement('span');
            chipText.className = 'hl-tag-chip-label';
            chipText.textContent = label;

            var removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'hl-tag-chip-remove';
            removeButton.setAttribute('aria-label', 'Remove tag ' + label);
            removeButton.textContent = 'x';
            removeButton.addEventListener('click', function () {
                if (type === 'existing') {
                    selectedIds.delete(String(value));
                } else {
                    newNames = newNames.filter(function (name) {
                        return normalizeName(name).toLowerCase() !== normalizeName(value).toLowerCase();
                    });
                }

                syncFields();
                render();
                searchInput.focus();
            });

            chip.appendChild(chipText);
            chip.appendChild(removeButton);

            return chip;
        }

        function selectSuggestion(item) {
            if (!item) {
                return;
            }

            if (item.type === 'existing') {
                selectedIds.add(String(item.id));
                removeMatchingNewName(item.label);
            } else {
                var newName = normalizeName(item.label);
                if (!newName || hasName(newName)) {
                    return;
                }

                var existingMatch = optionByName(newName);
                if (existingMatch) {
                    selectedIds.add(String(existingMatch.value));
                    removeMatchingNewName(existingMatch.textContent);
                } else {
                    newNames.push(newName);
                }
            }

            searchInput.value = '';
            syncFields();
            render();
        }

        function updateSuggestions() {
            var query = normalizeName(searchInput.value).toLowerCase();
            var nextSuggestions = [];

            Array.from(select.options).forEach(function (option) {
                var id = String(option.value);
                var label = normalizeName(option.textContent);
                if (selectedIds.has(id)) {
                    return;
                }

                if (query.length === 0 || label.toLowerCase().indexOf(query) !== -1) {
                    nextSuggestions.push({
                        type: 'existing',
                        id: id,
                        label: label,
                    });
                }
            });

            if (query.length > 0 && !hasName(query)) {
                var exact = optionByName(query);
                if (!exact) {
                    nextSuggestions.push({
                        type: 'new',
                        label: normalizeName(searchInput.value),
                    });
                }
            }

            suggestions = nextSuggestions;
            highlightedIndex = suggestions.length > 0 ? 0 : -1;
        }

        function renderSuggestions() {
            suggestionsEl.innerHTML = '';

            if (suggestions.length === 0) {
                suggestionsEl.hidden = true;
                return;
            }

            suggestions.forEach(function (item, index) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';
                button.setAttribute('role', 'option');

                if (index === highlightedIndex) {
                    button.classList.add('active');
                }

                if (item.type === 'existing') {
                    var existingLabel = document.createElement('span');
                    existingLabel.className = 'fw-medium';
                    existingLabel.textContent = item.label;

                    var existingHint = document.createElement('small');
                    existingHint.className = 'text-muted ms-2';
                    existingHint.textContent = 'existing';

                    button.appendChild(existingLabel);
                    button.appendChild(existingHint);
                } else {
                    var newLabel = document.createElement('span');
                    newLabel.className = 'fw-medium';
                    newLabel.textContent = item.label;

                    var newHint = document.createElement('small');
                    newHint.className = 'text-muted ms-2';
                    newHint.textContent = 'create new';

                    button.appendChild(newLabel);
                    button.appendChild(newHint);
                }

                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    selectSuggestion(item);
                });

                suggestionsEl.appendChild(button);
            });

            suggestionsEl.hidden = false;
        }

        function renderChips() {
            chipList.innerHTML = '';

            Array.from(selectedIds).forEach(function (id) {
                var option = optionById(id);
                if (option) {
                    chipList.appendChild(createChip(normalizeName(option.textContent), 'existing', id));
                }
            });

            newNames.forEach(function (name) {
                chipList.appendChild(createChip(name, 'new', name));
            });
        }

        function render() {
            updateSuggestions();
            renderChips();
            renderSuggestions();
        }

        function commitRawInput() {
            var value = normalizeName(searchInput.value);
            if (!value) {
                return;
            }

            selectSuggestion({ type: 'new', label: value });
        }

        shell.addEventListener('click', function () {
            searchInput.focus();
        });

        searchInput.addEventListener('input', function () {
            render();
        });

        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                if (suggestions.length > 0) {
                    event.preventDefault();
                    highlightedIndex = (highlightedIndex + 1) % suggestions.length;
                    renderSuggestions();
                }
                return;
            }

            if (event.key === 'ArrowUp') {
                if (suggestions.length > 0) {
                    event.preventDefault();
                    highlightedIndex = (highlightedIndex - 1 + suggestions.length) % suggestions.length;
                    renderSuggestions();
                }
                return;
            }

            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                if (highlightedIndex >= 0 && suggestions[highlightedIndex]) {
                    selectSuggestion(suggestions[highlightedIndex]);
                } else {
                    commitRawInput();
                }
                return;
            }

            if (event.key === 'Backspace' && searchInput.value === '') {
                var chip = chipList.querySelector('.hl-tag-chip:last-child .hl-tag-chip-remove');
                if (chip) {
                    chip.click();
                }
                return;
            }

            if (event.key === 'Escape') {
                suggestions = [];
                highlightedIndex = -1;
                renderSuggestions();
            }
        });

        searchInput.addEventListener('blur', function () {
            window.setTimeout(function () {
                suggestions = [];
                highlightedIndex = -1;
                renderSuggestions();
                syncFields();
            }, 120);
        });

        searchInput.addEventListener('focus', function () {
            render();
        });

        var form = container.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                commitRawInput();
                syncFields();
            });
        }

        render();
        syncFields();
    }

    function initializeTagPickers(root) {
        var scope = root || document;
        var pickers = scope.querySelectorAll('[data-tag-picker]');
        pickers.forEach(initTagPicker);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initializeTagPickers(document);
    });

    document.body.addEventListener('htmx:afterSwap', function (event) {
        initializeTagPickers(event.target);
    });
})();
