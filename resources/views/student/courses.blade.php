<x-layout title="browse_courses">
<div class="student-page">
<header class="student-page-header student-directory-header"><a class="student-back" href="{{ route('dashboard') }}" aria-label="{{ __('ui.back') }}"><x-icon name="arrow" :size="19" /></a><div><p class="eyebrow"><x-t k="learning_space" /></p><h1><x-t k="browse_courses" /></h1><span><x-t k="courses_page_hint" /></span></div></header>
<livewire:course-directory />
</div>
</x-layout>
