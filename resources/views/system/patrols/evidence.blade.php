<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-blue-950">My Evidence</h2>
            <p class="mt-1 text-sm text-blue-600">Review your patrol photos and incident evidence in one place</p>
        </div>
    </x-slot>

    <div
        class="py-5 sm:py-8"
        x-data="{
            tab: @js(request()->has('patrol_page') ? 'patrol' : 'incident'),
            photoOpen: false,
            photoUrl: '',
            photoTitle: '',
            openPhoto(url, title) {
                this.photoUrl = url;
                this.photoTitle = title || 'Photo preview';
                this.photoOpen = true;
                document.body.classList.add('overflow-y-hidden');
                this.$nextTick(() => this.$refs.photoCloseButton?.focus());
            },
            closePhoto() {
                this.photoOpen = false;
                this.photoUrl = '';
                this.photoTitle = '';
                document.body.classList.remove('overflow-y-hidden');
            },
        }"
        x-on:keydown.escape.window="photoOpen && closePhoto()"
    >
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            <section class="rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Photo records</p>
                        <h3 class="mt-1 text-lg font-semibold text-blue-950">Check your uploaded proof</h3>
                        <p class="mt-1 text-sm leading-5 text-slate-500">Tap a photo to view it. Use the X button to return to the system on your phone.</p>
                    </div>
                    <a href="{{ route('patrol.scan') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-blue-200 px-4 text-sm font-semibold text-blue-700 transition hover:bg-blue-50">
                        Back to Scan Checkpoint
                    </a>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-2 rounded-md bg-blue-50/70 p-1 sm:max-w-md">
                    <button type="button" class="rounded-md px-3 py-2 text-sm font-semibold transition" :class="tab === 'incident' ? 'bg-white text-blue-800 shadow-sm' : 'text-slate-600 hover:bg-white/70'" @click="tab = 'incident'">
                        Incident Evidence
                    </button>
                    <button type="button" class="rounded-md px-3 py-2 text-sm font-semibold transition" :class="tab === 'patrol' ? 'bg-white text-blue-800 shadow-sm' : 'text-slate-600 hover:bg-white/70'" @click="tab = 'patrol'">
                        Patrol Photos
                    </button>
                </div>
            </section>

            <section x-show="tab === 'incident'" x-cloak class="space-y-5">
                <div class="rounded-md border border-amber-100 bg-amber-50/70 p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-bold text-amber-950">Saved evidence not yet attached</h3>
                            <p class="mt-1 text-sm text-amber-900/70">These photos are ready to attach to your next incident report.</p>
                        </div>
                        <span class="w-fit rounded-md bg-white px-2.5 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-200">{{ $savedEvidence->total() }} saved</span>
                    </div>

                    @if ($savedEvidence->isNotEmpty())
                        <div class="mt-4 grid items-start gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($savedEvidence as $evidence)
                                @php
                                    $evidenceUrl = route('patrol.incident-evidence.image', $evidence);
                                    $evidenceTime = $evidence->captured_at?->timezone(config('app.timezone'));
                                @endphp
                                <article class="overflow-hidden rounded-md border border-amber-200 bg-white shadow-sm">
                                    <button type="button" class="group relative block aspect-[4/3] w-full bg-slate-100" @click="openPhoto(@js($evidenceUrl), @js($evidence->original_name ?: 'Incident evidence'))" aria-label="Open incident evidence photo">
                                        <img src="{{ $evidenceUrl }}" alt="{{ $evidence->original_name ?: 'Incident evidence' }}" class="h-full w-full object-cover transition group-hover:scale-[1.02]">
                                        <span class="absolute bottom-2 left-2 rounded-md bg-slate-950/75 px-2 py-1 text-xs font-semibold text-white">Saved evidence</span>
                                    </button>
                                    <div class="border-t border-amber-100 px-3 py-2 text-xs leading-5 text-slate-600">
                                        <p class="truncate font-semibold text-slate-800">{{ $evidence->original_name ?: 'Incident evidence' }}</p>
                                        <p>Time: {{ $evidenceTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</p>
                                        <p>GPS: {{ number_format((float) $evidence->latitude, 6) }}, {{ number_format((float) $evidence->longitude, 6) }}</p>
                                        <p class="mt-1 font-semibold text-amber-700">Not attached to a report</p>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <x-pagination-panel :paginator="$savedEvidence" label="saved evidence photos" page-label="Evidence page" class="mt-4 border-t border-amber-200 pt-3" />
                    @else
                        <p class="mt-4 rounded-md border border-dashed border-amber-200 bg-white px-4 py-6 text-center text-sm text-slate-500">No unassigned incident evidence photos.</p>
                    @endif
                </div>

                <div class="grid items-start gap-4 md:grid-cols-2">
                    @forelse ($incidentReports as $incident)
                        @php
                            $incidentImages = $incident->images->filter(fn ($image) => $image->image_data || ($image->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($image->image_path)))->values();
                        @endphp
                        <article class="self-start rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-blue-950">{{ $incident->category ?: 'Incident report' }}</h3>
                                    <p class="mt-1 text-sm text-slate-500">{{ $incident->incident_at?->timezone(config('app.timezone'))?->format('M d, Y h:i A') ?? 'Date not recorded' }} · {{ $incident->checkpoint?->name ?? 'Checkpoint not recorded' }}</p>
                                </div>
                                <span class="w-fit rounded-md px-2.5 py-1 text-xs font-bold ring-1 {{ \App\Support\StatusBadge::incidentStatus($incident->status) }}">{{ str($incident->status)->replace('_', ' ')->title() }}</span>
                            </div>

                            @if ($incidentImages->isNotEmpty())
                                <div class="mt-4 grid grid-cols-2 items-start gap-2">
                                    @foreach ($incidentImages as $image)
                                        @php
                                            $imageUrl = route('incidents.images.show', [$incident, $image]);
                                        @endphp
                                        <article class="overflow-hidden rounded-md border border-blue-100 bg-white">
                                            <button type="button" class="group relative block aspect-[4/3] w-full bg-slate-100" @click="openPhoto(@js($imageUrl), @js($image->original_name ?: 'Incident photo'))" aria-label="Open incident photo">
                                                <img src="{{ $imageUrl }}" alt="Incident photo {{ $loop->iteration }}" class="h-full w-full object-cover transition group-hover:scale-[1.02]">
                                                <span class="absolute bottom-2 left-2 rounded-md bg-slate-950/75 px-2 py-1 text-xs font-semibold text-white">{{ $image->incidentEvidence ? 'Saved evidence' : 'Incident photo' }}</span>
                                            </button>
                                            @if ($image->incidentEvidence)
                                                <div class="border-t border-blue-100 px-3 py-2 text-xs leading-5 text-slate-600">
                                                    <p>Captured: {{ $image->incidentEvidence->captured_at?->timezone(config('app.timezone'))?->format('M d, Y h:i A') ?? 'Not recorded' }}</p>
                                                    <p>GPS: {{ number_format((float) $image->incidentEvidence->latitude, 6) }}, {{ number_format((float) $image->incidentEvidence->longitude, 6) }}</p>
                                                </div>
                                            @else
                                                <p class="border-t border-blue-100 px-3 py-2 text-xs font-semibold text-slate-500">Captured during incident report</p>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-4 rounded-md border border-dashed border-blue-200 bg-blue-50/40 px-4 py-5 text-center text-sm text-slate-500">No incident photos available for this report.</p>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-md border border-dashed border-blue-200 bg-white px-4 py-8 text-center text-sm text-slate-500 shadow-sm">No submitted incident reports yet.</div>
                    @endforelse
                </div>
                <x-pagination-panel :paginator="$incidentReports" label="incident reports" page-label="Incident page" class="rounded-md border border-blue-100 bg-white px-4 py-3 shadow-sm" />
            </section>

            <section x-show="tab === 'patrol'" x-cloak class="space-y-4">
                <div class="grid items-start gap-4 md:grid-cols-2">
                @forelse ($patrolLogs as $log)
                    @php
                        $areaSelfieUrl = (filled($log->area_selfie_path) || filled($log->area_selfie_image_data)) ? route('patrol-logs.area-selfie.show', $log) : null;
                        $proofPhotos = $log->checklistResponse?->proofPhotos ?? collect();
                    @endphp
                    <article class="self-start rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-base font-bold text-blue-950">{{ $log->checkpoint?->name ?? $log->checkpoint_code ?? 'Patrol checkpoint' }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $log->scanned_at?->timezone(config('app.timezone'))?->format('M d, Y h:i A') ?? 'Date not recorded' }}</p>
                            </div>
                            <span class="w-fit rounded-md px-2.5 py-1 text-xs font-bold ring-1 {{ \App\Support\StatusBadge::patrol($log->status) }}">{{ str($log->status)->replace('_', ' ')->title() }}</span>
                        </div>

                        @if ($areaSelfieUrl || $proofPhotos->isNotEmpty())
                            <div class="mt-4 grid grid-cols-2 items-start gap-2">
                                @if ($areaSelfieUrl)
                                    <button type="button" class="group relative aspect-[4/3] overflow-hidden rounded-md border border-blue-100 bg-slate-100" @click="openPhoto(@js($areaSelfieUrl), 'Area selfie')" aria-label="Open area selfie">
                                        <img src="{{ $areaSelfieUrl }}" alt="Area selfie" class="h-full w-full object-cover transition group-hover:scale-[1.02]">
                                        <span class="absolute inset-x-2 bottom-2 truncate rounded-md bg-slate-950/75 px-2 py-1 text-left text-xs font-semibold text-white">Area selfie</span>
                                    </button>
                                @endif
                                @foreach ($proofPhotos as $photo)
                                    @php $photoUrl = route('patrol-logs.proof-photos.show', [$log, $photo]); @endphp
                                    <button type="button" class="group relative aspect-[4/3] overflow-hidden rounded-md border border-blue-100 bg-slate-100" @click="openPhoto(@js($photoUrl), @js($photo->item_label))" aria-label="Open {{ $photo->item_label }} photo">
                                        <img src="{{ $photoUrl }}" alt="{{ $photo->item_label }}" class="h-full w-full object-cover transition group-hover:scale-[1.02]">
                                        <span class="absolute inset-x-2 bottom-2 truncate rounded-md bg-slate-950/75 px-2 py-1 text-left text-xs font-semibold text-white">{{ $photo->item_label }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-4 rounded-md border border-dashed border-blue-200 bg-blue-50/40 px-4 py-5 text-center text-sm text-slate-500">No patrol photos uploaded for this record.</p>
                        @endif
                    </article>
                @empty
                    <div class="rounded-md border border-dashed border-blue-200 bg-white px-4 py-8 text-center text-sm text-slate-500 shadow-sm">No patrol photo records yet.</div>
                @endforelse
                </div>
                <x-pagination-panel :paginator="$patrolLogs" label="patrol records" page-label="Patrol page" class="rounded-md border border-blue-100 bg-white px-4 py-3 shadow-sm" />
            </section>
        </div>

        <template x-teleport="body">
            <div x-show="photoOpen" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/75 p-3 sm:p-6" @click.self="closePhoto()" role="dialog" aria-modal="true" aria-label="Photo preview">
                <section class="flex max-h-[92dvh] w-full max-w-3xl flex-col overflow-hidden rounded-md bg-white shadow-2xl">
                    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-blue-100 px-4 py-3">
                        <p class="min-w-0 truncate text-sm font-semibold text-blue-950" x-text="photoTitle"></p>
                        <button type="button" x-ref="photoCloseButton" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-blue-100 text-slate-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500" @click="closePhoto()" aria-label="Close photo preview and return to system">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                        </button>
                    </div>
                    <div class="min-h-0 overflow-auto bg-slate-950 p-2 sm:p-4">
                        <img x-show="photoUrl" :src="photoUrl" :alt="photoTitle" class="mx-auto max-h-[78dvh] w-full object-contain">
                    </div>
                </section>
            </div>
        </template>
    </div>
</x-app-layout>
