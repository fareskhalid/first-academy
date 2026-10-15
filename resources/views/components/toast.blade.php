@props(['type' => 'success', 'title' => null, 'timeout' => null])
<article class="app-toast is-{{ $type }} is-visible" data-toast data-timeout="{{ $timeout ?? ($type === 'error' ? 8000 : 5000) }}" role="{{ $type === 'error' ? 'alert' : 'status' }}">
    <span class="app-toast-icon" aria-hidden="true">{{ $type === 'error' ? '!' : '✓' }}</span>
    <div class="app-toast-content">
        @if($title)<strong>{{ $title }}</strong>@endif
        <div class="app-toast-message">{{ $slot }}</div>
    </div>
    <button type="button" class="app-toast-close" data-toast-dismiss aria-label="{{ __('ui.dismiss_notification') }}"><x-icon name="close" :size="17" /></button>
    @if(($timeout ?? ($type === 'error' ? 8000 : 5000)) > 0)<span class="app-toast-progress" style="--toast-duration: {{ $timeout ?? ($type === 'error' ? 8000 : 5000) }}ms" aria-hidden="true"></span>@endif
</article>
