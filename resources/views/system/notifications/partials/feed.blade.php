@if (session('status'))
    <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100">
        {{ session('status') }}
    </div>
@endif

<section class="overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex flex-col gap-1 border-b border-blue-100 px-4 py-4 dark:border-slate-800 sm:px-5">
        <h3 class="font-semibold text-blue-950 dark:text-blue-100">All Notifications</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $unreadCount }} unread {{ \Illuminate\Support\Str::plural('alert', $unreadCount) }}</p>
    </div>

    <div class="divide-y divide-blue-50 dark:divide-slate-800">
        @forelse ($notifications as $item)
            <article class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                <a href="{{ $item['href'] }}" target="_parent" class="min-w-0 flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">{{ $item['type'] }}</span>
                        @unless ($item['is_read'])
                            <span class="rounded-md bg-red-50 px-2 py-0.5 text-[0.7rem] font-semibold text-red-700 ring-1 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-900">Unread</span>
                        @endunless
                    </div>
                    <p class="mt-1 truncate text-sm font-semibold text-slate-900 dark:text-slate-100 sm:text-base">{{ $item['title'] }}</p>
                    <p class="mt-1 max-h-10 overflow-hidden text-sm leading-5 text-slate-500 dark:text-slate-400">{{ $item['body'] }}</p>
                    <p class="mt-2 text-xs font-medium text-slate-400 dark:text-slate-500">
                        {{ $item['time_label'] }}
                        @if ($item['relative_time'])
                            <span class="text-slate-300 dark:text-slate-600">|</span>
                            {{ $item['relative_time'] }}
                        @endif
                    </p>
                </a>

                <div class="flex shrink-0 items-center justify-between gap-3 sm:flex-col sm:items-end">
                    <span class="max-w-full truncate whitespace-nowrap rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200 dark:bg-blue-950 dark:text-blue-200 dark:ring-blue-800">
                        {{ $item['badge'] }}
                    </span>
                    @if ($item['is_read'])
                        <span class="whitespace-nowrap text-xs font-medium text-slate-400 dark:text-slate-500">Read</span>
                    @else
                        <form method="POST" action="{{ route('notifications.read') }}">
                            @csrf
                            <input type="hidden" name="type" value="{{ $item['read_type'] }}">
                            <input type="hidden" name="id" value="{{ $item['read_id'] }}">
                            <button type="submit" class="whitespace-nowrap text-xs font-semibold text-slate-500 underline underline-offset-2 transition hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:text-slate-400 dark:hover:text-blue-200 dark:focus:ring-offset-slate-900">
                                Mark as read
                            </button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="px-5 py-10 text-center">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">No notifications yet</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">New incident reports and patrol alerts will appear here.</p>
            </div>
        @endforelse
    </div>

    <div class="border-t border-blue-100 px-4 py-4 dark:border-slate-800 sm:px-5">
        {{ $notifications->links() }}
    </div>
</section>
