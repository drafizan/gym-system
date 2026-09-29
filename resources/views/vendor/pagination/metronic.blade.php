@if ($paginator->hasPages())
    <nav class="pagination-nav" role="navigation" aria-label="Pagination Navigation">
        <span>
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} entries
        </span>

        <div class="pagination-actions">
            @if ($paginator->onFirstPage())
                <span class="pagination-button disabled" aria-disabled="true">Prev</span>
            @else
                <a class="pagination-button" href="{{ $paginator->previousPageUrl() }}" rel="prev">Prev</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination-button disabled" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination-button active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pagination-button" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pagination-button" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="pagination-button disabled" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@elseif ($paginator->total() > 0)
    <nav class="pagination-nav" role="navigation" aria-label="Pagination Navigation">
        <span>
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} entries
        </span>
    </nav>
@endif
