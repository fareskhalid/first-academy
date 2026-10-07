@if($paginator->hasPages())<nav class="row between">
<button type="button" class="secondary" wire:click="previousPage" @disabled($paginator->onFirstPage())><x-t k="previous" /></button>
<span class="muted">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
<button type="button" class="secondary" wire:click="nextPage" @disabled(!$paginator->hasMorePages())><x-t k="next" /></button>
</nav>@endif
