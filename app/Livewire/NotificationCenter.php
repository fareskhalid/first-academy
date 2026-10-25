<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;

class NotificationCenter extends Component
{
    use WithPagination;

    public string $filter = 'all';

    public function paginationView(): string
    {
        return 'livewire.pagination';
    }

    public function markRead(string $id): void
    {
        abort_unless(auth()->check() && auth()->user()->status === 'active', 403);
        auth()->user()->notifications()->findOrFail($id)->markAsRead();
        $this->dispatchUnreadCount();
    }

    public function markAllRead(): void
    {
        abort_unless(auth()->check() && auth()->user()->status === 'active', 403);
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        $this->dispatchUnreadCount();
    }

    public function setFilter(string $filter): void
    {
        abort_unless(in_array($filter, ['all', 'unread'], true), 422);
        $this->filter = $filter;
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(auth()->check() && auth()->user()->status === 'active', 403);

        $this->filter = in_array($this->filter, ['all', 'unread'], true) ? $this->filter : 'all';
        $notifications = auth()->user()->notifications();

        return view('livewire.notification-center', [
            'notices' => (clone $notifications)
                ->when($this->filter === 'unread', fn ($query) => $query->whereNull('read_at'))
                ->paginate(15),
            'totalCount' => (clone $notifications)->count(),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ]);
    }

    private function dispatchUnreadCount(): void
    {
        $this->dispatch(
            'notification-count-updated',
            count: auth()->user()->unreadNotifications()->count(),
        );
    }
}
