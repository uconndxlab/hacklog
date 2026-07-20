@extends('layouts.app')

@section('title', 'Tags')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0">Tags</h1>
        <small class="text-muted">Manage reusable tags for projects.</small>
    </div>
    <a href="{{ route('tags.create') }}" class="btn btn-primary">New Tag</a>
</div>

<div class="card">
    <div class="card-body p-0">
        @if($tags->isEmpty())
            <div class="p-4 text-muted">No tags yet.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Color</th>
                            <th scope="col">Projects</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tags as $tag)
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $tag->name }}</span>
                                </td>
                                <td>
                                    @if($tag->color)
                                        <span class="badge border" style="background-color: {{ $tag->color }}; color: #111;">{{ $tag->color }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $tag->projects_count }}</td>
                                <td class="text-end">
                                    <a href="{{ route('tags.edit', $tag) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <form action="{{ route('tags.destroy', $tag) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this tag?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
