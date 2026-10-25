@php
    $editingMaterial = $material ?? null;
    $defaultGroups = $editingMaterial?->groups->pluck('id')->all()
        ?? ($activeGroups->count() === 1 ? [$activeGroups->first()->id] : []);
    $selectedGroups = old('group_ids', $defaultGroups);
    $selectedType = old('type', $editingMaterial?->type ?? 'pdf');
    $formId = $editingMaterial ? 'material-'.$editingMaterial->id : 'new-material';
@endphp
<form
    method="POST"
    action="{{ $editingMaterial ? route('instructor.materials.update', [$offering, $editingMaterial]) : route('instructor.materials.store', $offering) }}"
    enctype="multipart/form-data"
    class="stack material-editor"
    x-data="{ type: @js($selectedType), selected: @js(array_map('intval', $selectedGroups)), allGroups: @js($activeGroups->pluck('id')->values()) }"
>
    @csrf
    @if($editingMaterial) @method('PUT') @endif

    <div class="grid two">
        <x-field name="title" :id="$formId.'-title'" label="title_en" :value="$editingMaterial?->title" required maxlength="150" dir="ltr" />
        <x-field name="title_ar" :id="$formId.'-title-ar'" label="title_ar" :value="$editingMaterial?->title_ar" required maxlength="150" dir="rtl" />
    </div>
    <div class="grid two">
        <div class="field">
            <label for="{{ $formId }}-description"><x-t k="description_en" /></label>
            <textarea id="{{ $formId }}-description" name="description" maxlength="2000" dir="ltr">{{ old('description', $editingMaterial?->description) }}</textarea>
        </div>
        <div class="field">
            <label for="{{ $formId }}-description-ar"><x-t k="description_ar" /></label>
            <textarea id="{{ $formId }}-description-ar" name="description_ar" maxlength="2000" dir="rtl">{{ old('description_ar', $editingMaterial?->description_ar) }}</textarea>
        </div>
    </div>
    <div class="grid three">
        <x-select name="type" :id="$formId.'-type'" label="material_type" required x-model="type">
            @foreach(\App\Models\CourseMaterial::TYPES as $type)<option value="{{ $type }}" @selected($selectedType === $type)>{{ __('ui.'.$type) }}</option>@endforeach
        </x-select>
        <x-select name="lesson_id" :id="$formId.'-lesson'" label="lesson">
            <option value=""><x-t k="general_materials" /></option>
            @foreach($activeLessons as $lesson)<option value="{{ $lesson->id }}" @selected((string) old('lesson_id', $editingMaterial?->lesson_id) === (string) $lesson->id)>{{ $lesson->display_title }}</option>@endforeach
        </x-select>
        <x-field name="position" :id="$formId.'-position'" type="number" min="1" max="10000" :value="$editingMaterial?->position ?? (($offering->materials->max('position') ?? 0) + 1)" required />
    </div>

    <div class="material-source-field" x-show="type === 'youtube'" x-cloak>
        <x-field name="youtube_url" :id="$formId.'-youtube'" type="url" label="youtube_url" :value="$editingMaterial?->youtube_video_id ? 'https://youtu.be/'.$editingMaterial->youtube_video_id : ''" maxlength="500" placeholder="https://www.youtube.com/watch?v=..." dir="ltr" x-bind:disabled="type !== 'youtube'" x-bind:required="type === 'youtube'" />
    </div>
    <div class="field material-source-field" x-show="type === 'pdf' || type === 'image'" x-cloak>
        <label for="{{ $formId }}-file"><x-t k="upload_file" /></label>
        <input id="{{ $formId }}-file" name="file" type="file" :accept="type === 'pdf' ? '.pdf,application/pdf' : '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp'" x-bind:disabled="type === 'youtube'">
        @if($editingMaterial?->file_path)<small class="muted"><x-t k="file_optional_hint" /> · {{ $editingMaterial->original_name }}</small>@endif
    </div>

    <fieldset class="material-group-picker">
        <legend><x-t k="material_groups" /></legend>
        <button type="button" class="material-select-all" @click="selected = selected.length === allGroups.length ? [] : [...allGroups]"><x-t k="select_all_groups" /></button>
        <div>
            @foreach($activeGroups as $group)
                <label><input type="checkbox" name="group_ids[]" value="{{ $group->id }}" x-model.number="selected" @checked(in_array($group->id, array_map('intval', $selectedGroups), true))><span>{{ $group->display_name }}</span></label>
            @endforeach
        </div>
    </fieldset>

    <div class="material-editor-actions">
        <x-select name="status" :id="$formId.'-status'" label="status" required>
            @foreach(['draft', 'published'] as $status)<option value="{{ $status }}" @selected(old('status', $editingMaterial?->status ?? 'draft') === $status)>{{ __('ui.'.$status) }}</option>@endforeach
        </x-select>
        <button class="primary"><x-icon name="check" :size="17" /><x-t k="save" /></button>
    </div>
</form>
