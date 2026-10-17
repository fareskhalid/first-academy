@props(['value'])
<span class="badge {{ in_array($value,['draft','withdrawn','archived','ended','completed','absent','void','cancelled','inactive']) ? 'neutral' : '' }} {{ in_array($value,['rejected','revoked','payment_rejected','payment_revoked','payment_required']) ? 'danger' : '' }} {{ in_array($value,['pending','payment_pending']) ? 'warning' : '' }}"><x-t :k="$value" /></span>
