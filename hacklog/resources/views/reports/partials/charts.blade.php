<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="text-uppercase text-muted small fw-semibold mb-2">All Projects by Status</h2>
                @if($statusCounts->isEmpty())
                    <p class="text-muted small mb-0">No projects to chart.</p>
                @else
                    <div id="status-chart" style="min-height: 220px;"></div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="text-uppercase text-muted small fw-semibold mb-2">All Projects by Type</h2>
                @if($typeCounts->isEmpty())
                    <p class="text-muted small mb-0">No project types to chart.</p>
                @else
                    <div id="type-chart" style="min-height: 220px;"></div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        @include('reports.partials.summary')
    </div>
</div>

@if($statusCounts->isNotEmpty() || $typeCounts->isNotEmpty())
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3/dist/apexcharts.min.js"></script>
<script>
(function () {
    const otherFiltersFor = function (exceptKey) {
        const raw = @json(request()->except(['page']));
        delete raw[exceptKey];
        return Object.fromEntries(Object.entries(raw).filter(([, value]) => value !== null && value !== ''));
    };

    const buildUrl = function (exceptKey, value) {
        const params = new URLSearchParams(otherFiltersFor(exceptKey));
        if (value !== null) {
            params.set(exceptKey, value);
        }
        const qs = params.toString();
        return @json(route('reports.index')) + (qs ? '?' + qs : '');
    };

    const pieOptions = function (el, slices, colors, activeValue, filterKey) {
        let suppressNext = false;
        const chart = new ApexCharts(el, {
            chart: {
                type: 'pie',
                height: 220,
                toolbar: { show: false },
                animations: { enabled: true, speed: 300 },
                events: {
                    dataPointSelection: function (event, ctx, config) {
                        if (suppressNext) { suppressNext = false; return; }
                        const clicked = slices[config.dataPointIndex].value;
                        window.location.href = (clicked === activeValue)
                            ? buildUrl(filterKey, null)
                            : buildUrl(filterKey, clicked);
                    },
                },
            },
            series: slices.map(function (s) { return s.projects_count; }),
            labels: slices.map(function (s) { return s.label; }),
            colors: colors,
            legend: {
                position: 'right',
                fontSize: '12px',
                labels: {
                    colors: document.documentElement.classList.contains('theme-dark') ? '#e2e6eb' : '#373d3f',
                },
                formatter: function (label, opts) {
                    return label + ' (' + opts.w.globals.series[opts.seriesIndex] + ')';
                },
            },
            dataLabels: { enabled: false },
            stroke: { width: 2 },
            tooltip: {
                y: {
                    formatter: function (val) { return val + (val === 1 ? ' project' : ' projects'); },
                },
            },
            plotOptions: { pie: { expandOnClick: false } },
        });

        chart.render().then(function () {
            if (activeValue !== null) {
                const idx = slices.findIndex(function (s) { return s.value === activeValue; });
                if (idx >= 0) {
                    requestAnimationFrame(function () {
                        suppressNext = true;
                        chart.toggleDataPointSelection(idx);
                    });
                }
            }
        });
    };

    const statusEl = document.getElementById('status-chart');
    const statusSlices = @json($statusCounts);
    const statusColors = @json($statusColors);
    if (statusEl && statusSlices.length) {
        pieOptions(
            statusEl,
            statusSlices,
            statusSlices.map(function (s) { return statusColors[s.value] || '#9ca3af'; }),
            @json(request('status') ?: null),
            'status'
        );
    }

    const typeEl = document.getElementById('type-chart');
    const typeSlices = @json($typeCounts);
    const typePalette = ['#2563eb', '#7c3aed', '#16a34a', '#d97706', '#ea580c'];
    if (typeEl && typeSlices.length) {
        pieOptions(
            typeEl,
            typeSlices,
            typeSlices.map(function (_, i) { return typePalette[i % typePalette.length]; }),
            @json(request('type') ?: null),
            'type'
        );
    }
}());
</script>
@endpush
@endif
