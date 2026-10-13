@props(['value'])
<span class="badge {{ in_array($value,['draft','withdrawn','archived','ended','completed','absent','void','cancelled']) ? 'neutral' : '' }}"><x-t :k="$value" /></span>
