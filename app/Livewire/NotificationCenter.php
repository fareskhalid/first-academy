<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;

class NotificationCenter extends Component
{
    use WithPagination;

    public function paginationView(): string
    {
        return 'livewire.pagination';
    }

    public function markRead(string $id): void
    {
        abort_unless(auth()->check() && auth()->user()->status === 'active', 403);
        auth()->user()->notifications()->findOrFail($id)->markAsRead();
    }

    public function render()
    {
        abort_unless(auth()->check() && auth()->user()->status === 'active', 403);

        return view('livewire.notification-center', ['notices' => auth()->user()->notifications()->paginate(15)]);
    }
}
