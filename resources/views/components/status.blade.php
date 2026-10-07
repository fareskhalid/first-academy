@props(['value'])
<span class="badge {{ in_array($value,['draft','withdrawn','archived','completed']) ? 'neutral' : '' }}"><x-t :k="$value" /></span>
