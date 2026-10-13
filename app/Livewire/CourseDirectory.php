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
        $escaped = '%'.addcslashes($search, '%_\\').'%';
        $offerings = CourseOffering::whereHas('enrollments', fn ($q) => $q->where('student_id', auth()->id()))
            ->where(function ($query) use ($escaped) {
                $query->where('title', 'like', $escaped)
                    ->orWhere('title_ar', 'like', $escaped)
                    ->orWhereHas('course', fn ($course) => $course
                        ->where('name', 'like', $escaped)
                        ->orWhere('name_ar', 'like', $escaped)
                        ->orWhere('code', 'like', $escaped)
                        ->orWhere('description', 'like', $escaped)
                        ->orWhere('description_ar', 'like', $escaped));
            })
            ->with([
                'course',
                'semester',
                'instructor:id,name',
                'enrollments' => fn ($q) => $q->where('student_id', auth()->id()),
            ])
            ->orderBy(app()->getLocale() === 'ar' ? 'title_ar' : 'title')
            ->paginate(12);

        return view('livewire.course-directory', compact('offerings'));
    }
}
