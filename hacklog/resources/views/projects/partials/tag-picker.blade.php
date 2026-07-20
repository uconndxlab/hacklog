@php
    $hasTagErrors = $errors->has('tags') || $errors->has('tags.*') || $errors->has('new_tags');
@endphp

<div class="mb-3 js-tag-picker" data-tag-picker>
    <label for="{{ $selectId }}" class="form-label">Tags</label>

    <div class="hl-tag-picker d-none" data-tag-enhanced>
        <div class="hl-tag-picker-shell form-control {{ $hasTagErrors ? 'is-invalid' : '' }}" data-tag-shell>
            <div class="hl-tag-chip-list" data-tag-chip-list></div>
            <input
                type="text"
                class="hl-tag-picker-input"
                data-tag-search-input
                placeholder="Type to search existing tags or create new"
                autocomplete="off"
                aria-label="Add tags">
        </div>
        <div class="hl-tag-suggestions list-group" data-tag-suggestions role="listbox" hidden></div>
        <div class="form-text">Type to search existing tags. Press Enter or comma to add new tags.</div>
    </div>

    <select
        class="form-select @error('tags') is-invalid @enderror"
        id="{{ $selectId }}"
        name="tags[]"
        multiple
        size="6"
        data-tag-select>
        @foreach($availableTags as $tag)
            <option value="{{ $tag->id }}" {{ in_array($tag->id, $selectedTagIds, true) ? 'selected' : '' }}>
                {{ $tag->name }}
            </option>
        @endforeach
    </select>

    <input
        type="text"
        class="form-control mt-2 @error('new_tags') is-invalid @enderror"
        id="{{ $newTagsId }}"
        name="new_tags"
        value="{{ $newTagsValue }}"
        placeholder="Comma-separated, e.g. Security, Frontend"
        data-tag-new-input>

    <div class="form-text">New tags are created automatically when you save.</div>

    @error('tags')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('tags.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('new_tags')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
