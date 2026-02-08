{{--
    User Picker Component

    @param \Illuminate\Database\Eloquent\Collection $users - Collection of users to display
    @param array $selectedUserIds - Array of selected user IDs
    @param string $inputName - Name attribute for the checkboxes (e.g. 'assignees[]')
--}}

@php
    $pickerId = 'user-picker-' . md5($inputName . microtime());
@endphp

<div class="user-picker" id="{{ $pickerId }}">
    {{-- Selected users summary --}}
    <div class="mb-3">
        @if(!empty($selectedUserIds))
            <div class="text-muted small">
                <strong>Assigned:</strong>
                @php
                    $selectedUsers = $users->whereIn('id', $selectedUserIds);
                @endphp
                {{ $selectedUsers->pluck('name')->join(', ') }}
            </div>
        @else
            <div class="text-muted small">Unassigned</div>
        @endif
    </div>

    {{-- Search input --}}
    <div class="mb-2">
        <input
            type="text"
            class="form-control form-control-sm user-picker-search"
            placeholder="Search users..."
            autocomplete="off">
    </div>

    {{-- User list --}}
    <div class="border rounded p-3 user-picker-list" style="max-height: 300px; overflow-y: auto;">
        @foreach($users as $user)
            <div class="form-check mb-2 user-picker-item" data-user-name="{{ strtolower($user->name) }}">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="{{ $inputName }}_{{ $user->id }}"
                    name="{{ $inputName }}"
                    value="{{ $user->id }}"
                    {{ in_array($user->id, $selectedUserIds) ? 'checked' : '' }}>
                <label class="form-check-label w-100" for="{{ $inputName }}_{{ $user->id }}">
                    {{ $user->name }}
                </label>
            </div>
        @endforeach
    </div>
</div>

<script>
(function() {
    const picker = document.getElementById('{{ $pickerId }}');
    const searchInput = picker.querySelector('.user-picker-search');
    const userItems = picker.querySelectorAll('.user-picker-item');

    searchInput.addEventListener('input', function() {
        const searchTerms = this.value.toLowerCase().trim().split(/\s+/).filter(term => term.length > 0);
        
        userItems.forEach(function(item) {
            const userName = item.getAttribute('data-user-name');
            
            if (searchTerms.length === 0) {
                // No search terms - show all
                item.style.display = '';
            } else {
                // Check if all search terms are found in the user name
                const allTermsMatch = searchTerms.every(term => userName.includes(term));
                item.style.display = allTermsMatch ? '' : 'none';
            }
        });
    });
})();
</script>