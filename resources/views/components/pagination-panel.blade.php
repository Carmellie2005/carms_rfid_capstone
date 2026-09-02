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
            <div class="flex items-center gap-2">
                @if ($paginator->onFirstPage())
                    <span class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 px-3 text-xs font-semibold text-slate-400">Previous</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">Previous</a>
                @endif

                <span class="whitespace-nowrap text-xs font-semibold text-slate-500">
                    {{ $pageLabel }} {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
                </span>

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">Next</a>
                @else
                    <span class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 px-3 text-xs font-semibold text-slate-400">Next</span>
                @endif
            </div>
        @endif
    </div>
</div>
