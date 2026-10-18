<x-layout title="browse_courses">
<div class="student-page">
<header class="student-directory-hero">
    <a class="student-back" href="{{ route('dashboard') }}" aria-label="{{ __('ui.back') }}"><x-icon name="arrow" :size="19" /></a>
    <span class="student-directory-hero-icon"><x-icon name="book" :size="27" /></span>
    <div><p class="eyebrow"><x-t k="learning_space" /></p><h1><x-t k="browse_courses" /></h1><p><x-t k="courses_page_hint" /></p></div>
</header>
<livewire:course-directory />
</div>
</x-layout>
