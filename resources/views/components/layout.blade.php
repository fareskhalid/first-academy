@props(['title' => 'app_name', 'guest' => false])
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('ui.'.$title) }} · {{ __('ui.app_name') }}</title>
    <meta name="robots" content="noindex,nofollow">
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
</head>
<body x-data>
<a class="skip" href="#main"><x-t k="skip" /></a>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="{{ auth()->check() ? route('dashboard') : route('login') }}"><span class="brand-mark" aria-hidden="true">C</span><x-t k="app_name" /></a>
        <div class="tools">
            @auth <span class="muted role"><x-t :k="auth()->user()->role" /></span> @endauth
            <button type="button" class="secondary" data-testid="language-toggle" @click="$store.i18n.toggle()" :disabled="$store.i18n.busy" :aria-label="$store.i18n.locale === 'ar' ? 'Switch to English' : 'التبديل إلى العربية'" x-text="$store.i18n.locale === 'ar' ? 'English' : 'العربية'">{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</button>
            @auth <form method="POST" action="{{ route('logout') }}">@csrf<button class="secondary"><x-t k="logout" /></button></form> @endauth
        </div>
    </div>
    @auth
    <nav class="nav" aria-label="{{ __('ui.app_name') }}">
        @php($links = auth()->user()->isInstructor() ? ['dashboard'=>'dashboard','instructor.setup'=>'setup','instructor.offerings'=>'offerings','notifications'=>'notifications','instructor.audit'=>'audit','profile'=>'profile'] : ['dashboard'=>'my_courses','student.courses'=>'browse_courses','notifications'=>'notifications','profile'=>'profile'])
        @foreach($links as $route => $key)<a href="{{ route($route) }}" @if(request()->routeIs($route, $route.'.*')) aria-current="page" @endif><x-t :k="$key" /></a>@endforeach
    </nav>
    @endauth
</header>
<main id="main" class="{{ $guest ? 'auth-shell' : 'shell' }}">
    @if($guest)<aside class="auth-intro"><div class="eyebrow"><x-t k="app_name" /></div><h1><x-t k="tagline" /></h1><p class="muted"><x-t k="account_note" /></p><div class="motif" aria-hidden="true"></div></aside><div>@endif
    <div class="alert error" x-cloak x-show="$store.i18n.error" x-text="$store.i18n.error" role="alert"></div>
    @if(isset($errors) && $errors->any())<div class="alert error" role="alert"><strong><x-t k="check_errors" /></strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(session('status_key'))<div class="alert" role="status"><x-t :k="session('status_key')" /></div>@endif
    @if(session('registered_code'))<div class="alert" role="status"><x-t k="registered" /> <strong dir="ltr">{{ session('registered_code') }}</strong></div>@endif
    {{ $slot }}
    @if($guest)</div>@endif
</main>
<script>window.courseApp = {{ Illuminate\Support\Js::from(['messages'=>['en'=>trans('ui',[], 'en'),'ar'=>trans('ui',[], 'ar')], 'authenticated'=>auth()->check(), 'localeUrl'=>route('locale',[],false), 'csrf'=>csrf_token()]) }};</script>
@livewireScriptConfig
</body>
</html>
