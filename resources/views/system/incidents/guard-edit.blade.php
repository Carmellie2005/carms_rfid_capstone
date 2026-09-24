<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">Edit Incident Report</h2>
                <p class="mt-1 text-sm text-blue-600">Update submitted incident details before supervisor review</p>
            </div>
            <a href="{{ route('patrol-logs.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-blue-200 bg-white px-4 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Back to My Patrol Logs
            </a>
        </div>
    </x-slot>

    @php
        $incidentTime = $incident->incident_at?->timezone(config('app.timezone'));
        $reportedTime = $incident->reported_at?->timezone(config('app.timezone'));
        $reportNumber = 'IR-'.str_pad((string) $incident->id, 6, '0', STR_PAD_LEFT);
        $statusLabel = str($incident->status)->replace('_', ' ')->title();
        $incidentImages = $incident->images;
        $removeImageIds = collect(old('remove_image_ids', []))->map(fn ($id) => (string) $id);
    @endphp

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                    Please review the highlighted fields before saving the incident report.
                </div>
            @endif

            <section class="rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                <div class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Report No.</p>
                        <p class="mt-1 font-semibold text-blue-950">{{ $reportNumber }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Status</p>
                        <p class="mt-1">
                            <span class="inline-flex rounded-md bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 ring-1 ring-blue-100">{{ $statusLabel }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Incident Time</p>
                        <p class="mt-1 font-medium text-slate-700">{{ $incidentTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Reported</p>
                        <p class="mt-1 font-medium text-slate-700">{{ $reportedTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Checkpoint</p>
                        <p class="mt-1 font-medium text-slate-700">{{ $incident->checkpoint?->name ?? $incident->location ?? 'Unassigned' }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Checkpoint Code</p>
                        <p class="mt-1 font-mono text-slate-700">{{ $incident->checkpoint?->code ?? $incident->patrolLog?->checkpoint_code ?? 'Not recorded' }}</p>
                    </div>
                </div>
            </section>

            <form method="POST" action="{{ route('guard.incidents.update', $incident) }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PATCH')

                <section class="rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="category" class="block text-sm font-medium text-slate-700">Category</label>
                            <select id="category" name="category" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @foreach ($incidentCategories as $category)
                                    <option value="{{ $category }}" @selected(old('category', $incident->category) === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('category')" class="mt-2" />
                        </div>

                        <div>
                            <label for="priority" class="block text-sm font-medium text-slate-700">Priority</label>
                            <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @foreach ($priorityOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority', $incident->priority ?? 'normal') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                        </div>

                        <div class="sm:col-span-2">
                            <label for="description" class="block text-sm font-medium text-slate-700">Narrative of Incident</label>
                            <textarea id="description" name="description" rows="8" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>{{ old('description', $incident->description) }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-bold text-blue-950">Evidence Photos</h3>
                            <p class="mt-1 text-sm text-slate-500">Keep at least one photo and up to three photos total.</p>
                        </div>
                        <span class="w-fit rounded-md bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 ring-1 ring-blue-100">{{ $incidentImages->count() }} current</span>
                    </div>

                    @if ($incidentImages->isNotEmpty())
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($incidentImages as $image)
                                <label class="block overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm">
                                    <img src="{{ route('incidents.images.show', [$incident, $image]) }}" alt="Incident evidence {{ $loop->iteration }}" class="h-40 w-full object-cover">
                                    <span class="flex items-start gap-2 border-t border-blue-100 px-3 py-2 text-sm text-slate-700">
                                        <input type="checkbox" name="remove_image_ids[]" value="{{ $image->id }}" class="mt-0.5 rounded border-slate-300 text-red-700 focus:ring-red-500" @checked($removeImageIds->contains((string) $image->id))>
                                        <span>
                                            <span class="block font-semibold">Remove this photo</span>
                                            <span class="block truncate text-xs text-slate-500">{{ $image->original_name ?: 'Incident evidence' }}</span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-4 rounded-md border border-dashed border-blue-200 bg-blue-50/50 px-4 py-6 text-center text-sm text-slate-500">
                            No separate evidence image records were found for this incident.
                        </div>
                    @endif

                    <div class="mt-5 rounded-md border border-dashed border-blue-200 bg-blue-50/60 p-3">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label for="incident_images" class="inline-flex h-11 cursor-pointer items-center justify-center rounded-md border border-blue-200 bg-white px-3 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50">
                                Upload Images
                                <input id="incident_images" name="incident_images[]" type="file" accept="image/*" multiple class="sr-only">
                            </label>
                            <label for="incident_camera_images" class="inline-flex h-11 cursor-pointer items-center justify-center rounded-md border border-blue-200 bg-white px-3 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50">
                                Take Photo
                                <input id="incident_camera_images" name="incident_camera_images[]" type="file" accept="image/*" capture="environment" class="sr-only">
                            </label>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">New photos are added to the existing evidence unless selected photos are removed.</p>
                        <x-input-error :messages="$errors->get('incident_images')" class="mt-2" />
                        <x-input-error :messages="$errors->get('incident_images.*')" class="mt-2" />
                        <x-input-error :messages="$errors->get('incident_camera_images')" class="mt-2" />
                        <x-input-error :messages="$errors->get('incident_camera_images.*')" class="mt-2" />
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <a href="{{ route('patrol-logs.index') }}" class="inline-flex h-11 items-center justify-center rounded-md border border-blue-200 bg-white px-5 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-md bg-blue-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
