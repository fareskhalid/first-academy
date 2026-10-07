@props(['name', 'label' => null])
<div class="field"><label for="{{ $attributes->get('id', $name) }}"><x-t :k="$label ?? $name" /></label><select name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" {{ $attributes->except('id') }}>{{ $slot }}</select></div>
