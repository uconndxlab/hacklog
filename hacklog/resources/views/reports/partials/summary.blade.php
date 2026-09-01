<div class="card h-100">
    <div class="card-body">
        <h2 class="text-uppercase text-muted small fw-semibold mb-3">Filtered Summary</h2>
        <div class="row g-3">
            <div class="col-6">
                <div class="text-muted small">Total Projects</div>
                <div class="fs-3 fw-bold lh-sm">{{ number_format($summary->total) }}</div>
            </div>
            <div class="col-6">
                <div class="text-muted small">Grant Funding</div>
                @if($summary->grant_total > 0)
                    <div class="fs-3 fw-bold lh-sm">${{ number_format($summary->grant_total) }}</div>
                @else
                    <div class="fs-3 fw-bold lh-sm text-muted">—</div>
                @endif
            </div>
            <div class="col-6">
                <div class="text-muted small">Grant Funded</div>
                <div class="fs-3 fw-bold lh-sm">{{ number_format($summary->grant_count) }}</div>
                <div class="text-muted" style="font-size: 0.75rem;">{{ number_format($summary->total - $summary->grant_count) }} without</div>
            </div>
            <div class="col-6">
                <div class="text-muted small">Departments</div>
                <div class="fs-3 fw-bold lh-sm">{{ number_format($summary->dept_count) }}</div>
            </div>
            <div class="col-6">
                <div class="text-muted small">Offices</div>
                <div class="fs-3 fw-bold lh-sm">{{ number_format($summary->office_count) }}</div>
            </div>
        </div>
    </div>
</div>
