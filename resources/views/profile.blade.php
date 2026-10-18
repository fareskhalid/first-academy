<x-layout title="profile">
@if(!auth()->user()->isInstructor())
<div class="student-page student-profile-page">
    <header class="student-profile-hero">
        <span class="student-profile-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
        <div><p class="eyebrow"><x-t k="profile" /></p><h1>{{ auth()->user()->name }}</h1><p><x-t k="student_profile_hint" /></p></div>
        <div class="student-profile-code"><small><x-t k="student_code" /></small><strong dir="ltr">{{ auth()->user()->student_code }}</strong></div>
    </header>

    @if(auth()->user()->must_change_password)<div class="alert"><x-t k="forced_password" /></div>@endif

    <section class="student-profile-summary" aria-label="{{ __('ui.profile') }}">
        <article><span><x-icon name="phone" :size="19" /></span><div><small><x-t k="phone" /></small><strong dir="ltr">{{ auth()->user()->phone }}</strong></div></article>
        <article><span><x-icon name="message" :size="19" /></span><div><small><x-t k="whatsapp_phone" /></small><strong dir="ltr">{{ auth()->user()->profile?->whatsapp_phone }}</strong></div></article>
    </section>

    <div class="student-profile-grid">
    @if(!auth()->user()->must_change_password)
        <section class="student-profile-panel">
            <header><span><x-icon name="user" :size="21" /></span><div><h2><x-t k="personal_information" /></h2><p><x-t k="personal_information_hint" /></p></div></header>
            <form method="POST" action="{{ route('profile.update') }}" class="stack" x-data="{ submitting: false }" @submit="submitting = true">@csrf @method('PUT')
                <x-field name="name" :value="auth()->user()->name" required autocomplete="name" maxlength="150" />
                <x-field name="phone" :value="auth()->user()->phone" type="tel" required dir="ltr" maxlength="32" />
                <x-field name="whatsapp_phone" :value="auth()->user()->profile?->whatsapp_phone" type="tel" required dir="ltr" maxlength="32" />
                <x-check name="whatsapp_declared" required />
                <x-field name="university" :value="auth()->user()->profile?->university" maxlength="150" />
                <x-field name="university_student_id" :value="auth()->user()->profile?->university_student_id" maxlength="100" />
                <x-field name="current_password" type="password" required autocomplete="current-password" maxlength="255" />
                <button class="primary" :disabled="submitting"><span x-show="!submitting"><x-t k="save" /></span><span x-cloak x-show="submitting"><x-t k="please_wait" /></span></button>
            </form>
        </section>
    @endif
        <section class="student-profile-panel student-security-panel">
            <header><span><x-icon name="shield" :size="21" /></span><div><h2><x-t k="account_security" /></h2><p><x-t k="account_security_hint" /></p></div></header>
            <form method="POST" action="{{ route('profile.password') }}" class="stack" x-data="{ submitting: false }" @submit="submitting = true">@csrf @method('PUT')
                <x-field name="current_password" id="password-current" type="password" required autocomplete="current-password" maxlength="255" />
                <x-field name="password" label="new_password" type="password" required autocomplete="new-password" minlength="8" maxlength="255" hint="password_hint" />
                <x-field name="password_confirmation" type="password" required autocomplete="new-password" maxlength="255" />
                <button class="primary" :disabled="submitting"><span x-show="!submitting"><x-t k="change_password" /></span><span x-cloak x-show="submitting"><x-t k="please_wait" /></span></button>
            </form>
        </section>
    </div>
</div>
@else
<div class="hero"><div><div class="eyebrow"><x-t k="profile" /></div><h1>{{ auth()->user()->name }}</h1></div></div>
@if(auth()->user()->must_change_password)<div class="alert"><x-t k="forced_password" /></div>@endif
<div class="grid two">
@if(!auth()->user()->must_change_password)
<section class="panel"><h2><x-t k="profile" /></h2><form method="POST" action="{{ route('profile.update') }}" class="stack">@csrf @method('PUT')
<x-field name="name" :value="auth()->user()->name" required autocomplete="name" maxlength="150" />
<x-field name="phone" :value="auth()->user()->phone" type="tel" required dir="ltr" maxlength="32" />
<x-field name="current_password" type="password" required autocomplete="current-password" maxlength="255" />
<button class="primary"><x-t k="save" /></button></form></section>
@endif
<section class="panel"><h2><x-t k="change_password" /></h2><form method="POST" action="{{ route('profile.password') }}" class="stack">@csrf @method('PUT')
<x-field name="current_password" id="password-current" type="password" required autocomplete="current-password" maxlength="255" />
<x-field name="password" label="new_password" type="password" required autocomplete="new-password" minlength="8" maxlength="255" hint="password_hint" />
<x-field name="password_confirmation" type="password" required autocomplete="new-password" maxlength="255" />
<button class="primary"><x-t k="change_password" /></button></form></section>
</div>
@endif
</x-layout>
