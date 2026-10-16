@props([
    'paginator',
    'label' => 'records',
    'pageLabel' => 'Page',
])

<div {{ $attributes->merge(['class' => 'print:hidden']) }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="whitespace-nowrap text-sm text-slate-600">
            @if ($paginator->total())
                <span class="font-semibold text-blue-950">Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} {{ $label }}</span>
            @else
                No {{ $label }} to display
            @endif
        </p>

        @if ($paginator->hasPages())
            <div class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2 sm:flex sm:items-center">
                @if ($paginator->onFirstPage())
                    <span aria-label="Previous page" title="Previous page" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-slate-200 text-sm font-semibold text-slate-400">&lt;</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" aria-label="Previous page" title="Previous page" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-blue-200 text-sm font-semibold text-blue-700 hover:bg-blue-50">&lt;</a>
                @endif

                <span class="min-w-0 truncate text-center text-xs font-semibold text-slate-500">
                    <span class="sm:hidden">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
                    <span class="hidden sm:inline">{{ $pageLabel }} {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
                </span>

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" aria-label="Next page" title="Next page" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-blue-200 text-sm font-semibold text-blue-700 hover:bg-blue-50">&gt;</a>
                @else
                    <span aria-label="Next page" title="Next page" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-slate-200 text-sm font-semibold text-slate-400">&gt;</span>
                @endif
            </div>
        @endif
    </div>
</div>
