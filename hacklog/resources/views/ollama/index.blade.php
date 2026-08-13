@extends('layouts.app')

@section('title', 'AI Test')

@section('content')
<div class="row">
    <div class="col-lg-9">
        <h1 class="mb-4">AI Test</h1>

        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('ollama.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="prompt" class="form-label">Prompt</label>
                        <textarea
                            class="form-control @error('prompt') is-invalid @enderror"
                            id="prompt"
                            name="prompt"
                            rows="6"
                            placeholder="Type a prompt to send to the local Ollama model..."
                            required>{{ old('prompt', $prompt ?? '') }}</textarea>
                        @error('prompt')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    @if(auth()->user()->isAdmin())
                        <div class="form-check mb-3">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="debug_tools"
                                name="debug_tools"
                                value="1"
                                {{ old('debug_tools', $debugTools ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="debug_tools">
                                Show tool-calling debug output (admin only)
                            </label>
                        </div>
                    @endif

                    <button type="submit" class="btn btn-primary">Send to Ollama</button>
                </form>
            </div>
        </div>

        @if(!empty($response))
            <div class="card">
                <div class="card-header text-white">Model Response</div>
                <div class="card-body">
                    <pre class="mb-0" style="white-space: pre-wrap; word-wrap: break-word;">{{ $response }}</pre>
                </div>
            </div>
        @endif

        @if(!empty($toolDebug))
            <div class="card mt-4">
                <div class="card-header text-white">Tool Debug</div>
                <div class="card-body">
                    @foreach($toolDebug as $index => $entry)
                        <div class="mb-3">
                            <h6 class="mb-2">Tool Call {{ $index + 1 }}: {{ $entry['tool'] ?? 'unknown' }}</h6>
                            <div class="mb-2">
                                <strong>Validated Arguments</strong>
                                <pre class="mb-0" style="white-space: pre-wrap; word-wrap: break-word;">{{ json_encode($entry['validated_arguments'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                            <div class="mb-2">
                                <strong>Tool Result</strong>
                                <pre class="mb-0" style="white-space: pre-wrap; word-wrap: break-word;">{{ json_encode($entry['result'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                            @if(!empty($entry['error']))
                                <div class="alert alert-warning py-2 mb-0">{{ $entry['error'] }}</div>
                            @endif
                        </div>
                        @if(!$loop->last)
                            <hr>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
