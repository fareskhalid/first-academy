<x-layout title="invitation" :guest="!auth()->check()">
<section class="panel invitation-card max-w-xl mx-auto">
<div class="invitation-icon"><x-icon name="book" :size="28" /></div>
<div class="eyebrow"><x-t k="invitation" /></div>
<h1>{{ $invitation->offering->display_title }}</h1>
<p class="invitation-group"><x-t k="group" /> · {{ $invitation->group->display_name }}</p>
@auth
<form method="POST" action="{{ route('invitations.accept',$token) }}" x-data="{ submitting: false }" @submit="submitting = true">@csrf
<button class="primary w-full" :disabled="submitting"><span x-show="!submitting"><x-t k="accept_invitation" /></span><span x-cloak x-show="submitting"><x-t k="please_wait" /></span></button>
</form>
@else
<p class="muted"><x-t k="invitation_account_prompt" /></p>
<form method="POST" action="{{ route('invitations.account',$token) }}" class="stack" x-data="{ submitting: false }" @submit="submitting = true">@csrf
<x-field name="phone" type="tel" autocomplete="tel" hint="phone_hint" required dir="ltr" />
<button class="primary w-full" :disabled="submitting"><span x-show="!submitting"><x-t k="continue" /></span><span x-cloak x-show="submitting"><x-t k="please_wait" /></span></button>
</form>
@endauth
</section>
</x-layout>
