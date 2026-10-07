@props(['name','label' => null,'checked' => false])
<label class="check"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name,$checked)) {{ $attributes }}><x-t :k="$label ?? $name" /></label>
