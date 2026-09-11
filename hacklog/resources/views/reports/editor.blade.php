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
        'subtitle' => 'Edit project details, assemble teams, and choose project leads from one spreadsheet.',
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
    const STATUS_STYLES = Object.fromEntries((options.statuses || []).map(function (status) {
        return [status.value, status];
    }));
    const TYPES = toMap(options.types, 'value', 'label', '—');
    const DEPARTMENTS = toMap(options.departments, 'id', 'name', '—');
    const OFFICES = toMap(options.offices, 'id', 'name', '—');
    const CATEGORIES = toMap(options.categories, 'value', 'label', '—');
    const AFFILIATIONS = toMap(options.affiliations, 'value', 'label', '—');
    const TEAM_USERS = options.teamUsers || [];
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

    function statusFormatter(cell) {
        const status = STATUS_STYLES[cell.getValue()];
        const badge = document.createElement('span');
        badge.className = 'badge';
        badge.textContent = status ? status.label : (cell.getValue() || '');
        if (status) {
            badge.style.backgroundColor = status.color;
            badge.style.color = status.text_color;
        }
        return badge;
    }

    function nameFormatter(cell) {
        const span = document.createElement('span');
        span.className = 'inventory-editor-name-text';
        span.textContent = cell.getValue() ?? '';
        return span;
    }

    function initials(name) {
        return (name || '?').trim().split(/\s+/).slice(0, 2).map(function (part) {
            return part.charAt(0).toUpperCase();
        }).join('');
    }

    function avatar(name, small) {
        const element = document.createElement('span');
        element.className = 'inventory-person-avatar' + (small ? ' inventory-person-avatar-sm' : '');
        element.textContent = initials(name);
        element.setAttribute('aria-hidden', 'true');
        return element;
    }

    function teamFormatter(cell) {
        const wrap = document.createElement('div');
        wrap.className = 'inventory-team-cell';
        const members = cell.getRow().getData().team || [];

        if (!members.length) {
            wrap.classList.add('inventory-people-empty');
            wrap.textContent = '+ Assign people';
            return wrap;
        }

        members.slice(0, 3).forEach(function (member) {
            const chip = document.createElement('span');
            chip.className = 'inventory-person-chip' + (member.is_leader ? ' is-leader' : '');
            chip.appendChild(avatar(member.name, true));
            const name = document.createElement('span');
            name.textContent = member.name.split(/\s+/)[0];
            chip.appendChild(name);
            if (member.is_leader) {
                const crown = document.createElement('span');
                crown.className = 'inventory-lead-crown';
                crown.textContent = '★';
                crown.title = 'Project lead';
                chip.appendChild(crown);
            }
            wrap.appendChild(chip);
        });

        if (members.length > 3) {
            const more = document.createElement('span');
            more.className = 'inventory-team-more';
            more.textContent = '+' + (members.length - 3);
            wrap.appendChild(more);
        }

        return wrap;
    }

    function leaderFormatter(cell) {
        const leader = cell.getRow().getData().leader;
        const wrap = document.createElement('div');
        wrap.className = 'inventory-leader-cell' + (leader ? ' is-leader' : ' inventory-people-empty');

        if (!leader) {
            wrap.textContent = '+ Choose lead';
            return wrap;
        }

        wrap.appendChild(avatar(leader.name, true));
        const name = document.createElement('span');
        name.textContent = leader.name;
        wrap.appendChild(name);
        const crown = document.createElement('span');
        crown.className = 'inventory-lead-crown';
        crown.textContent = '★';
        wrap.appendChild(crown);
        return wrap;
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
                data.honeycrisp_project_name,
                data.honeycrisp_billed_total,
                label(STATUSES, data.status),
                label(TYPES, data.project_type),
                label(DEPARTMENTS, data.department_id),
                label(nestedFor(data.department_id), data.nested_department_id),
                label(OFFICES, data.major_office_id),
                label(CATEGORIES, data.client_category),
                label(AFFILIATIONS, data.uconn_affiliation),
                (data.team || []).map(function (member) { return member.name; }).join(' '),
                data.leader ? data.leader.name : '',
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
                formatter: statusFormatter,
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
                title: 'Has Grant',
                field: 'has_grant',
                editor: 'list',
                editorParams: {
                    values: [
                        { label: 'Yes', value: true },
                        { label: 'No', value: false },
                    ],
                },
                formatter: function (cell) {
                    return cell.getValue() ? 'Yes' : 'No';
                },
                width: 110,
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
                title: 'Honeycrisp Project',
                field: 'honeycrisp_project_name',
                headerSort: true,
                minWidth: 160,
                formatter: function (cell) {
                    const value = cell.getValue();

                    return value ? value : '—';
                },
            },
            {
                title: 'Billed',
                field: 'honeycrisp_billed_total',
                hozAlign: 'right',
                headerSort: true,
                width: 120,
                formatter: function (cell) {
                    const value = cell.getValue();

                    return value === null || value === undefined || value === '' ? '—' : money.format(value);
                },
            },
            {
                title: 'Sponsor',
                field: 'sponsor',
                editor: 'input',
                minWidth: 160,
            },
            {
                title: 'Project Lead',
                field: 'leader_user_id',
                formatter: leaderFormatter,
                cellClick: function (event, cell) { openPeopleEditor(event, cell, 'leader'); },
                headerSort: false,
                minWidth: 180,
                width: 220,
                tooltip: 'Click to choose the project lead',
            },
            {
                title: 'Project Team',
                field: 'team_user_ids',
                formatter: teamFormatter,
                cellClick: function (event, cell) { openPeopleEditor(event, cell, 'team'); },
                headerSort: false,
                minWidth: 270,
                width: 320,
                tooltip: 'Click to assign people and choose a project lead',
            },
        ],
    });

    let syncing = false;
    let peoplePopover = null;

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

    function closePeopleEditor() {
        if (peoplePopover) {
            peoplePopover.remove();
            peoplePopover = null;
        }
    }

    function positionPeopleEditor(popover, anchor) {
        const rect = anchor.getBoundingClientRect();
        const gap = 6;
        const width = Math.min(380, window.innerWidth - 24);
        popover.style.width = width + 'px';
        const left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12));
        let top = rect.bottom + gap;
        const height = Math.min(popover.offsetHeight, window.innerHeight - 24);

        if (top + height > window.innerHeight - 12) {
            top = Math.max(12, rect.top - height - gap);
        }

        popover.style.left = left + 'px';
        popover.style.top = top + 'px';
    }

    function openPeopleEditor(event, cell, mode) {
        event.stopPropagation();
        closePeopleEditor();

        const row = cell.getRow();
        const data = row.getData();
        const selected = new Set((data.team_user_ids || []).map(Number));
        let leaderId = data.leader_user_id === null ? null : Number(data.leader_user_id);
        const originalLeaderId = leaderId;
        const popover = document.createElement('div');
        peoplePopover = popover;
        popover.className = 'inventory-people-popover';
        popover.setAttribute('role', 'dialog');
        popover.setAttribute('aria-label', mode === 'team' ? 'Edit project team' : 'Choose project lead');

        const header = document.createElement('div');
        header.className = 'inventory-people-header';
        const heading = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = mode === 'team' ? 'Project team' : 'Project lead';
        const help = document.createElement('small');
        help.textContent = mode === 'team'
            ? 'Select people and star one as the lead.'
            : 'Choosing a lead also adds them to the project team.';
        heading.appendChild(title);
        heading.appendChild(help);
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'inventory-people-close';
        close.setAttribute('aria-label', 'Close');
        close.textContent = '×';
        close.addEventListener('click', closePeopleEditor);
        header.appendChild(heading);
        header.appendChild(close);
        popover.appendChild(header);

        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control form-control-sm inventory-people-search';
        search.placeholder = 'Find a person…';
        popover.appendChild(search);

        const list = document.createElement('div');
        list.className = 'inventory-people-list';
        popover.appendChild(list);

        let countNote = null;

        function renderList() {
            const query = search.value.trim().toLowerCase();
            list.replaceChildren();
            const matches = TEAM_USERS.filter(function (user) {
                return !query || (user.name + ' ' + user.email).toLowerCase().includes(query);
            });

            matches.forEach(function (user) {
                const id = Number(user.id);
                const item = document.createElement('div');
                item.className = 'inventory-person-option'
                    + (selected.has(id) ? ' is-selected' : '')
                    + (leaderId === id ? ' is-leader' : '');
                const choose = document.createElement('button');
                choose.type = 'button';
                choose.className = 'inventory-person-choice';
                choose.appendChild(avatar(user.name));
                const identity = document.createElement('span');
                identity.className = 'inventory-person-identity';
                const personName = document.createElement('strong');
                personName.textContent = user.name;
                const email = document.createElement('small');
                email.textContent = user.active ? user.email : user.email + ' · Inactive';
                identity.appendChild(personName);
                identity.appendChild(email);
                choose.appendChild(identity);
                const check = document.createElement('span');
                check.className = 'inventory-person-check';
                check.textContent = mode === 'leader'
                    ? (leaderId === id ? '●' : '○')
                    : (selected.has(id) ? '✓' : '');
                choose.appendChild(check);
                choose.disabled = !user.active && !selected.has(id);
                choose.addEventListener('click', function () {
                    if (mode === 'leader') {
                        leaderId = id;
                        selected.add(id);
                    } else if (selected.has(id)) {
                        selected.delete(id);
                        if (leaderId === id) leaderId = null;
                    } else {
                        selected.add(id);
                    }
                    renderList();
                });
                item.appendChild(choose);

                if (mode === 'team') {
                    const lead = document.createElement('button');
                    lead.type = 'button';
                    lead.className = 'inventory-person-lead' + (leaderId === id ? ' is-leader' : '');
                    lead.title = leaderId === id ? 'Remove project lead' : 'Make project lead';
                    lead.setAttribute('aria-label', lead.title + ': ' + user.name);
                    lead.textContent = '★';
                    lead.disabled = !user.active && leaderId !== id;
                    lead.addEventListener('click', function () {
                        leaderId = leaderId === id ? null : id;
                        if (leaderId !== null) selected.add(id);
                        renderList();
                    });
                    item.appendChild(lead);
                }
                list.appendChild(item);
            });

            if (!matches.length) {
                const empty = document.createElement('div');
                empty.className = 'inventory-people-no-results';
                empty.textContent = 'No people found';
                list.appendChild(empty);
            }

            if (countNote) {
                countNote.textContent = selected.size + ' selected';
            }
        }

        search.addEventListener('input', renderList);
        renderList();

        const footer = document.createElement('div');
        footer.className = 'inventory-people-footer';
        if (mode === 'leader') {
            const clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'btn btn-sm btn-link text-muted me-auto';
            clear.textContent = 'No lead';
            clear.addEventListener('click', function () {
                leaderId = null;
                renderList();
            });
            footer.appendChild(clear);
        } else {
            countNote = document.createElement('span');
            countNote.className = 'small text-muted me-auto';
            countNote.textContent = selected.size + ' selected';
            footer.appendChild(countNote);
        }
        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'btn btn-sm btn-outline-secondary';
        cancelButton.textContent = 'Cancel';
        cancelButton.addEventListener('click', closePeopleEditor);
        const applyButton = document.createElement('button');
        applyButton.type = 'button';
        applyButton.className = 'btn btn-sm btn-primary';
        applyButton.textContent = 'Apply';
        applyButton.addEventListener('click', function () {
            applyButton.disabled = true;
            setStatus('Saving…');
            let save;

            if (mode === 'team') {
                save = request(updateUrl.replace('__ID__', data.id), 'PATCH', {
                    field: 'team_user_ids', value: Array.from(selected),
                }).then(function (payload) {
                    syncRow(row, payload.project);
                    if (leaderId === originalLeaderId) {
                        return payload;
                    }
                    return request(updateUrl.replace('__ID__', data.id), 'PATCH', {
                        field: 'leader_user_id', value: leaderId,
                    });
                });
            } else {
                save = request(updateUrl.replace('__ID__', data.id), 'PATCH', {
                    field: 'leader_user_id', value: leaderId,
                });
            }

            save.then(function (payload) {
                syncRow(row, payload.project);
                searchCache.delete(data.id);
                setStatus('Saved');
                closePeopleEditor();
            }).catch(function (error) {
                applyButton.disabled = false;
                setStatus(error.message, true);
            });
        });
        footer.appendChild(cancelButton);
        footer.appendChild(applyButton);
        popover.appendChild(footer);
        document.body.appendChild(popover);
        positionPeopleEditor(popover, cell.getElement());
        window.requestAnimationFrame(function () { search.focus(); });
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
        if (event.key === 'Escape' && peoplePopover) {
            closePeopleEditor();
            return;
        }
        if (event.key === 'Escape' && document.body.classList.contains('inventory-editor-fullscreen')) {
            setFullscreen(false);
        }
    });

    document.addEventListener('pointerdown', function (event) {
        if (peoplePopover && !peoplePopover.contains(event.target)) {
            closePeopleEditor();
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
