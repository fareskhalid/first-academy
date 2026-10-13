<x-layout title="login" :guest="true">
<section class="panel"><h2 class="section-heading"><span class="heading-icon"><x-icon name="user" :size="20" /></span><x-t k="login" /></h2>
<form method="POST" action="{{ route('login') }}" class="stack" x-data="{ submitting: false }" @submit="submitting = true" :aria-busy="submitting">@csrf
<x-field name="identifier" :value="request('identifier')" autocomplete="username" required autofocus dir="ltr" />
<x-field name="password" type="password" autocomplete="current-password" required />
<button class="primary w-full" :disabled="submitting"><x-icon name="arrow" :size="18" /><span x-show="!submitting"><x-t k="login" /></span><span x-cloak x-show="submitting"><x-t k="please_wait" /></span></button>
<a class="block text-sm" href="{{ route('recovery') }}"><x-t k="recovery" /></a>
</form><hr class="divider"><a class="button secondary w-full" href="{{ route('register') }}"><x-t k="register" /></a></section>
</x-layout>
