{{--
    Task Dependency Picker

    @param \Illuminate\Support\Collection $tasks
    @param \Illuminate\Support\Collection $projects
    @param array $selectedTaskIds
    @param int $defaultProjectId
    @param string $inputName
--}}

@php
    $pickerId = 'task-dependency-picker-' . md5(
        $inputName . microtime()
    );
@endphp




<div class="task-dependency-picker" id="{{ $pickerId }}">
    {{-- Project filter --}}
    <div class="mb-2">
        <label
            class="form-label small mb-1"
            for="{{ $pickerId }}-project">
            Project
        </label>

        <select
            id="{{ $pickerId }}-project"
            class="form-select form-select-sm dependency-picker-project">
            @foreach($projects as $candidateProject)
                <option
                    value="{{ $candidateProject->id }}"
                    {{ (string) $candidateProject->id ===
                       (string) $defaultProjectId
                        ? 'selected'
                        : '' }}>
                    {{ $candidateProject->name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Search input --}}
    <div class="mb-2">
        <label
            class="visually-hidden"
            for="{{ $pickerId }}-search">
            Search dependency tasks
        </label>

        <input
            id="{{ $pickerId }}-search"
            type="text"
            class="form-control form-control-sm dependency-picker-search"
            placeholder="Search tasks by title or ID..."
            autocomplete="off">
    </div>

    {{-- Candidate task list --}}
    <div
        class="border rounded p-2 user-picker-list"
        style="max-height: 200px; overflow-y: auto;">
        @forelse($tasks as $candidateTask)
            <div
                class="form-check mb-1 dependency-picker-item"
                data-project-id="{{ $candidateTask->column->project_id }}"
                data-search-text="{{ strtolower(
                    '#' . $candidateTask->id . ' ' .
                    $candidateTask->title
                ) }}">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="{{ $pickerId }}-task-{{ $candidateTask->id }}"
                    name="{{ $inputName }}"
                    value="{{ $candidateTask->id }}"
                    {{ in_array(
                        $candidateTask->id,
                        $selectedTaskIds,
                        true
                    ) ? 'checked' : '' }}>

                <label
                    class="form-check-label w-100 small"
                    for="{{ $pickerId }}-task-{{ $candidateTask->id }}">
                    #{{ $candidateTask->id }}
                    — {{ $candidateTask->title }}
                    <span class="text-muted">
                        · {{ $candidateTask->column->project->name }}
                    </span>
                </label>
            </div>
        @empty
            <div class="text-muted small text-center py-2">
                No available dependency tasks.
            </div>
        @endforelse

        <div
            class="dependency-picker-no-results text-muted small text-center py-2"
            style="display: none;">
            No matching tasks.
        </div>
    </div>
</div>

<script>
(() => {
    const picker = document.getElementById(@json($pickerId));
    if (!picker) return;

    const projectFilter = picker.querySelector(
        '.dependency-picker-project'
    );
    const searchInput = picker.querySelector(
        '.dependency-picker-search'
    );
    const items = Array.from(
        picker.querySelectorAll('.dependency-picker-item')
    );
    const noResults = picker.querySelector(
        '.dependency-picker-no-results'
    );

    function applyFilters() {
        const projectId = projectFilter.value;
        const searchTerms = searchInput.value
            .toLowerCase()
            .trim()
            .split(/\s+/)
            .filter(term => term.length > 0);

        let visibleCount = 0;

        items.forEach(item => {
            const matchesProject =
                item.dataset.projectId === projectId;

            const searchText = item.dataset.searchText;
            const matchesSearch = searchTerms.every(
                term => searchText.includes(term)
            );

            const visible =
                matchesProject && matchesSearch;

            item.style.display = visible ? '' : 'none';

            if (visible) visibleCount++;
        });

        if (noResults) {
            noResults.style.display =
                visibleCount === 0 ? '' : 'none';
        }
    }

    projectFilter.addEventListener(
        'change',
        applyFilters
    );
    searchInput.addEventListener(
        'input',
        applyFilters
    );

    // Apply the current-project default immediately.
    applyFilters();
})();
</script>

