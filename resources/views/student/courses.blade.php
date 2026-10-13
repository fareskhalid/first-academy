<x-layout title="browse_courses">
<div class="student-page">
<header class="student-page-header"><a class="student-back" href="{{ route('dashboard') }}"><x-icon name="arrow" :size="19" /></a><div><p class="eyebrow"><x-t k="student" /></p><h1><x-t k="browse_courses" /></h1></div></header>
<livewire:course-directory />
</div>
</x-layout>
