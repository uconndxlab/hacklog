@extends('layouts.app')

@section('title', 'Organization Timeline')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1>Organization Timeline</h1>
                <p class="text-muted mb-0">Phase schedules across all projects</p>
            </div>
        </div>

        @include('timeline.partials.page')
    </div>
</div>
@endsection
