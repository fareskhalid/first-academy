@props(['name', 'label' => null, 'type' => 'text', 'value' => '', 'required' => false, 'hint' => null])
<div class="field">
    <label for="{{ $attributes->get('id', $name) }}"><x-t :k="$label ?? $name" /></label>
    <input name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" type="{{ $type }}" @if($type !== 'password') value="{{ old($name, $value) }}" @endif @required($required) @if($errors->has($name)) aria-invalid="true" @endif @if($hint) aria-describedby="{{ $attributes->get('id', $name) }}-hint" @endif {{ $attributes->except('id') }}>
    @if($hint)<span id="{{ $attributes->get('id', $name) }}-hint" class="muted"><x-t :k="$hint" /></span>@endif
</div>
