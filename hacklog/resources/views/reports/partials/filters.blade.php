@php
    $filterQuery = function (array $except = []) {
        return array_filter(
            request()->except(array_merge(['page'], $except)),
            fn ($value) => $value !== null && $value !== ''
        );
    };

    $chips = [];
    if (request()->filled('status') && isset(\App\Http\Controllers\ReportController::STATUS_LABELS[request('status')])) {
        $chips[] = ['label' => \App\Http\Controllers\ReportController::STATUS_LABELS[request('status')], 'url' => route('reports.index', $filterQuery(['status']))];
    }
    if (request()->filled('type') && isset(\App\Models\Project::TYPE_LABELS[request('type')])) {
        $chips[] = ['label' => \App\Models\Project::TYPE_LABELS[request('type')], 'url' => route('reports.index', $filterQuery(['type']))];
    }
    if (request()->filled('department')) {
        $deptName = $departments->firstWhere('id', (int) request('department'))?->name ?? 'Department';
        $chips[] = ['label' => $deptName, 'url' => route('reports.index', $filterQuery(['department']))];
    }
    if (request()->filled('office')) {
        $officeName = $offices->firstWhere('id', (int) request('office'))?->name ?? 'Office';
        $chips[] = ['label' => $officeName, 'url' => route('reports.index', $filterQuery(['office']))];
    }
    if (request()->filled('affiliation') && isset(\App\Models\Project::AFFILIATION_LABELS[request('affiliation')])) {
        $chips[] = ['label' => \App\Models\Project::AFFILIATION_LABELS[request('affiliation')], 'url' => route('reports.index', $filterQuery(['affiliation']))];
    }
    if (request('grant') === '1') {
        $chips[] = ['label' => 'Grant funded', 'url' => route('reports.index', $filterQuery(['grant']))];
    } elseif (request('grant') === '0') {
        $chips[] = ['label' => 'No grant', 'url' => route('reports.index', $filterQuery(['grant']))];
    }
    if (request()->filled('search')) {
        $chips[] = ['label' => 'Search: '.request('search'), 'url' => route('reports.index', $filterQuery(['search']))];
    }
@endphp

<form method="GET" action="{{ route('reports.index') }}" class="mb-4" id="report-filter-form">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-4 col-xl">
            <label for="status" class="form-label small mb-1">Status</label>
            <select id="status" name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach(\App\Http\Controllers\ReportController::STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <label for="type" class="form-label small mb-1">Type</label>
            <select id="type" name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All types</option>
                @foreach(\App\Models\Project::TYPE_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <label for="department" class="form-label small mb-1">Department</label>
            <select id="department" name="department" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) request('department') === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <label for="office" class="form-label small mb-1">Office</label>
            <select id="office" name="office" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All offices</option>
                @foreach($offices as $office)
                    <option value="{{ $office->id }}" @selected((string) request('office') === (string) $office->id)>{{ $office->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <label for="affiliation" class="form-label small mb-1">Affiliation</label>
            <select id="affiliation" name="affiliation" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All</option>
                @foreach(\App\Models\Project::AFFILIATION_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(request('affiliation') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <label for="grant" class="form-label small mb-1">Grant</label>
            <select id="grant" name="grant" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All</option>
                <option value="1" @selected(request('grant') === '1')>Funded</option>
                <option value="0" @selected(request('grant') === '0')>No grant</option>
            </select>
        </div>
        <div class="col-12 col-md-8 col-xl-3">
            <label for="search" class="form-label small mb-1">Search</label>
            <div class="input-group input-group-sm">
                <input type="text" id="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Project name">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </div>
    </div>
</form>

@if(count($chips) > 0)
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
        @foreach($chips as $chip)
            <a href="{{ $chip['url'] }}" class="badge rounded-pill text-decoration-none report-filter-chip">
                {{ $chip['label'] }}
                <span aria-hidden="true">&times;</span>
            </a>
        @endforeach
        <a href="{{ route('reports.index') }}" class="small text-muted">Clear</a>
    </div>
@endif
