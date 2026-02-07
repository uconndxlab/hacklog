{{-- 
    Reusable empty state component
    
    Props:
    - $message: Main message to display
    - $actionUrl: URL for the action link
    - $actionText: Text for the action link
--}}
<div class="alert alert-light border">
    <p class="mb-0 text-muted">
        {{ $message }}
        @if(isset($actionUrl) && isset($actionText))
            <a href="{{ $actionUrl }}" class="alert-link">{{ $actionText }}</a>
        @endif
    </p>
</div>
