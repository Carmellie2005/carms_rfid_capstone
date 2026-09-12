<x-app-layout>
    @php
        $incidentDefault = (bool) old('has_incident');
        $checkpointChecklistItems = \App\Support\PatrolChecklist::items();
        $checklistStatusOptions = \App\Support\PatrolChecklist::statusOptions();
        $incidentCategories = \App\Support\PatrolChecklist::incidentCategories();
        $guardName = $guardProfile?->name ?? Auth::user()->name;
        $guardEmployeeNo = $guardProfile?->employee_no ?? 'Account only';
        $oldAreaSelfieCapture = old('area_selfie_capture', '');
        $pendingSelfieCaptured = filled($oldAreaSelfieCapture)
            || ($pendingPatrol && (filled($pendingPatrol->area_selfie_path) || filled($pendingPatrol->area_selfie_image_data)));
        $incidentFormHasErrors = $errors->has('incident_category')
            || $errors->has('incident_priority')
            || $errors->has('incident_description')
            || $errors->has('incident_image')
            || $errors->has('incident_images')
            || $errors->has('incident_images.*')
            || $errors->has('incident_camera_images')
            || $errors->has('incident_camera_images.*');
        $openIncident = $incidentDefault && old('patrol_log_id') && $incidentFormHasErrors;
        $openChecklist = ((($errors->any() && old('patrol_log_id') && $pendingSelfieCaptured) || $pendingSelfieCaptured) && ! $openIncident);
        $patrolScheduleOpen = (bool) ($patrolScheduleOpen ?? true);
        $patrolScheduleTestingMode = (bool) ($patrolScheduleTestingMode ?? false);
        $patrolScheduleMessage = $patrolScheduleMessage ?? 'Guard patrol scanning is only available during the assigned patrol schedule.';
        $patrolTestingNotice = $patrolTestingNotice ?? 'Testing mode is active, so patrol scanning is open anytime for demo/testing.';
        $scanWaitingMessage = $patrolScheduleTestingMode
            ? 'Testing mode is active. Waiting for your ESP32 checkpoint scan anytime.'
            : 'Waiting for your ESP32 checkpoint scan.';
        $pendingScan = $pendingPatrol ? [
            'id' => $pendingPatrol->id,
            'rfid_uid' => $pendingPatrol->rfid_uid,
            'checkpoint_code' => $pendingPatrol->checkpoint_code,
            'status' => $pendingPatrol->status,
            'facial_status' => $pendingPatrol->facial_status,
            'area_selfie_captured' => $pendingSelfieCaptured,
            'area_selfie_captured_at' => $pendingPatrol->area_selfie_captured_at?->timezone('Asia/Manila')->format('M d, Y h:i A'),
            'area_selfie_latitude' => $pendingPatrol->area_selfie_latitude,
            'area_selfie_longitude' => $pendingPatrol->area_selfie_longitude,
            'area_selfie_accuracy' => $pendingPatrol->area_selfie_accuracy,
            'scanned_at' => $pendingPatrol->scanned_at?->timezone('Asia/Manila')->format('M d, Y h:i A'),
            'guard' => [
                'name' => $pendingPatrol->securityGuard?->name,
                'employee_no' => $pendingPatrol->securityGuard?->employee_no,
            ],
            'checkpoint' => [
                'name' => $pendingPatrol->checkpoint?->name ?? $pendingPatrol->checkpoint_code,
                'code' => $pendingPatrol->checkpoint?->code ?? $pendingPatrol->checkpoint_code,
                'location' => $pendingPatrol->checkpoint?->location,
                'device_uid' => $pendingPatrol->checkpoint?->device_uid,
            ],
        ] : null;
    @endphp

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="flex items-center gap-3 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('warning'))
                <div class="flex items-center gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 9v4m0 4h.01M10.3 4.3 2.8 17.2A2 2 0 0 0 4.5 20h15a2 2 0 0 0 1.7-2.8L13.7 4.3a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span>{{ session('warning') }}</span>
                </div>
            @endif

            @if (! $guardProfile)
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                    This account is not linked to a guard profile. Ask the supervisor to link this user to a guard record.
                </div>
            @endif

            @if ($patrolScheduleTestingMode)
                <div class="flex items-center gap-3 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800 dark:border-blue-400/30 dark:bg-blue-950/50 dark:text-blue-100">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-blue-700 ring-1 ring-blue-100 dark:bg-blue-900 dark:text-blue-100 dark:ring-blue-400/30">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 8h.01M11 12h1v5h1M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span>{{ $patrolTestingNotice }}</span>
                </div>
            @elseif (! $patrolScheduleOpen)
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                    Patrol scanning is open only from {{ $patrolScheduleLabel }}. Next patrol window starts {{ $patrolScheduleNextOpen }}.
                </div>
            @endif

            @if ($guardProfile)
            <form
                method="POST"
                action="{{ route('patrol.store') }}"
                enctype="multipart/form-data"
                data-skip-global-loader="true"
                class="space-y-5"
                x-data="patrolScan({
                    incident: @js((bool) $incidentDefault),
                    pendingScan: @js($pendingScan),
                    pendingScanUrl: @js(route('patrol.pending-scan', [], false)),
                    csrfRefreshUrl: @js(route('csrf.refresh', [], false)),
                    guardName: @js($guardName),
                    guardEmployeeNo: @js($guardEmployeeNo),
                    patrolLogId: @js(old('patrol_log_id', $pendingPatrol?->id)),
                    scanMessage: @js($pendingScan ? ($pendingSelfieCaptured ? 'Area selfie captured. Complete the checklist.' : 'RFID accepted. Take the required area selfie.') : ($patrolScheduleOpen ? $scanWaitingMessage : $patrolScheduleMessage)),
                    patrolScheduleOpen: @js($patrolScheduleOpen),
                    patrolScheduleTestingMode: @js($patrolScheduleTestingMode),
                    patrolScheduleMessage: @js($patrolScheduleMessage),
                    patrolTestingNotice: @js($patrolTestingNotice),
                    openChecklist: @js((bool) $openChecklist),
                    openIncident: @js((bool) $openIncident),
                    areaSelfieCapture: @js($oldAreaSelfieCapture),
                    areaSelfieCapturedAt: @js(old('area_selfie_captured_at', '')),
                    areaSelfieLatitude: @js(old('area_selfie_latitude', '')),
                    areaSelfieLongitude: @js(old('area_selfie_longitude', '')),
                    areaSelfieAccuracy: @js(old('area_selfie_accuracy', '')),
                    checklistItems: @js(collect($checkpointChecklistItems)->map(fn ($label, $field) => ['field' => $field, 'label' => $label])->values()),
                })"
                x-init="boot()"
                x-on:submit="handleSubmit($event)"
                x-on:beforeunload.window="stopCamera(); if (pollingTimer) clearInterval(pollingTimer)"
            >
                @csrf

                <input type="hidden" name="patrol_log_id" :value="patrolLogId">
                <input type="hidden" name="area_selfie_capture" :value="areaSelfieCapture">
                <input type="hidden" name="area_selfie_captured_at" :value="areaSelfieCapturedAt">
                <input type="hidden" name="area_selfie_latitude" :value="areaSelfieLatitude">
                <input type="hidden" name="area_selfie_longitude" :value="areaSelfieLongitude">
                <input type="hidden" name="area_selfie_accuracy" :value="areaSelfieAccuracy">

                <section class="rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-4">
                    <div class="space-y-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-[0.68rem] font-semibold uppercase tracking-wide text-blue-700">Device Flow</p>
                                <h3 class="mt-0.5 text-base font-semibold text-blue-950">Checkpoint Scan Progress</h3>
                            </div>
                            <span class="inline-flex w-fit items-center gap-2 rounded-md px-2.5 py-1 text-[0.68rem] font-bold uppercase tracking-wide ring-1"
                                :class="areaSelfieComplete() ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : (pendingScan ? 'bg-blue-50 text-blue-700 ring-blue-200' : 'bg-slate-50 text-slate-600 ring-slate-200')"
                                x-text="areaSelfieComplete() ? 'Step 3 of 3' : (pendingScan ? 'Step 2 of 3' : 'Step 1 of 3')">
                            </span>
                        </div>

                        <div class="rounded-md border border-blue-100 bg-blue-50/60 px-3 py-3 sm:px-4">
                            <div class="grid grid-cols-[2rem_minmax(1.5rem,1fr)_2rem_minmax(1.5rem,1fr)_2rem] items-center sm:grid-cols-[2.25rem_minmax(2rem,1fr)_2.25rem_minmax(2rem,1fr)_2.25rem]">
                                <div class="flex justify-center">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold shadow-sm transition sm:h-9 sm:w-9 sm:text-sm"
                                        :class="pendingScan ? 'bg-emerald-600 text-white' : 'bg-blue-700 text-white ring-4 ring-blue-100'">
                                        <svg x-show="pendingScan" x-cloak class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <span x-show="! pendingScan">1</span>
                                    </span>
                                </div>
                                <span class="h-1 rounded-full transition" :class="pendingScan ? 'bg-emerald-500' : 'bg-blue-200'"></span>
                                <div class="flex justify-center">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold shadow-sm transition sm:h-9 sm:w-9 sm:text-sm"
                                        :class="areaSelfieComplete() ? 'bg-emerald-600 text-white' : (pendingScan ? 'bg-blue-700 text-white ring-4 ring-blue-100' : 'bg-white text-slate-400 ring-1 ring-slate-200')">
                                        <svg x-show="areaSelfieComplete()" x-cloak class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <span x-show="! areaSelfieComplete()">2</span>
                                    </span>
                                </div>
                                <span class="h-1 rounded-full transition" :class="areaSelfieComplete() ? 'bg-emerald-500' : (pendingScan ? 'bg-blue-200' : 'bg-slate-200')"></span>
                                <div class="flex justify-center">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold shadow-sm transition sm:h-9 sm:w-9 sm:text-sm"
                                        :class="areaSelfieComplete() ? 'bg-blue-700 text-white ring-4 ring-blue-100' : 'bg-white text-slate-400 ring-1 ring-slate-200'">3</span>
                                </div>
                            </div>

                            <div class="mt-2 grid grid-cols-3 gap-1 text-center text-[0.62rem] font-bold uppercase tracking-wide sm:text-[0.68rem]">
                                <span class="whitespace-nowrap" :class="pendingScan ? 'text-emerald-700' : 'text-blue-800'">RFID Scan</span>
                                <span class="whitespace-nowrap" :class="areaSelfieComplete() ? 'text-emerald-700' : (pendingScan ? 'text-blue-800' : 'text-slate-400')">Area Selfie</span>
                                <span class="whitespace-nowrap" :class="areaSelfieComplete() ? 'text-blue-800' : 'text-slate-400'">Checklist</span>
                            </div>
                        </div>

                        <div class="rounded-md border border-blue-100 bg-blue-50/40 p-2.5 sm:p-3">
                            <div x-show="! pendingScan" x-transition.opacity.duration.200ms class="flex min-h-[13rem] flex-col items-center justify-center rounded-md border border-dashed border-blue-200 bg-white p-4 text-center sm:min-h-[15rem]">
                                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-blue-50 text-blue-700">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M7 7.5h10v9H7v-9ZM9.5 4.5h5M9.5 19.5h5M4 10v4M20 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                                <p class="mt-3 text-[0.68rem] font-semibold uppercase tracking-wide text-blue-700">Step 1</p>
                                <h3 class="mt-0.5 text-base font-semibold text-blue-950">Scan RFID Card</h3>
                                <p class="mt-1.5 max-w-md text-sm leading-5 text-slate-500" x-text="scanMessage"></p>
                                <div x-show="patrolScheduleOpen" class="mt-4 inline-flex items-center gap-2 rounded-md bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800 ring-1 ring-blue-100">
                                    <span class="h-2 w-2 animate-pulse rounded-full bg-blue-700"></span>
                                    <span x-text="patrolScheduleTestingMode ? 'Testing mode: open anytime' : 'Listening for ESP32 scan'"></span>
                                </div>
                                <div x-show="! patrolScheduleOpen" x-cloak class="mt-4 inline-flex items-center gap-2 rounded-md bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">
                                    Scheduled patrol only
                                </div>
                            </div>

                            <div x-show="pendingScan && ! areaSelfieComplete()" x-cloak x-transition.opacity.duration.200ms class="mx-auto max-w-2xl space-y-3">
                                <div class="rounded-md border border-emerald-100 bg-emerald-50 px-4 py-3">
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-emerald-700 ring-1 ring-emerald-100">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-emerald-800">RFID scan accepted</p>
                                            <p class="mt-0.5 truncate text-xs text-emerald-700" x-text="pendingScan?.checkpoint?.name || 'Checkpoint'"></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-md border border-blue-100 bg-white p-4 text-center shadow-sm sm:p-5">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-700 ring-1 ring-blue-100">
                                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M4 8h3l1.5-2h7L17 8h3v11H4V8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                            <path d="M12 16a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" />
                                        </svg>
                                    </div>
                                    <p class="mt-4 text-[0.68rem] font-semibold uppercase tracking-wide text-blue-700">Step 2</p>
                                    <h3 class="mt-1 text-lg font-semibold text-blue-950">Area Selfie</h3>
                                    <p class="mx-auto mt-1 max-w-sm text-sm leading-5 text-slate-500">Take a photo at the checkpoint area. Allow location when asked so GPS can be stamped on the saved photo.</p>
                                    <button type="button" class="mt-5 inline-flex h-12 w-full items-center justify-center gap-2 rounded-md bg-blue-700 px-5 text-base font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-blue-300 sm:w-auto sm:min-w-56" @click="openAreaSelfieCamera()" :disabled="cameraOpening || submittingPatrol">
                                        <svg x-show="! cameraOpening" class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M4 8h3l1.5-2h7L17 8h3v11H4V8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                            <path d="M12 16a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" />
                                        </svg>
                                        <svg x-show="cameraOpening" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                                        </svg>
                                        <span x-text="cameraOpening ? 'Opening...' : 'Take Photo'"></span>
                                    </button>
                                    <p x-show="areaSelfieMessage && ! areaSelfieCameraOpen" x-cloak class="mt-3 text-sm font-semibold text-blue-800" x-text="areaSelfieMessage"></p>
                                    <p x-show="cameraError || areaSelfieError" x-cloak class="mt-3 text-sm font-semibold text-red-700" x-text="cameraError || areaSelfieError"></p>
                                    <x-input-error :messages="$errors->get('area_selfie_capture')" class="mt-2" />
                                    <x-input-error :messages="$errors->get('area_selfie_latitude')" class="mt-2" />
                                    <x-input-error :messages="$errors->get('area_selfie_longitude')" class="mt-2" />
                                </div>

                                <dl class="grid gap-2 sm:grid-cols-3">
                                    <div class="rounded-md border border-blue-100 bg-white p-3">
                                        <dt class="text-[0.68rem] font-semibold uppercase text-blue-800">Guard</dt>
                                        <dd class="mt-1 truncate text-sm font-semibold text-slate-900" x-text="pendingScan?.guard?.name || guardName"></dd>
                                        <dd class="text-xs text-slate-500" x-text="pendingScan?.guard?.employee_no || guardEmployeeNo"></dd>
                                    </div>
                                    <div class="rounded-md border border-blue-100 bg-white p-3">
                                        <dt class="text-[0.68rem] font-semibold uppercase text-blue-800">Location</dt>
                                        <dd class="mt-1 truncate text-sm font-semibold text-slate-900" x-text="areaSelfieLocationLabel()"></dd>
                                        <dd class="text-xs text-slate-500" x-text="pendingScan?.checkpoint?.code || pendingScan?.checkpoint_code || ''"></dd>
                                    </div>
                                    <div class="rounded-md border border-blue-100 bg-white p-3">
                                        <dt class="text-[0.68rem] font-semibold uppercase text-blue-800">RFID UID</dt>
                                        <dd class="mt-1 truncate font-mono text-sm text-slate-900" x-text="pendingScan?.rfid_uid || ''"></dd>
                                    </div>
                                </dl>

                                <x-input-error :messages="$errors->get('patrol_log_id')" class="mt-2" />
                            </div>

                            <div x-show="pendingScan && areaSelfieComplete()" x-cloak x-transition.opacity.duration.200ms class="mx-auto max-w-3xl space-y-3">
                                <div class="rounded-md border border-emerald-100 bg-emerald-50 px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-emerald-700 ring-1 ring-emerald-100">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-emerald-800">Steps 1 and 2 complete</p>
                                            <p class="mt-0.5 text-xs text-emerald-700">RFID scan and stamped area selfie are ready.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-4">
                                    <div class="flex min-h-[16rem] max-h-[65dvh] items-center justify-center overflow-hidden rounded-md bg-slate-950">
                                        <img :src="areaSelfieCapture" alt="Captured area selfie proof" class="max-h-[65dvh] w-full object-contain" style="transform: none;">
                                    </div>
                                    <dl class="mt-3 grid gap-2 sm:grid-cols-3">
                                        <div class="rounded-md border border-blue-100 bg-blue-50/40 p-3">
                                            <dt class="text-[0.68rem] font-semibold uppercase text-blue-800">Guard</dt>
                                            <dd class="mt-1 truncate text-sm font-semibold text-slate-900" x-text="pendingScan?.guard?.name || guardName"></dd>
                                            <dd class="text-xs text-slate-500" x-text="pendingScan?.guard?.employee_no || guardEmployeeNo"></dd>
                                        </div>
                                        <div class="rounded-md border border-blue-100 bg-blue-50/40 p-3">
                                            <dt class="text-[0.68rem] font-semibold uppercase text-blue-800">Location</dt>
                                            <dd class="mt-1 truncate text-sm font-semibold text-slate-900" x-text="areaSelfieLocationLabel()"></dd>
                                            <dd class="text-xs text-slate-500" x-text="areaSelfieGpsLabel()"></dd>
                                        </div>
                                        <div class="rounded-md border border-blue-100 bg-blue-50/40 p-3">
                                            <dt class="text-[0.68rem] font-semibold uppercase text-blue-800">Captured</dt>
                                            <dd class="mt-1 text-sm font-semibold text-slate-900" x-text="areaSelfieCapturedLabel()"></dd>
                                        </div>
                                    </dl>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                        <button type="button" class="inline-flex h-11 items-center justify-center rounded-md border border-blue-200 bg-white px-4 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60" @click="clearAreaSelfie()" :disabled="submittingPatrol">
                                            Retake Photo
                                        </button>
                                        <button type="button" class="inline-flex h-11 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-blue-300" @click="continueToChecklist()" :disabled="submittingPatrol">
                                            Continue to Checklist
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div x-show="areaSelfieCameraOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[90] flex bg-slate-950 text-white" x-on:keydown.escape.window="areaSelfieCameraOpen && closeAreaSelfieCamera()">
                    <section class="flex h-[100svh] max-h-[100dvh] w-full flex-col overflow-hidden">
                        <div class="flex h-14 shrink-0 items-center justify-between gap-3 bg-slate-950/95 px-4">
                            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-white transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60 disabled:cursor-not-allowed disabled:opacity-50" @click="closeAreaSelfieCamera()" :disabled="cameraOpening || areaSelfieLocationBusy" aria-label="Close camera">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                </svg>
                            </button>
                            <div class="min-w-0 text-center">
                                <p class="truncate text-sm font-semibold">Take Photo</p>
                                <p class="truncate text-[0.68rem] text-white/65" x-text="areaSelfieLocationLabel()"></p>
                            </div>
                            <span class="h-10 w-10" aria-hidden="true"></span>
                        </div>

                        <div class="relative flex min-h-0 flex-1 items-center justify-center bg-black">
                            <video x-ref="areaSelfieVideo" x-show="cameraOpen" x-cloak class="camera-unmirrored max-h-full max-w-full object-contain" autoplay playsinline muted></video>
                            <div x-show="! cameraOpen" class="absolute inset-0 flex flex-col items-center justify-center px-6 text-center">
                                <svg x-show="cameraOpening" class="h-10 w-10 animate-spin text-white/80" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                                </svg>
                                <p class="mt-3 text-sm font-semibold text-white/80" x-text="cameraOpening ? 'Opening camera...' : 'Camera is not open'"></p>
                            </div>
                            <div x-show="cameraOpen && ! areaSelfieLocationBusy" x-cloak class="absolute inset-x-3 bottom-3 rounded-md bg-slate-950/75 px-3 py-2 text-xs font-semibold leading-5 text-white shadow-lg">
                                <p x-text="areaSelfieStampPreview()"></p>
                            </div>
                        </div>

                        <div class="shrink-0 space-y-3 bg-slate-950 px-4 pb-[calc(1rem+env(safe-area-inset-bottom))] pt-4">
                            <p x-show="areaSelfieMessage" x-cloak class="text-center text-xs font-semibold text-white/75" x-text="areaSelfieMessage"></p>
                            <p x-show="cameraError || areaSelfieError" x-cloak class="rounded-md bg-red-500/15 px-3 py-2 text-center text-xs font-semibold text-red-100 ring-1 ring-red-400/30" x-text="cameraError || areaSelfieError"></p>
                            <div class="flex items-center justify-center">
                                <button type="button" class="inline-flex h-16 w-16 items-center justify-center rounded-full border-4 border-white bg-white shadow-lg transition hover:bg-blue-50 focus:outline-none focus:ring-4 focus:ring-blue-300 disabled:cursor-not-allowed disabled:opacity-60" @click="captureAreaSelfie()" :disabled="! cameraOpen || areaSelfieLocationBusy || submittingPatrol" aria-label="Capture area selfie">
                                    <span class="h-11 w-11 rounded-full bg-white ring-2 ring-slate-950/80" x-show="! areaSelfieLocationBusy"></span>
                                    <svg x-show="areaSelfieLocationBusy" x-cloak class="h-7 w-7 animate-spin text-blue-700" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                                    </svg>
                                </button>
                            </div>
                            <p class="text-center text-[0.68rem] font-semibold uppercase tracking-wide text-white/50" x-text="areaSelfieLocationBusy ? 'Getting GPS' : 'Photo'"></p>
                        </div>

                        <canvas x-ref="areaSelfieCanvas" class="hidden"></canvas>
                    </section>
                </div>

                <div x-show="checklistModalOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[80] flex items-stretch justify-center overflow-hidden bg-slate-950/55 p-0 sm:items-center sm:px-4 sm:py-6">
                    <section class="flex h-[100svh] max-h-[100dvh] w-full flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:min-h-0 sm:max-h-[92vh] sm:max-w-5xl sm:rounded-lg">
                        <div class="flex items-start justify-between gap-4 border-b border-blue-100 px-4 py-4 sm:px-5">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Step 3</p>
                                <h3 class="text-lg font-semibold text-blue-950">Checklist and Incident Report</h3>
                                <p class="mt-1 text-sm text-slate-500">Complete the patrol checklist before submitting this checkpoint visit.</p>
                            </div>
                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-blue-100 bg-white text-slate-700 hover:bg-blue-50" @click="checklistModalOpen = false" aria-label="Close checklist modal">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                </svg>
                            </button>
                        </div>

                        <div class="mobile-scroll-area grid flex-1 gap-5 overflow-y-auto p-4 sm:p-5 lg:grid-cols-[1fr_0.95fr]">
                            <div>
                                <h4 class="text-base font-semibold text-blue-950">Checkpoint Checklist</h4>
                                <p x-show="checklistPhotoError" x-cloak x-text="checklistPhotoError" class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700"></p>
                                <x-input-error :messages="$errors->get('checklist_photos')" class="mt-3" />
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    @foreach ($checkpointChecklistItems as $field => $label)
                                        @php
                                            $selectedChecklistStatus = old("checklist_statuses.{$field}", \App\Support\PatrolChecklist::STATUS_NORMAL);
                                        @endphp
                                        <div class="min-h-36 rounded-md border border-blue-100 bg-white p-3 text-sm text-slate-700 shadow-sm transition hover:bg-blue-50/70">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0 flex-1">
                                                    <p class="font-semibold text-slate-800">{{ $label }}</p>
                                                    <div class="mt-3 grid grid-cols-3 gap-1.5">
                                                        @foreach ($checklistStatusOptions as $statusValue => $statusLabel)
                                                            <label for="{{ $field }}_{{ $statusValue }}" class="cursor-pointer">
                                                                <input id="{{ $field }}_{{ $statusValue }}" type="radio" name="checklist_statuses[{{ $field }}]" value="{{ $statusValue }}" class="peer sr-only" required @checked($selectedChecklistStatus === $statusValue) @change="checklistPhotoError = ''">
                                                                <span class="flex min-h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-1.5 text-center text-[0.65rem] font-semibold leading-tight text-slate-600 transition peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-800 peer-focus:ring-2 peer-focus:ring-blue-500 peer-focus:ring-offset-1 sm:text-xs">
                                                                    {{ $statusLabel }}
                                                                </span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>

                                                <button type="button" class="relative inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border shadow-sm transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :class="checklistPhotoPreviews['{{ $field }}'] ? 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-blue-200 bg-white text-blue-700 hover:bg-blue-50'" @click="takeChecklistPhoto('{{ $field }}')" :disabled="submittingPatrol" :aria-label="(checklistPhotoPreviews['{{ $field }}'] ? 'Retake proof photo for ' : 'Take proof photo for ') + @js($label)" :title="(checklistPhotoPreviews['{{ $field }}'] ? 'Retake proof photo for ' : 'Take proof photo for ') + @js($label)">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="M4 8h3l1.5-2h7L17 8h3v11H4V8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                                        <path d="M12 16a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" />
                                                    </svg>
                                                    <span x-show="checklistPhotoPreviews['{{ $field }}']" x-cloak class="absolute -right-1 -top-1 inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-600 text-white">
                                                        <svg class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="m5 12 4 4 10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                                        </svg>
                                                    </span>
                                                </button>
                                            </div>
                                            <input x-ref="checklistPhoto_{{ $field }}" id="checklist_photo_{{ $field }}" name="checklist_photos[{{ $field }}]" type="file" accept="image/*" capture="environment" class="sr-only" @change="updateChecklistPhoto('{{ $field }}', $event)">

                                            <div x-show="checklistPhotoPreviews['{{ $field }}']" x-cloak class="mt-3 flex items-center gap-2">
                                                <button type="button" x-show="checklistPhotoPreviews['{{ $field }}']" x-cloak class="h-12 w-12 shrink-0 overflow-hidden rounded-md border border-blue-100 bg-slate-100 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="openChecklistPhotoPreview('{{ $field }}')" aria-label="Preview {{ $label }} proof photo">
                                                    <img :src="checklistPhotoPreviews['{{ $field }}']?.url" alt="{{ $label }} proof photo thumbnail" class="h-full w-full object-cover">
                                                </button>
                                                <button type="button" x-show="checklistPhotoPreviews['{{ $field }}']" x-cloak class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-red-200 bg-white text-red-700 shadow-sm transition hover:bg-red-50" @click="removeChecklistPhoto('{{ $field }}')" aria-label="Remove {{ $label }} proof photo">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                                    </svg>
                                                </button>
                                            </div>

                                            <x-input-error :messages="$errors->get('checklist_photos.'.$field)" class="mt-2" />
                                            <x-input-error :messages="$errors->get('checklist_statuses.'.$field)" class="mt-2" />
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-4">
                                    <label for="remarks" class="block text-sm font-medium text-slate-700">Remarks</label>
                                    <textarea id="remarks" name="remarks" rows="4" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('remarks') }}</textarea>
                                    <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
                                </div>
                            </div>

                            <div class="rounded-lg border border-blue-100 bg-blue-50/60 p-3 sm:p-4">
                                <div class="flex flex-col gap-4 border-b border-blue-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h4 class="text-base font-semibold text-blue-950">Incident Report</h4>
                                        <p class="mt-1 text-sm text-slate-500">Open a separate form only when something happened.</p>
                                    </div>
                                    <label class="inline-flex w-fit items-center gap-2 rounded-full border border-blue-100 bg-white px-3 py-2 text-sm font-semibold text-blue-800">
                                        <input type="checkbox" name="has_incident" value="1" x-model="incident" class="rounded border-slate-300 text-blue-700 focus:ring-blue-500" @checked($incidentDefault) @change="handleIncidentToggle($event)">
                                        Incident observed
                                    </label>
                                </div>

                                <div class="mt-4 rounded-md border border-blue-100 bg-white p-3">
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="incident ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'">
                                            <svg x-show="! incident" class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <svg x-show="incident" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 9v4m0 4h.01M10.3 4.2 2.7 17.4A2 2 0 0 0 4.4 20h15.2a2 2 0 0 0 1.7-2.6L13.7 4.2a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold" :class="incident ? 'text-amber-800' : 'text-emerald-800'" x-text="incident ? 'Incident report attached' : 'No incident observed'"></p>
                                            <p class="mt-1 text-sm text-slate-500" x-text="incidentSummary()"></p>
                                            <p x-show="incidentFormError" x-cloak class="mt-2 text-xs font-semibold text-red-700" x-text="incidentFormError"></p>
                                            <p x-show="incidentImageError" x-cloak class="mt-2 text-xs font-semibold text-red-700" x-text="incidentImageError"></p>
                                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                                <button type="button" class="inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-blue-300" @click="openIncidentModal()" :disabled="submittingPatrol">
                                                    <span x-text="incident ? 'Edit Incident Report' : 'Add Incident Report'"></span>
                                                </button>
                                                <button type="button" x-show="incident" x-cloak class="inline-flex h-10 items-center justify-center rounded-md border border-red-200 bg-white px-4 text-sm font-semibold text-red-700 shadow-sm transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60" @click="clearIncidentReport()" :disabled="submittingPatrol">
                                                    Remove Incident
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-blue-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <p class="text-sm text-slate-500">
                                Submitting will complete the ESP32 RFID scan, stamped area selfie, checklist, and incident report if provided.
                            </p>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <button type="button" class="inline-flex h-11 items-center justify-center rounded-md border border-blue-200 bg-white px-5 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60" @click="checklistModalOpen = false" :disabled="submittingPatrol">
                                    Review Scan
                                </button>
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-md bg-blue-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" :disabled="! patrolLogId || ! areaSelfieComplete() || submittingPatrol" :class="(! patrolLogId || ! areaSelfieComplete() || submittingPatrol) ? 'cursor-not-allowed opacity-60' : ''">
                                    <svg x-show="submittingPatrol" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                                    </svg>
                                    <span x-text="submittingPatrol ? 'Submitting...' : 'Submit Patrol Record'"></span>
                                </button>
                            </div>
                        </div>
                    </section>
                </div>

                <div x-show="checklistPhotoModalOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[95] flex items-center justify-center bg-slate-950/70 p-3 sm:p-6" x-on:click.self="closeChecklistPhotoPreview()" x-on:keydown.escape.window="checklistPhotoModalOpen && closeChecklistPhotoPreview()">
                    <section class="w-full max-w-2xl overflow-hidden rounded-md bg-white shadow-2xl">
                        <div class="flex items-center justify-between gap-3 border-b border-blue-100 px-4 py-3">
                            <p class="min-w-0 truncate text-sm font-semibold text-blue-950" x-text="selectedChecklistPhoto?.name || 'Checklist proof photo'"></p>
                            <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-blue-100 bg-white text-slate-700 hover:bg-blue-50" @click="closeChecklistPhotoPreview()" aria-label="Close proof photo preview">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                </svg>
                            </button>
                        </div>
                        <div class="bg-slate-950 p-2 sm:p-3">
                            <img x-show="selectedChecklistPhoto" :src="selectedChecklistPhoto?.url" alt="Selected checklist proof photo" class="max-h-[78dvh] w-full rounded object-contain">
                        </div>
                    </section>
                </div>

                <div x-show="incidentModalOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[85] flex items-stretch justify-center overflow-hidden bg-slate-950/60 p-0 sm:items-center sm:px-4 sm:py-6" x-on:keydown.escape.window="incidentModalOpen && closeIncidentModal()">
                    <section class="flex h-[100svh] max-h-[100dvh] w-full flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:max-h-[92vh] sm:max-w-xl sm:rounded-lg">
                        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-blue-100 px-4 py-4 sm:px-5">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Incident Report</p>
                                <h3 class="text-lg font-semibold text-blue-950">Report Observed Incident</h3>
                                <p class="mt-1 text-sm text-slate-500">This submits together with the completed checklist.</p>
                            </div>
                            <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-blue-100 bg-white text-slate-700 hover:bg-blue-50" @click="closeIncidentModal()" aria-label="Back to checklist">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                </svg>
                            </button>
                        </div>

                        <div class="mobile-scroll-area flex-1 space-y-4 overflow-y-auto p-4 sm:p-5">
                            <p x-show="incidentFormError" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700" x-text="incidentFormError"></p>

                            <div>
                                <label for="incident_category" class="block text-sm font-medium text-slate-700">Category</label>
                                <select x-ref="incidentCategory" id="incident_category" name="incident_category" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" :required="incident && incidentModalOpen" @change="incidentFormError = ''">
                                    @foreach ($incidentCategories as $category)
                                        <option value="{{ $category }}" @selected(old('incident_category') === $category)>{{ $category }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('incident_category')" class="mt-2" />
                            </div>

                            <div>
                                <label for="incident_priority" class="block text-sm font-medium text-slate-700">Priority</label>
                                <select x-ref="incidentPriority" id="incident_priority" name="incident_priority" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    @foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'critical' => 'Critical'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('incident_priority', 'normal') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('incident_priority')" class="mt-2" />
                            </div>

                            <div>
                                <label for="incident_description" class="block text-sm font-medium text-slate-700">Description</label>
                                <textarea x-ref="incidentDescription" id="incident_description" name="incident_description" rows="4" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" :required="incident && incidentModalOpen" @input="incidentFormError = ''">{{ old('incident_description') }}</textarea>
                                <x-input-error :messages="$errors->get('incident_description')" class="mt-2" />
                            </div>

                            <div>
                                <span class="block text-sm font-medium text-slate-700">
                                    Incident Images <span class="text-red-600">*</span>
                                </span>
                                <div class="mt-1 rounded-md border border-dashed border-blue-200 bg-blue-50/60 p-3">
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <label for="incident_images" class="inline-flex h-11 cursor-pointer items-center justify-center rounded-md border border-blue-200 bg-white px-3 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50">
                                            <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 5v14m7-7H5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            </svg>
                                            Upload Images
                                            <input x-ref="incidentUploadImages" id="incident_images" name="incident_images[]" type="file" accept="image/*" multiple class="sr-only" @change="updateIncidentImageCount($event)">
                                        </label>
                                        <button type="button" class="inline-flex h-11 items-center justify-center rounded-md border border-blue-200 bg-white px-3 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50" @click="$refs.incidentCameraImages?.click()">
                                            <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M4 8h3l1.5-2h7L17 8h3v11H4V8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                                <path d="M12 16a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" />
                                            </svg>
                                            Take Photo
                                        </button>
                                        <input x-ref="incidentCameraImages" id="incident_camera_images" name="incident_camera_images[]" type="file" accept="image/*" capture="environment" class="sr-only" @change="updateIncidentImageCount($event)">
                                    </div>

                                    <p class="mt-2 text-xs text-slate-500">
                                        Upload 2 to 3 images, or take one photo using the camera. Maximum 3 images total.
                                    </p>

                                    <div x-show="incidentImagePreviews.length" x-cloak class="mt-3">
                                        <p class="text-xs font-semibold text-slate-700">Selected image previews</p>
                                        <div class="mt-2 grid grid-cols-3 gap-2">
                                            <template x-for="preview in incidentImagePreviews" :key="preview.id">
                                                <figure class="min-w-0 overflow-hidden rounded-md border border-blue-100 bg-white">
                                                    <img :src="preview.url" :alt="preview.name" class="h-20 w-full object-cover sm:h-24">
                                                    <figcaption class="truncate px-2 py-1 text-[0.65rem] font-medium text-slate-600" x-text="preview.name"></figcaption>
                                                </figure>
                                            </template>
                                        </div>
                                    </div>

                                    <p x-show="incidentImageCount > 0 && ! incidentImageError" x-cloak class="mt-2 text-xs font-semibold text-blue-700" x-text="`${incidentImageCount} image${incidentImageCount === 1 ? '' : 's'} selected`"></p>
                                    <p x-show="incidentImageError" x-cloak class="mt-2 text-xs font-semibold text-red-700" x-text="incidentImageError"></p>
                                </div>
                                <x-input-error :messages="$errors->get('incident_image')" class="mt-2" />
                                <x-input-error :messages="$errors->get('incident_images')" class="mt-2" />
                                <x-input-error :messages="$errors->get('incident_images.*')" class="mt-2" />
                                <x-input-error :messages="$errors->get('incident_camera_images')" class="mt-2" />
                                <x-input-error :messages="$errors->get('incident_camera_images.*')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-2 border-t border-blue-100 px-4 py-4 sm:flex-row sm:justify-end sm:px-5">
                            <button type="button" class="inline-flex h-11 items-center justify-center rounded-md border border-blue-200 bg-white px-5 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60" @click="closeIncidentModal()" :disabled="submittingPatrol">
                                Back to Checklist
                            </button>
                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-md bg-blue-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-blue-300" :disabled="! patrolLogId || ! areaSelfieComplete() || submittingPatrol">
                                <svg x-show="submittingPatrol" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                                </svg>
                                <span x-text="submittingPatrol ? 'Submitting...' : 'Submit Patrol Record'"></span>
                            </button>
                        </div>
                    </section>
                </div>

                <div x-show="submittingPatrol" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[95] flex items-center justify-center bg-slate-950/50 p-4">
                    <x-brand-spinner class="w-full max-w-sm rounded-lg bg-white p-6 text-blue-950 shadow-2xl dark:bg-slate-900 dark:text-blue-100">
                        Submitting patrol record
                        <x-slot name="description">Please wait while the checkpoint visit is saved.</x-slot>
                    </x-brand-spinner>
                </div>
            </form>
            @endif
        </div>
    </div>
</x-app-layout>
