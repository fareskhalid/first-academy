<div>
    <section class="notification-hero">
        <div class="notification-hero-icon"><x-icon name="bell" :size="30" /></div>
        <div class="notification-hero-copy">
            <div class="eyebrow"><x-t k="notification_center" /></div>
            <h1><x-t k="notifications" /></h1>
            <p><x-t k="notification_hint" /></p>
        </div>
        <div class="notification-stats">
            <div><strong>{{ $unreadCount }}</strong><span><x-t k="unread" /></span></div>
            <div><strong>{{ $notices->total() }}</strong><span><x-t k="notifications_total" /></span></div>
        </div>
    </section>

    <div class="notification-toolbar">
        <div><h2><x-t k="recent_notifications" /></h2><p><x-t k="notification_list_hint" /></p></div>
        @if($unreadCount > 0)
            <button type="button" class="secondary" wire:click="markAllRead" wire:loading.attr="disabled" wire:target="markAllRead"><x-icon name="check" :size="17" /><x-t k="mark_all_read" /></button>
        @else
            <span class="notification-caught-up"><x-icon name="check" :size="17" /><x-t k="all_caught_up" /></span>
        @endif
    </div>

    <div class="notification-list">
    @forelse($notices as $notice)
        @php($key = $notice->data['key'] ?? 'notifications')
        @php($noticeIcon = str_contains($key, 'session') || str_contains($key, 'schedule') ? 'calendar' : (str_contains($key, 'enroll') || str_contains($key, 'course') ? 'book' : 'bell'))
        <article class="notification-item {{ $notice->read_at ? 'is-read' : 'is-unread' }}" wire:key="notice-{{ $notice->id }}">
            <div class="notification-item-icon"><x-icon :name="$noticeIcon" :size="21" />@if(!$notice->read_at)<span aria-hidden="true"></span>@endif</div>
            <div class="notification-item-copy">
                <p><x-t :k="$key" :params="$notice->data['parameters'] ?? []" /></p>
                <time datetime="{{ $notice->created_at->toIso8601String() }}" title="{{ $notice->created_at->timezone('Africa/Cairo')->locale(app()->getLocale())->translatedFormat('Y-m-d H:i') }}">{{ $notice->created_at->timezone('Africa/Cairo')->locale(app()->getLocale())->diffForHumans() }}</time>
            </div>
            <div class="notification-item-actions">
                @if(!empty($notice->data['url']))<a class="notification-open" href="{{ $notice->data['url'] }}"><x-t k="open_notification" /><x-icon name="arrow" :size="16" /></a>@endif
                @if(!$notice->read_at)
                    <button type="button" class="notification-read-button" wire:click="markRead('{{ $notice->id }}')" wire:loading.attr="disabled" wire:target="markRead('{{ $notice->id }}')"><x-icon name="check" :size="16" /><x-t k="mark_read" /></button>
                @else
                    <span class="notification-read-state"><x-icon name="check" :size="15" /><x-t k="read" /></span>
                @endif
            </div>
        </article>
    @empty
        <div class="notification-empty"><span><x-icon name="bell" :size="30" /></span><h2><x-t k="empty_notifications" /></h2><p><x-t k="empty_notifications_hint" /></p></div>
    @endforelse
    </div>

    <div class="mt-5">{{ $notices->links() }}</div>
</div>
