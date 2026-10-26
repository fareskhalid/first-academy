@props([
    'primaryLinks',
    'moreLinks' => [],
    'hasUnreadNotifications' => false,
    'student' => false,
])

@php
    $isCurrentRoute = fn (string $route): bool => request()->routeIs($route, $route.'.*');
    $moreIsCurrent = collect($moreLinks)->keys()->contains(fn (string $route) => $isCurrentRoute($route));
    $moreHasUnread = collect($moreLinks)->keys()->contains('notifications') && $hasUnreadNotifications;
@endphp

<nav {{ $attributes->class(['nav', 'student-bottom-nav' => $student]) }} aria-label="{{ __('ui.app_name') }}">
    @foreach($primaryLinks as $route => [$key, $icon])
        <a href="{{ route($route) }}" aria-label="{{ __('ui.'.$key) }}" @if($isCurrentRoute($route)) aria-current="page" @endif>
            <span class="nav-icon">
                <x-icon :name="$icon" :size="19" />
                @if($route === 'notifications' && $hasUnreadNotifications)
                    <span class="nav-unread-dot" x-data="{ visible: true }" x-show="visible" x-on:notification-count-updated.window="visible = Number($event.detail.count) > 0" aria-hidden="true"></span>
                @endif
            </span>
            <x-t :class="$student ? 'student-nav-label' : ''" :k="$key" />
        </a>
    @endforeach

    @if(count($moreLinks))
        <details class="nav-more" @if($moreIsCurrent) data-current="true" @endif>
            <summary aria-label="{{ __('ui.more') }}" @if($moreIsCurrent) aria-current="page" @endif>
                <span class="nav-icon">
                    <x-icon name="more" :size="19" />
                    @if($moreHasUnread)
                        <span class="nav-unread-dot" x-data="{ visible: true }" x-show="visible" x-on:notification-count-updated.window="visible = Number($event.detail.count) > 0" aria-hidden="true"></span>
                    @endif
                </span>
                <x-t :class="$student ? 'student-nav-label' : ''" k="more" />
            </summary>
            <div class="nav-more-menu">
                @foreach($moreLinks as $route => [$key, $icon])
                    <a href="{{ route($route) }}" @if($isCurrentRoute($route)) aria-current="page" @endif>
                        <span class="nav-icon">
                            <x-icon :name="$icon" :size="18" />
                            @if($route === 'notifications' && $hasUnreadNotifications)
                                <span class="nav-unread-dot" x-data="{ visible: true }" x-show="visible" x-on:notification-count-updated.window="visible = Number($event.detail.count) > 0" aria-hidden="true"></span>
                            @endif
                        </span>
                        <x-t :k="$key" />
                    </a>
                @endforeach
            </div>
        </details>
    @endif
</nav>
