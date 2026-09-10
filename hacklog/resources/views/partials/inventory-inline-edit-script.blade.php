<script>
    var expandedDepartmentGroups = new Set();

    document.body.addEventListener('click', function (event) {
        var homeRow = event.target.closest('tr[data-department-home-row]');
        if (homeRow && !event.target.closest('button, a, input, select, textarea, label, [data-inline-start], [data-inline-save]')) {
            var group = homeRow.closest('[data-department-group]');
            if (group && !homeRow.classList.contains('is-editing')) {
                group.classList.toggle('is-expanded');
                var expanded = group.classList.contains('is-expanded');
                if (expanded) {
                    expandedDepartmentGroups.add(group.id);
                } else {
                    expandedDepartmentGroups.delete(group.id);
                }
                homeRow.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }
        }

        var start = event.target.closest('[data-inline-start]');
        if (!start) {
            return;
        }

        var row = start.closest('[data-inline-edit]');
        if (!row) {
            return;
        }

        var input = row.querySelector('[data-inline-input]');
        if (!input) {
            return;
        }

        row.classList.add('is-editing');
        input.focus();
        input.select();
    });

    function restoreExpandedDepartmentGroups() {
        expandedDepartmentGroups.forEach(function (id) {
            var group = document.getElementById(id);
            if (!group) {
                expandedDepartmentGroups.delete(id);
                return;
            }

            group.classList.add('is-expanded');
            var homeRow = group.querySelector('[data-department-home-row]');
            if (homeRow) {
                homeRow.setAttribute('aria-expanded', 'true');
            }
        });
    }

    function applyInventorySearch(searchInput) {
        var query = (searchInput.value || '').trim().toLowerCase();
        var table = document.querySelector(searchInput.getAttribute('data-inventory-search'));
        if (!table) {
            return;
        }

        var mode = searchInput.getAttribute('data-inventory-search-mode') || 'rows';
        var empty = table.parentElement ? table.parentElement.querySelector('[data-inventory-search-empty]') : null;
        var visibleCount = 0;

        if (mode === 'groups') {
            table.querySelectorAll('tbody').forEach(function (group) {
                var homeRow = group.querySelector('tr[data-inline-edit]:not([data-nested-row])');
                var nestedRows = group.querySelectorAll('tr[data-nested-row]');
                var addRow = group.querySelector('tr[data-nested-add]');
                var homeName = homeRow ? (homeRow.getAttribute('data-search-name') || '') : '';
                var homeMatches = !query || homeName.indexOf(query) !== -1;
                var nestedMatchCount = 0;

                nestedRows.forEach(function (row) {
                    var nestedName = row.getAttribute('data-search-name') || '';
                    var nestedMatches = !query || homeMatches || nestedName.indexOf(query) !== -1;
                    row.classList.toggle('d-none', query !== '' && !nestedMatches);
                    if (query && nestedName.indexOf(query) !== -1) {
                        nestedMatchCount += 1;
                    }
                });

                var groupVisible = homeMatches || nestedMatchCount > 0;
                group.classList.toggle('d-none', !groupVisible);
                if (addRow) {
                    addRow.classList.remove('d-none');
                }
                if (homeRow) {
                    homeRow.classList.toggle('d-none', !groupVisible);
                }

                var shouldExpand = expandedDepartmentGroups.has(group.id) || nestedMatchCount > 0;
                group.classList.toggle('is-expanded', shouldExpand);
                if (homeRow) {
                    homeRow.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
                }

                if (groupVisible) {
                    visibleCount += 1;
                }
            });
        } else {
            table.querySelectorAll('tr[data-inline-edit]').forEach(function (row) {
                var name = row.getAttribute('data-search-name') || '';
                var visible = !query || name.indexOf(query) !== -1;
                row.classList.toggle('d-none', !visible);
                if (visible) {
                    visibleCount += 1;
                }
            });
        }

        if (empty) {
            empty.classList.toggle('d-none', visibleCount > 0 || !query);
        }
    }

    document.body.addEventListener('input', function (event) {
        if (!event.target.matches('[data-inventory-search]')) {
            return;
        }
        applyInventorySearch(event.target);
    });

    document.body.addEventListener('htmx:beforeRequest', function (event) {
        var elt = event.detail && event.detail.elt;
        if (!elt || !elt.closest) {
            return;
        }

        var group = elt.closest('[data-department-group]');
        if (group && group.id && (group.classList.contains('is-expanded') || elt.closest('[data-nested-add], [data-nested-row]'))) {
            expandedDepartmentGroups.add(group.id);
        }
    });

    document.body.addEventListener('htmx:afterSettle', function (event) {
        var target = event.detail && event.detail.target;
        if (target && target.matches && target.matches('[data-department-group]') && target.id && target.classList.contains('is-expanded')) {
            expandedDepartmentGroups.add(target.id);
        }

        restoreExpandedDepartmentGroups();
        document.querySelectorAll('[data-inventory-search]').forEach(applyInventorySearch);
    });
</script>
