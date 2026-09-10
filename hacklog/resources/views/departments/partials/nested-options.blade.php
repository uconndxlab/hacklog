@php
    $departmentSelected = $departmentSelected ?? false;
@endphp
<option value="">{{ $departmentSelected ? 'Select nested department…' : 'Please select department first' }}</option>
@foreach($nestedDepartments as $nested)
    <option value="{{ $nested->id }}" @selected((string) $selectedNestedId === (string) $nested->id)>
        {{ $nested->name }}
    </option>
@endforeach
