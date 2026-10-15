<x-layout title="register" :guest="true">
<section class="panel"><h2 class="section-heading"><span class="heading-icon"><x-icon name="graduation" :size="20" /></span><x-t k="register" /></h2>
<form method="POST" action="{{ route('register') }}" class="stack" x-data="{ submitting: false }" @submit="submitting = true" :aria-busy="submitting">@csrf
<x-field name="name" autocomplete="name" maxlength="150" required />
<x-field name="phone" :value="request('phone')" type="tel" autocomplete="tel" hint="phone_hint" required dir="ltr" maxlength="32" />
<x-field name="password" type="password" autocomplete="new-password" minlength="8" maxlength="255" hint="password_hint" required />
<x-field name="password_confirmation" type="password" autocomplete="new-password" required maxlength="255" />
<button class="primary w-full" :disabled="submitting"><x-icon name="arrow" :size="18" /><span x-show="!submitting"><x-t k="register" /></span><span x-cloak x-show="submitting"><x-t k="please_wait" /></span></button>
<a class="block text-sm" href="{{ route('login') }}"><x-t k="back_login" /></a>
</form></section>
</x-layout>
