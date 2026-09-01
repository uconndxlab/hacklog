@extends('layouts.app')

@section('title', 'Reports — Inventory Editor')
@section('body_class', 'inventory-editor-body')

@push('styles')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
@endpush

@section('content')
<div class="inventory-editor-page">
    @include('reports.partials.nav', [
        'title' => 'Inventory Editor',
        'subtitle' => 'Spreadsheet of all relevant project fields. Click a cell to edit; changes save as you leave it.',
    ])

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
            <input type="search" id="inventory-editor-search" class="form-control form-control-sm" style="min-width: 16rem;" placeholder="Search projects…">
            <span id="inventory-editor-status" class="small text-muted">{{ $rows->count() }} project{{ $rows->count() === 1 ? '' : 's' }}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="btn-group" role="group" aria-label="Scroll spreadsheet">
                <button type="button" id="inventory-editor-scroll-left" class="btn btn-sm btn-outline-secondary" aria-label="Scroll left" title="Scroll left">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                        <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                    </svg>
                </button>
                <button type="button" id="inventory-editor-scroll-right" class="btn btn-sm btn-outline-secondary" aria-label="Scroll right" title="Scroll right">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </button>
            </div>
            <button type="button" id="inventory-editor-add" class="btn btn-sm btn-primary">Add project</button>
            <button type="button" id="inventory-editor-fs-enter" class="btn btn-sm btn-outline-secondary" aria-label="Full screen" title="Full screen">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M1.5 1a.5.5 0 0 0-.5.5v4a.5.5 0 0 1-1 0v-4A1.5 1.5 0 0 1 1.5 0h4a.5.5 0 0 1 0 1zM10 .5a.5.5 0 0 1 .5-.5h4A1.5 1.5 0 0 1 16 1.5v4a.5.5 0 0 1-1 0v-4a.5.5 0 0 0-.5-.5h-4a.5.5 0 0 1-.5-.5M.5 10a.5.5 0 0 1 .5.5v4a.5.5 0 0 0 .5.5h4a.5.5 0 0 1 0 1h-4A1.5 1.5 0 0 1 0 14.5v-4a.5.5 0 0 1 .5-.5m15 0a.5.5 0 0 1 .5.5v4a1.5 1.5 0 0 1-1.5 1.5h-4a.5.5 0 0 1 0-1h4a.5.5 0 0 0 .5-.5v-4a.5.5 0 0 1 .5-.5"/>
                </svg>
            </button>
        </div>
    </div>

    <div id="inventory-editor-grid" class="report-inventory-grid"></div>

    <button type="button" id="inventory-editor-fs-exit" class="btn btn-sm btn-primary inventory-editor-fs-exit" aria-label="Exit full screen" title="Exit full screen">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M5.5 0a.5.5 0 0 1 .5.5v4A1.5 1.5 0 0 1 4.5 6h-4a.5.5 0 0 1 0-1h4a.5.5 0 0 0 .5-.5v-4a.5.5 0 0 1 .5-.5m5 0a.5.5 0 0 1 .5.5v4a.5.5 0 0 0 .5.5h4a.5.5 0 0 1 0 1h-4A1.5 1.5 0 0 1 10 4.5v-4a.5.5 0 0 1 .5-.5M0 10.5a.5.5 0 0 1 .5-.5h4A1.5 1.5 0 0 1 6 11.5v4a.5.5 0 0 1-1 0v-4a.5.5 0 0 0-.5-.5h-4a.5.5 0 0 1-.5-.5m10 1a1.5 1.5 0 0 1 1.5-1.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 0-.5.5v4a.5.5 0 0 1-1 0z"/>
        </svg>
    </button>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function () {
    const rows = @json($rows);
    const options = @json($options);
    const updateUrl = @json(route('reports.editor.update', ['project' => '__ID__']));
    const storeUrl = @json(route('reports.editor.store'));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const statusEl = document.getElementById('inventory-editor-status');
    const searchEl = document.getElementById('inventory-editor-search');

    function toMap(items, valueKey, labelKey, blank) {
        const result = { '': blank };
        (items || []).forEach(function (item) {
            result[item[valueKey]] = item[labelKey];
        });
        return result;
    }

    const STATUSES = toMap(options.statuses, 'value', 'label', '');
    const TYPES = toMap(options.types, 'value', 'label', '—');
    const DEPARTMENTS = toMap(options.departments, 'id', 'name', '—');
    const OFFICES = toMap(options.offices, 'id', 'name', '—');
    const CATEGORIES = toMap(options.categories, 'value', 'label', '—');
    const AFFILIATIONS = toMap(options.affiliations, 'value', 'label', '—');
    const money = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' });
    const nestedMaps = {};

    function nestedFor(homeId) {
        const key = homeId === null || homeId === undefined ? '' : String(homeId);
        if (!nestedMaps[key]) {
            nestedMaps[key] = toMap(options.nestedByHome[key], 'id', 'name', '—');
        }
        return nestedMaps[key];
    }

    function label(values, value) {
        if (value === null || value === undefined || value === '') {
            return values[''];
        }
        return values[value] || '';
    }

    function lookup(values) {
        return function (cell) {
            return label(values, cell.getValue());
        };
    }

    function nameFormatter(cell) {
        const span = document.createElement('span');
        span.className = 'inventory-editor-name-text';
        span.textContent = cell.getValue() ?? '';
        return span;
    }

    const searchCache = new Map();

    function searchText(data) {
        let text = searchCache.get(data.id);
        if (text === undefined) {
            text = [
                data.name,
                data.client_pi,
                data.sponsor,
                data.launch_date,
                data.grant_value,
                label(STATUSES, data.status),
                label(TYPES, data.project_type),
                label(DEPARTMENTS, data.department_id),
                label(nestedFor(data.department_id), data.nested_department_id),
                label(OFFICES, data.major_office_id),
                label(CATEGORIES, data.client_category),
                label(AFFILIATIONS, data.uconn_affiliation),
            ].filter(Boolean).join(' ').toLowerCase();
            searchCache.set(data.id, text);
        }
        return text;
    }

    function setStatus(message, isError) {
        statusEl.textContent = message;
        statusEl.classList.toggle('text-danger', isError === true);
        statusEl.classList.toggle('text-muted', isError !== true);
    }

    function messageFor(payload) {
        const errors = payload.errors || {};
        const first = Object.keys(errors)[0];

        return (first && errors[first][0]) || payload.message || 'Could not save that change.';
    }

    function request(url, method, body) {
        return fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body),
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (payload) {
                if (!response.ok) {
                    throw new Error(messageFor(payload));
                }
                return payload;
            });
        });
    }

    const table = new Tabulator('#inventory-editor-grid', {
        data: rows,
        index: 'id',
        layout: 'fitData',
        height: '100%',
        rowHeight: 48,
        renderVerticalBuffer: 300,
        placeholder: 'No projects yet.',
        clipboard: true,
        columnDefaults: { editorEmptyValue: null },
        columns: [
            {
                title: 'Status',
                field: 'status',
                editor: 'list',
                editorParams: { values: STATUSES },
                formatter: lookup(STATUSES),
                frozen: true,
                width: 130,
            },
            {
                title: 'Project Name',
                field: 'name',
                editor: 'input',
                formatter: nameFormatter,
                frozen: true,
                tooltip: true,
                minWidth: 140,
                width: 220,
            },
            {
                title: 'Project Type',
                field: 'project_type',
                editor: 'list',
                editorParams: { values: TYPES },
                formatter: lookup(TYPES),
                width: 150,
            },
            {
                title: 'Launch Date',
                field: 'launch_date',
                editor: 'date',
                editorParams: { format: 'yyyy-MM-dd' },
                width: 140,
            },
            {
                title: 'Home Department',
                field: 'department_id',
                editor: 'list',
                editorParams: { values: DEPARTMENTS, autocomplete: true, filter: true },
                formatter: lookup(DEPARTMENTS),
                minWidth: 180,
            },
            {
                title: 'Nested Department',
                field: 'nested_department_id',
                editor: 'list',
                editorParams: function (cell) {
                    return {
                        values: nestedFor(cell.getRow().getData().department_id),
                        autocomplete: true,
                        filter: true,
                    };
                },
                formatter: function (cell) {
                    return label(nestedFor(cell.getRow().getData().department_id), cell.getValue());
                },
                minWidth: 180,
            },
            {
                title: 'Top Level School or Major Office',
                field: 'major_office_id',
                editor: 'list',
                editorParams: { values: OFFICES, autocomplete: true, filter: true },
                formatter: lookup(OFFICES),
                minWidth: 220,
            },
            {
                title: 'Client/PI',
                field: 'client_pi',
                editor: 'input',
                minWidth: 150,
            },
            {
                title: 'Client Category',
                field: 'client_category',
                editor: 'list',
                editorParams: { values: CATEGORIES },
                formatter: lookup(CATEGORIES),
                minWidth: 220,
            },
            {
                title: 'UConn Affiliation',
                field: 'uconn_affiliation',
                editor: 'list',
                editorParams: { values: AFFILIATIONS },
                formatter: lookup(AFFILIATIONS),
                width: 150,
            },
            {
                title: 'Grant Value',
                field: 'grant_value',
                editor: 'number',
                hozAlign: 'right',
                formatter: function (cell) {
                    const value = cell.getValue();

                    return value === null || value === undefined || value === '' ? '' : money.format(value);
                },
                width: 130,
            },
            {
                title: 'Sponsor',
                field: 'sponsor',
                editor: 'input',
                minWidth: 160,
            },
        ],
    });

    let syncing = false;

    function syncRow(row, project) {
        const current = row.getData();
        const changes = {};
        let dirty = false;

        Object.keys(project).forEach(function (key) {
            if (current[key] !== project[key]) {
                changes[key] = project[key];
                dirty = true;
            }
        });

        if (!dirty) {
            return;
        }

        syncing = true;
        row.update(changes);
        syncing = false;
    }

    table.on('cellEdited', function (cell) {
        if (syncing) {
            return;
        }

        const row = cell.getRow();
        const id = row.getData().id;
        const value = cell.getValue();

        setStatus('Saving…');
        searchCache.delete(id);

        request(updateUrl.replace('__ID__', id), 'PATCH', {
            field: cell.getField(),
            value: value === '' ? null : value,
        }).then(function (payload) {
            syncRow(row, payload.project);
            searchCache.delete(id);
            setStatus('Saved');
        }).catch(function (error) {
            syncing = true;
            cell.restoreOldValue();
            syncing = false;
            searchCache.delete(id);
            setStatus(error.message, true);
        });
    });

    let searchTimer = null;

    searchEl.addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            const query = searchEl.value.trim().toLowerCase();

            if (query === '') {
                table.clearFilter();
                return;
            }

            table.setFilter(function (data) {
                return searchText(data).indexOf(query) !== -1;
            });
        }, 150);
    });

    let holder = null;

    table.on('tableBuilt', function () {
        holder = table.element.querySelector('.tabulator-tableholder');
    });

    function scrollSheet(distance) {
        if (holder) {
            holder.scrollLeft += distance;
        }
    }

    document.getElementById('inventory-editor-scroll-left').addEventListener('click', function () {
        scrollSheet(-280);
    });

    document.getElementById('inventory-editor-scroll-right').addEventListener('click', function () {
        scrollSheet(280);
    });

    function setFullscreen(on) {
        document.body.classList.toggle('inventory-editor-fullscreen', on);
        window.requestAnimationFrame(function () {
            table.redraw(true);
        });
    }

    document.getElementById('inventory-editor-fs-enter').addEventListener('click', function () {
        setFullscreen(true);
    });

    document.getElementById('inventory-editor-fs-exit').addEventListener('click', function () {
        setFullscreen(false);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && document.body.classList.contains('inventory-editor-fullscreen')) {
            setFullscreen(false);
        }
    });

    document.getElementById('inventory-editor-add').addEventListener('click', function () {
        request(storeUrl, 'POST', {}).then(function (payload) {
            table.addRow(payload.project, true);
            setStatus('Added untitled project');
        }).catch(function (error) {
            setStatus(error.message, true);
        });
    });
}());
</script>
@endpush
