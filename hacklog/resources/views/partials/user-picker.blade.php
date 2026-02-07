{{--
    User Picker Component

    @param \Illuminate\Database\Eloquent\Collection $users - Collection of users to display
    @param array $selectedUserIds - Array of selected user IDs
    @param string $inputName - Name attribute for the checkboxes (e.g. 'assignees[]')
--}}

<div class="user-picker">
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

    {{-- User list --}}
    <div class="border rounded p-3">
        @foreach($users as $user)
            <div class="form-check mb-2">
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