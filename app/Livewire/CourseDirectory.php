<?php

namespace App\Livewire;

use App\Models\CourseOffering;
use Livewire\Component;
use Livewire\WithPagination;

class CourseDirectory extends Component
{
    use WithPagination;

    public function paginationView(): string
    {
        return 'livewire.pagination';
    }

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(auth()->check() && auth()->user()->role === 'student' && auth()->user()->status === 'active', 403);
        $search = mb_substr($this->search, 0, 100);
        $offerings = CourseOffering::where('status', 'open')->where('self_enrollment', true)->whereHas('course', fn ($q) => $q->whereNull('archived_at'))->whereHas('semester', fn ($q) => $q->whereNull('archived_at'))->where('title', 'like', '%'.addcslashes($search, '%_\\').'%')->with(['course', 'semester', 'instructor:id,name'])->withCount(['enrollments as joined' => fn ($q) => $q->where('student_id', auth()->id())])->orderBy('title')->paginate(12);

        return view('livewire.course-directory', compact('offerings'));
    }
}
