@php($paginator = $paginator ?? null)
@if ($paginator && $paginator->hasPages())
    <nav class="pager" aria-label="{{ __('music.list.page_of', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}">
        @if ($paginator->onFirstPage())
            <span class="btn btn-secondary disabled" aria-disabled="true">{{ __('music.list.prev') }}</span>
        @else
            <a class="btn btn-secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('music.list.prev') }}</a>
        @endif
        <span class="muted">{{ __('music.list.page_of', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>
        @if ($paginator->hasMorePages())
            <a class="btn btn-secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('music.list.next') }}</a>
        @else
            <span class="btn btn-secondary disabled" aria-disabled="true">{{ __('music.list.next') }}</span>
        @endif
    </nav>
@endif
