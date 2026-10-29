<x-layout title="course_materials">
<div class="student-page learning-library-page">
    <a class="learning-library-back" href="{{ route('student.courses.show', $offering) }}"><x-icon name="arrow" :size="17" /><x-t k="back_to_course" /></a>

    <header class="learning-library-hero">
        <span class="learning-library-hero-icon"><x-icon name="files" :size="30" /></span>
        <div class="learning-library-hero-copy">
            <p class="eyebrow"><x-t k="learning_library" /></p>
            <h1><x-t k="course_materials" /></h1>
            <p>{{ $offering->display_title }} · {{ $enrollment->group->display_name }}</p>
        </div>
    </header>

    <section class="learning-library-insights" aria-label="{{ __('ui.materials_overview') }}">
        <article><span><x-icon name="files" :size="20" /></span><div><strong>{{ $insights['total'] }}</strong><small><x-t k="all_materials" /></small></div></article>
        <article><span><x-icon name="play" :size="20" /></span><div><strong>{{ $insights['youtube'] }}</strong><small><x-t k="video_lessons" /></small></div></article>
        <article><span><x-icon name="file" :size="20" /></span><div><strong>{{ $insights['pdf'] }}</strong><small><x-t k="pdf_resources" /></small></div></article>
        <article><span><x-icon name="image" :size="20" /></span><div><strong>{{ $insights['image'] }}</strong><small><x-t k="picture_resources" /></small></div></article>
    </section>

    <nav class="learning-library-type-tabs" aria-label="{{ __('ui.material_type') }}">
        @foreach([
            'all' => ['all_materials', 'files', $insights['total']],
            'youtube' => ['video_lessons', 'play', $insights['youtube']],
            'pdf' => ['pdf_resources', 'file', $insights['pdf']],
            'image' => ['picture_resources', 'image', $insights['image']],
        ] as $value => [$label, $icon, $count])
            <a href="{{ request()->fullUrlWithQuery(['type' => $value]) }}" @if($type === $value) aria-current="page" @endif><x-icon :name="$icon" :size="16" /><span><x-t :k="$label" /></span><small>{{ $count }}</small></a>
        @endforeach
    </nav>

    <section class="panel learning-library-filter-panel">
        <form method="GET" action="{{ route('student.courses.materials', $offering) }}" class="learning-library-filters">
            <input type="hidden" name="type" value="{{ $type }}">
            <x-field name="search" label="search_materials" type="search" :value="$search" maxlength="100" autocomplete="off" />
            <x-select name="lesson" label="lesson">
                <option value="">{{ __('ui.all_lessons') }}</option>
                @foreach($lessons as $lesson)<option value="{{ $lesson->id }}" @selected((string) $lessonId === (string) $lesson->id)>{{ $lesson->position }}. {{ $lesson->display_title }}</option>@endforeach
            </x-select>
            <button class="primary"><x-icon name="search" :size="17" /><x-t k="browse_resources" /></button>
            @if($search !== '' || $type !== 'all' || $lessonId !== '')<a class="button secondary" href="{{ route('student.courses.materials', $offering) }}"><x-t k="clear_filters" /></a>@endif
        </form>
    </section>

    <div class="learning-library-results">
        <div class="learning-library-results-heading">
            <div><p class="eyebrow"><x-t k="your_learning_path" /></p><h2><x-t k="available_resources" /></h2></div>
            <span>{{ __('ui.material_results', ['count' => $materials->count()]) }}</span>
        </div>

        @forelse($materials->groupBy(fn ($material) => $material->lesson_id ?: 'general') as $section => $sectionMaterials)
            @php($sectionLesson = $sectionMaterials->first()->lesson)
            <section class="learning-library-module">
                <header class="learning-library-module-heading">
                    <span>@if($sectionLesson){{ $sectionLesson->position }}@else<x-icon name="sparkles" :size="18" />@endif</span>
                    <div>
                        <small>@if($sectionLesson)<x-t k="lesson" /> {{ $sectionLesson->position }}@else<x-t k="start_here" />@endif</small>
                        <h2>{{ $sectionLesson?->display_title ?? __('ui.general_materials') }}</h2>
                    </div>
                    <strong>{{ __('ui.resource_count', ['count' => $sectionMaterials->count()]) }}</strong>
                </header>

                <div class="learning-library-grid">
                    @foreach($sectionMaterials as $material)
                        <article class="learning-resource-card is-{{ $material->type }}">
                            <header>
                                <span class="learning-resource-icon"><x-icon :name="$material->type === 'youtube' ? 'play' : ($material->type === 'image' ? 'image' : 'file')" :size="21" /></span>
                                <div><small><x-t :k="$material->type" /></small><h3>{{ $material->display_title }}</h3></div>
                                <span class="learning-resource-order">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            </header>

                            @if($material->display_description)<p>{{ $material->display_description }}</p>@endif

                            @if($material->type === 'youtube')
                                <div class="learning-resource-video">
                                    <iframe src="{{ $material->youtube_embed_url }}" title="{{ $material->display_title }}" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                                </div>
                            @elseif($material->type === 'image')
                                <a class="learning-resource-image" href="{{ route('course-materials.file', $material) }}" target="_blank" rel="noopener">
                                    <img src="{{ route('course-materials.file', $material) }}" alt="{{ $material->display_title }}" loading="lazy">
                                    <span><x-icon name="view" :size="17" /><x-t k="open_picture" /></span>
                                </a>
                            @else
                                <div class="learning-resource-document">
                                    <span><x-icon name="file" :size="25" /></span>
                                    <div><strong><x-t k="pdf_resource" /></strong>@if($material->file_size)<small>{{ \Illuminate\Support\Number::fileSize($material->file_size) }}</small>@endif</div>
                                    <a class="button primary" href="{{ route('course-materials.file', $material) }}" target="_blank" rel="noopener"><x-icon name="view" :size="16" /><x-t k="open_pdf" /></a>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="learning-library-empty"><span><x-icon name="files" :size="30" /></span><h2><x-t k="no_materials" /></h2><p><x-t k="no_materials_filter_hint" /></p>@if($search !== '' || $type !== 'all' || $lessonId !== '')<a class="button secondary" href="{{ route('student.courses.materials', $offering) }}"><x-t k="clear_filters" /></a>@endif</div>
        @endforelse
    </div>
</div>
</x-layout>
