<div class="notification-social">
    <header class="notification-social-header">
        <div class="notification-social-title">
            <span class="notification-social-icon"><x-icon name="bell" :size="27" />@if($unreadCount > 0)<i aria-hidden="true"></i>@endif</span>
            <div><p class="eyebrow"><x-t k="notification_center" /></p><h1><x-t k="notifications" /></h1><p><x-t k="notification_hint" /></p></div>
        </div>
        @if($unreadCount > 0)
            <button type="button" class="notification-mark-all" wire:click="markAllRead" wire:loading.attr="disabled" wire:target="markAllRead"><x-icon name="check" :size="17" /><x-t k="mark_all_read" /></button>
        @else
            <span class="notification-caught-up"><x-icon name="check" :size="17" /><x-t k="all_caught_up" /></span>
        @endif
    </header>

    <section class="notification-social-panel">
        <div class="notification-feed-toolbar">
            <nav class="notification-tabs" aria-label="{{ __('ui.notification_filters') }}">
                <button type="button" wire:click="setFilter('all')" wire:loading.attr="disabled" @if($filter === 'all') aria-current="page" @endif><x-t k="all_notifications" /><span>{{ $totalCount }}</span></button>
                <button type="button" wire:click="setFilter('unread')" wire:loading.attr="disabled" @if($filter === 'unread') aria-current="page" @endif><x-t k="unread" /><span>{{ $unreadCount }}</span></button>
            </nav>
            <p><x-t k="notification_list_hint" /></p>
        </div>

        <div class="notification-feed" wire:loading.class="is-loading">
        @forelse($notices as $notice)
            @php($key = $notice->data['key'] ?? 'notifications')
            @php($noticeType = str_contains($key, 'session') || str_contains($key, 'schedule') ? 'schedule' : (str_contains($key, 'enroll') || str_contains($key, 'course') || str_contains($key, 'transfer') ? 'course' : 'account'))
            @php($noticeIcon = $noticeType === 'schedule' ? 'calendar' : ($noticeType === 'course' ? 'book' : 'bell'))
            <article class="notification-feed-item is-{{ $noticeType }} {{ $notice->read_at ? 'is-read' : 'is-unread' }}" wire:key="notice-{{ $notice->id }}">
                <span class="notification-avatar"><x-icon :name="$noticeIcon" :size="21" />@if(!$notice->read_at)<i aria-hidden="true"></i>@endif</span>
                <div class="notification-feed-content">
                    <div class="notification-feed-meta">
                        <strong><x-t k="app_name" /></strong>
                        <span aria-hidden="true">·</span>
                        <time datetime="{{ $notice->created_at->toIso8601String() }}" title="{{ $notice->created_at->timezone('Africa/Cairo')->locale(app()->getLocale())->translatedFormat('Y-m-d h:i A') }}">{{ $notice->created_at->timezone('Africa/Cairo')->locale(app()->getLocale())->diffForHumans() }}</time>
                    </div>
                    <p><x-t :k="$key" :params="$notice->data['parameters'] ?? []" /></p>
                    <div class="notification-feed-actions">
                        @if(!empty($notice->data['url']))<a href="{{ $notice->data['url'] }}"><x-t k="open_notification" /><x-icon name="arrow" :size="15" /></a>@endif
                        @if(!$notice->read_at)
                            <button type="button" wire:click="markRead('{{ $notice->id }}')" wire:loading.attr="disabled" wire:target="markRead('{{ $notice->id }}')"><x-icon name="check" :size="15" /><x-t k="mark_read" /></button>
                        @else
                            <span class="notification-read-state"><x-icon name="check" :size="14" /><x-t k="read" /></span>
                        @endif
                    </div>
                </div>
                @if(!$notice->read_at)<span class="notification-unread-indicator" aria-label="{{ __('ui.unread') }}"></span>@endif
            </article>
        @empty
            <div class="notification-empty"><span><x-icon name="bell" :size="30" /></span><h2><x-t :k="$filter === 'unread' ? 'no_unread_notifications' : 'empty_notifications'" /></h2><p><x-t :k="$filter === 'unread' ? 'no_unread_notifications_hint' : 'empty_notifications_hint'" /></p></div>
        @endforelse
        </div>

        @if($notices->hasPages())<div class="notification-pagination">{{ $notices->links() }}</div>@endif
    </section>
</div>
