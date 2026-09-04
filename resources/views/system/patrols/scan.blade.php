<x-app-layout>
    @php
        $incidentDefault = (bool) old('has_incident');
        $checkpointChecklistItems = \App\Support\PatrolChecklist::items();
        $incidentCategories = \App\Support\PatrolChecklist::incidentCategories();
        $guardName = $guardProfile?->name ?? Auth::user()->name;
        $guardEmployeeNo = $guardProfile?->employee_no ?? 'Account only';
        $openChecklist = $errors->any() && old('patrol_log_id');
        $faceRegistrationComplete = (bool) ($faceRegistrationComplete ?? false);
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

            @if ($guardProfile && ! $faceRegistrationComplete)
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                    Face registration must be completed before patrol scanning.
                    <a href="{{ route('profile.edit') }}" class="font-semibold underline hover:text-amber-900">Open Profile Settings</a>
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

            @if (! $guardProfile || $faceRegistrationComplete)
            <form
                method="POST"
                action="{{ route('patrol.store') }}"
                enctype="multipart/form-data"
                data-skip-global-loader="true"
                class="space-y-5"
                x-data="patrolScan({
                    incident: @js((bool) $incidentDefault),
                    pendingScan: @js($pendingScan),
                    pendingScanUrl: @js(route('patrol.pending-scan')),
                    faceVerifyUrl: @js(route('patrol.verify-face')),
                    guardName: @js($guardName),
                    guardEmployeeNo: @js($guardEmployeeNo),
                    patrolLogId: @js(old('patrol_log_id', $pendingPatrol?->id)),
                    scanMessage: @js($pendingScan ? 'RFID scan received successfully. Continue to face verification.' : ($patrolScheduleOpen ? $scanWaitingMessage : $patrolScheduleMessage)),
                    patrolScheduleOpen: @js($patrolScheduleOpen),
                    patrolScheduleTestingMode: @js($patrolScheduleTestingMode),
                    patrolScheduleMessage: @js($patrolScheduleMessage),
                    patrolTestingNotice: @js($patrolTestingNotice),
                    openChecklist: @js((bool) $openChecklist),
                    faceCapture: @js(old('face_capture', '')),
                    capturedDescriptor: @js(old('captured_descriptor', '')),
                })"
                x-init="boot()"
                x-on:submit="handleSubmit($event)"
                x-on:beforeunload.window="stopCamera(); if (pollingTimer) clearInterval(pollingTimer)"
            >
                @csrf

                <input type="hidden" name="patrol_log_id" :value="patrolLogId">
                <input type="hidden" name="face_capture" :value="faceCapture">
                <input type="hidden" name="captured_descriptor" :value="capturedDescriptor">

                <section class="rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-4">
                    <div class="space-y-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-[0.68rem] font-semibold uppercase tracking-wide text-blue-700">Device Flow</p>
                                <h3 class="mt-0.5 text-base font-semibold text-blue-950">Checkpoint Scan Progress</h3>
                            </div>
                            <span class="inline-flex w-fit items-center gap-2 rounded-md px-2.5 py-1 text-[0.68rem] font-bold uppercase tracking-wide ring-1"
                                :class="faceVerified ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : (pendingScan ? 'bg-blue-50 text-blue-700 ring-blue-200' : 'bg-slate-50 text-slate-600 ring-slate-200')"
                                x-text="faceVerified ? 'Step 3 of 3' : (pendingScan ? 'Step 2 of 3' : 'Step 1 of 3')">
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
                                        :class="faceVerified ? 'bg-emerald-600 text-white' : (pendingScan ? 'bg-blue-700 text-white ring-4 ring-blue-100' : 'bg-white text-slate-400 ring-1 ring-slate-200')">
                                        <svg x-show="faceVerified" x-cloak class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <span x-show="! faceVerified">2</span>
                                    </span>
                                </div>
                                <span class="h-1 rounded-full transition" :class="faceVerified ? 'bg-emerald-500' : (pendingScan ? 'bg-blue-200' : 'bg-slate-200')"></span>
                                <div class="flex justify-center">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold shadow-sm transition sm:h-9 sm:w-9 sm:text-sm"
                                        :class="faceVerified ? 'bg-blue-700 text-white ring-4 ring-blue-100' : 'bg-white text-slate-400 ring-1 ring-slate-200'">3</span>
                                </div>
                            </div>

                            <div class="mt-2 grid grid-cols-3 gap-1 text-center text-[0.62rem] font-bold uppercase tracking-wide sm:text-[0.68rem]">
                                <span class="whitespace-nowrap" :class="pendingScan ? 'text-emerald-700' : 'text-blue-800'">RFID Scan</span>
                                <span class="whitespace-nowrap" :class="faceVerified ? 'text-emerald-700' : (pendingScan ? 'text-blue-800' : 'text-slate-400')">Face Verify</span>
                                <span class="whitespace-nowrap" :class="faceVerified ? 'text-blue-800' : 'text-slate-400'">Checklist</span>
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

                            <div x-show="pendingScan && ! faceVerified" x-cloak x-transition.opacity.duration.200ms class="space-y-3">
                                <div class="rounded-md border border-emerald-100 bg-white p-3">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p class="text-[0.68rem] font-semibold uppercase tracking-wide text-emerald-700">Step 1 complete</p>
                                            <h3 class="mt-0.5 text-base font-semibold text-blue-950" x-text="pendingScan?.checkpoint?.name || 'Checkpoint'"></h3>
                                            <p class="mt-1 text-sm text-slate-500" x-text="pendingScan?.scanned_at || ''"></p>
                                        </div>
                                        <button type="button" class="inline-flex w-fit rounded-md bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-200 transition hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="openFaceModal()">
                                            Face required
                                        </button>
                                    </div>
                                </div>

                                <dl class="grid gap-2 sm:grid-cols-4">
                                    <div class="rounded-md border border-blue-100 bg-white p-2.5">
                                        <dt class="text-[0.68rem] font-semibold uppercase text-blue-800">Guard</dt>
                                        <dd class="mt-1 truncate text-sm font-semibold text-slate-900" x-text="pendingScan?.guard?.name || guardName"></dd>
                                        <dd class="text-xs text-slate-500" x-text="pendingScan?.guard?.employee_no || guardEmployeeNo"></dd>
                                    </div>
                                    <div class="rounded-md border border-blue-100 bg-white p-2.5">
                                        <dt class="text-[0.68rem] font-semibold uppercase text-blue-800">RFID UID</dt>
                                        <dd class="mt-1 truncate font-mono text-sm text-slate-900" x-text="pendingScan?.rfid_uid || ''"></dd>
                                    </div>
                                    <div class="rounded-md border border-blue-100 bg-white p-2.5">
                                        <dt class="text-[0.68rem] font-semibold uppercase text-blue-800">Checkpoint</dt>
                                        <dd class="mt-1 truncate font-mono text-sm text-slate-900" x-text="pendingScan?.checkpoint?.code || pendingScan?.checkpoint_code || ''"></dd>
                                    </div>
                                    <div class="rounded-md border border-blue-100 bg-white p-2.5">
                                        <dt class="text-[0.68rem] font-semibold uppercase text-blue-800">Device UID</dt>
                                        <dd class="mt-1 truncate font-mono text-sm text-slate-900" x-text="pendingScan?.checkpoint?.device_uid || 'Device recorded'"></dd>
                                    </div>
                                </dl>

                                <x-input-error :messages="$errors->get('patrol_log_id')" class="mt-2" />
                            </div>

                            <div x-show="faceVerified" x-cloak x-transition.opacity.duration.200ms class="space-y-3">
                                <div class="rounded-md border border-emerald-100 bg-emerald-50 px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-emerald-700 ring-1 ring-emerald-100">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-emerald-800">Steps 1 and 2 complete</p>
                                            <p class="mt-0.5 text-xs text-emerald-700">RFID and face verification are confirmed.</p>
                                        </div>
                                    </div>
                                </div>

                                <dl class="grid gap-2 sm:grid-cols-2">
                                    <div class="rounded-md border border-blue-100 bg-white p-2.5">
                                        <dt class="text-[0.68rem] font-semibold uppercase text-blue-800">Checkpoint</dt>
                                        <dd class="mt-1 truncate text-sm font-semibold text-slate-900" x-text="pendingScan?.checkpoint?.name || 'Checkpoint'"></dd>
                                        <dd class="text-xs text-slate-500" x-text="pendingScan?.scanned_at || ''"></dd>
                                    </div>
                                    <div class="rounded-md border border-blue-100 bg-white p-2.5">
                                        <dt class="text-[0.68rem] font-semibold uppercase text-blue-800">Guard</dt>
                                        <dd class="mt-1 truncate text-sm font-semibold text-slate-900" x-text="pendingScan?.guard?.name || guardName"></dd>
                                        <dd class="text-xs text-slate-500" x-text="pendingScan?.guard?.employee_no || guardEmployeeNo"></dd>
                                    </div>
                                </dl>

                                <div class="rounded-md border border-blue-100 bg-white p-3">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-[0.68rem] font-semibold uppercase tracking-wide text-blue-700">Step 3</p>
                                            <h4 class="mt-0.5 text-sm font-semibold text-blue-950">Checklist and Incident</h4>
                                            <p class="mt-1 text-xs text-slate-500">Complete the patrol checklist before submitting this checkpoint visit.</p>
                                        </div>
                                        <div class="flex flex-col gap-2 sm:flex-row">
                                            <button type="button" class="inline-flex h-10 items-center justify-center rounded-md border border-blue-200 bg-white px-4 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" @click="openFaceModal()" :disabled="faceModelLoading || cameraOpening || verificationBusy || submittingPatrol">
                                                Recheck Face
                                            </button>
                                            <button type="button" class="inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-blue-300" @click="continueToChecklist()" :disabled="submittingPatrol">
                                                Open Checklist
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div x-show="faceModalOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-slate-950/55 p-3 sm:p-6">
                    <section class="face-verification-modal mobile-scroll-area overflow-y-auto rounded-md bg-white px-4 py-4 text-center shadow-2xl dark:bg-slate-900 sm:px-6 sm:py-5">
                        <div class="flex justify-end">
                            <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-slate-700 ring-1 ring-blue-100 transition hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-900" @click="closeFaceModal()" aria-label="Close face verification">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                </svg>
                            </button>
                        </div>

                        <div class="-mt-2">
                            <p class="text-sm font-bold uppercase tracking-wide text-blue-700">Step 2</p>
                            <h3 class="mt-1 text-2xl font-bold tracking-tight text-blue-950 dark:text-white">Face Verification</h3>
                        </div>

                        <div class="mt-6">
                            <div class="face-verification-circle relative mx-auto aspect-square">
                                <span x-show="cameraOpen && ! faceCapture" x-cloak class="face-auto-scan-ring" :class="faceGuideState === 'centered' ? 'opacity-100' : 'opacity-70'"></span>

                                <div class="absolute inset-0 overflow-hidden rounded-full border-[6px] border-blue-700 bg-gradient-to-b from-sky-100 via-blue-50 to-emerald-50 shadow-[0_16px_45px_rgba(37,99,235,0.18)] dark:border-blue-500 dark:from-slate-800 dark:via-slate-900 dark:to-slate-800">
                                    <video x-ref="faceVideo" x-show="cameraOpen && ! faceCapture" class="h-full w-full object-cover" autoplay playsinline muted></video>
                                    <img x-show="faceCapture" :src="faceCapture" alt="Captured face" class="h-full w-full object-cover">

                                    <div x-show="! cameraOpen && ! faceCapture" class="absolute inset-0 flex flex-col items-center justify-center px-6 text-blue-800 dark:text-blue-100">
                                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-white/75 text-blue-700 shadow-sm ring-1 ring-blue-100 dark:bg-slate-900/80 dark:text-blue-200 dark:ring-slate-700">
                                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" stroke="currentColor" stroke-width="2" />
                                                <path d="M4 19c1.6-3 4.3-4.5 8-4.5S18.4 16 20 19M5 5h3M16 5h3M5 5v3M19 5v3M5 16v3M5 19h3M19 16v3M16 19h3" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            </svg>
                                        </span>
                                        <p class="mt-4 text-sm font-semibold">Allow camera access</p>
                                        <p class="mt-1 text-xs leading-5 text-blue-700/80 dark:text-blue-200">Your camera preview will stay inside this circle.</p>
                                    </div>
                                </div>

                                <div x-show="faceModelLoading || cameraOpening || capturingFace || verificationBusy" x-cloak x-transition.opacity.duration.150ms class="absolute inset-0 flex items-center justify-center rounded-full bg-white/85 p-4 text-blue-950 backdrop-blur-sm dark:bg-slate-950/80 dark:text-blue-100">
                                    <x-brand-spinner>
                                        <span x-text="faceModelLoading ? 'Loading face model...' : (cameraOpening ? 'Opening camera...' : (capturingFace ? 'Capturing face...' : 'Verifying face...'))"></span>
                                        <x-slot name="description">
                                            <span x-text="faceModelLoading ? 'Preparing the face matcher.' : (cameraOpening ? 'Requesting camera access.' : 'This step will continue automatically.')"></span>
                                        </x-slot>
                                    </x-brand-spinner>
                                </div>
                            </div>
                            <canvas x-ref="faceCanvas" class="hidden"></canvas>
                            <input x-ref="faceCaptureInput" type="file" accept="image/*" capture="user" class="sr-only" @change="useFaceCaptureFile($event)">

                            <div class="mt-6 space-y-3 text-left">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-700 ring-1 ring-blue-100 dark:bg-slate-800 dark:text-blue-200 dark:ring-slate-700">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M5 5h4M15 5h4M5 5v4M19 5v4M5 15v4M5 19h4M19 15v4M15 19h4M9.5 12a2.5 2.5 0 1 1 5 0 2.5 2.5 0 0 1-5 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Position your face inside the circle</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-900">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="2" />
                                            <path d="m7.8 12.2 2.5 2.5 5.9-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Keep still while scanning</p>
                                </div>
                            </div>

                            <div x-show="cameraOpen && ! faceCapture" x-cloak class="mx-auto mt-5 max-w-64">
                                <div class="h-1.5 overflow-hidden rounded-full bg-emerald-100">
                                    <div class="h-full rounded-full bg-emerald-500 transition-all duration-150" :style="`width: ${faceScanProgress}%`"></div>
                                </div>
                            </div>

                            <p x-show="cameraError" x-text="cameraError" class="mt-4 text-sm font-semibold text-red-700"></p>
                            <p x-show="verificationMessage && (! cameraOpen || faceCapture)" x-text="verificationMessage" class="mt-3 text-sm font-semibold leading-5 text-blue-800 dark:text-blue-200"></p>
                            <p class="mt-2 text-xs font-semibold text-blue-700 dark:text-blue-300" x-show="matchDistance !== null" x-text="`Match distance: ${matchDistance}`"></p>

                            <div x-show="cameraOpen && ! faceCapture && ! cameraError" x-cloak class="mx-auto mt-5 inline-flex items-center gap-2 rounded-md bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-900">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span x-text="verificationBusy ? 'Checking face...' : 'Verifying automatically...'"></span>
                            </div>

                            <div class="mt-3">
                                <button x-show="! cameraOpen && ! faceCapture" type="button" class="inline-flex h-10 w-full items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-blue-300 dark:focus:ring-offset-slate-900" @click="beginAutomaticFaceVerification()" :disabled="faceModelLoading || cameraOpening || capturingFace || verificationBusy || submittingPatrol">
                                    <svg x-show="faceModelLoading || cameraOpening" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                                    </svg>
                                    <span x-text="faceModelLoading ? 'Preparing...' : (cameraOpening ? 'Opening...' : 'Allow Camera Access')"></span>
                                </button>

                                <button x-show="cameraError && ! cameraOpening && ! faceModelLoading && ! verificationBusy && ! capturingFace" x-cloak type="button" class="mt-2 inline-flex h-10 w-full items-center justify-center rounded-md border border-blue-200 bg-white px-4 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-blue-200 dark:hover:bg-slate-800" @click="restartFaceVerification()" :disabled="submittingPatrol">
                                    Try Again
                                </button>
                            </div>
                        </div>
                    </section>
                </div>

                <div x-show="checklistModalOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[80] flex items-stretch justify-center overflow-hidden bg-slate-950/55 p-0 sm:items-center sm:px-4 sm:py-6">
                    <section class="mobile-scroll-area h-[100dvh] w-full overflow-y-auto bg-white shadow-2xl sm:h-auto sm:min-h-0 sm:max-h-[92vh] sm:max-w-5xl sm:rounded-lg">
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

                        <div class="grid gap-5 p-4 sm:p-5 lg:grid-cols-[1fr_0.95fr]">
                            <div>
                                <h4 class="text-base font-semibold text-blue-950">Checkpoint Checklist</h4>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    @foreach ($checkpointChecklistItems as $field => $label)
                                        <label class="flex min-h-12 items-center gap-3 rounded-md border border-blue-100 p-3 text-sm font-medium text-slate-700 transition hover:bg-blue-50">
                                            <input id="{{ $field }}" type="checkbox" name="{{ $field }}" value="1" class="rounded border-slate-300 text-blue-700 focus:ring-blue-500" @checked(old($field))>
                                            {{ $label }}
                                        </label>
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
                                        <p class="mt-1 text-sm text-slate-500">Fill this only when something happened.</p>
                                    </div>
                                    <label class="inline-flex w-fit items-center gap-2 rounded-full border border-blue-100 bg-white px-3 py-2 text-sm font-semibold text-blue-800">
                                        <input type="checkbox" name="has_incident" value="1" x-model="incident" class="rounded border-slate-300 text-blue-700 focus:ring-blue-500" @checked($incidentDefault)>
                                        Incident observed
                                    </label>
                                </div>

                                <div class="mt-5 grid gap-4" x-show="incident" x-cloak x-transition.opacity.duration.200ms>
                                    <div>
                                        <label for="incident_category" class="block text-sm font-medium text-slate-700">Category</label>
                                        <select id="incident_category" name="incident_category" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" :required="incident">
                                            @foreach ($incidentCategories as $category)
                                                <option value="{{ $category }}" @selected(old('incident_category') === $category)>{{ $category }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('incident_category')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="incident_priority" class="block text-sm font-medium text-slate-700">Priority</label>
                                        <select id="incident_priority" name="incident_priority" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            @foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'critical' => 'Critical'] as $value => $label)
                                                <option value="{{ $value }}" @selected(old('incident_priority', 'normal') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('incident_priority')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="incident_description" class="block text-sm font-medium text-slate-700">Description</label>
                                        <textarea id="incident_description" name="incident_description" rows="4" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" :required="incident">{{ old('incident_description') }}</textarea>
                                        <x-input-error :messages="$errors->get('incident_description')" class="mt-2" />
                                    </div>
                                    <div>
                                        <span class="block text-sm font-medium text-slate-700">
                                            Incident Images <span class="text-red-600">*</span>
                                        </span>
                                        <div class="mt-1 rounded-md border border-dashed border-blue-200 bg-white p-3">
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                <label for="incident_images" class="inline-flex h-10 cursor-pointer items-center justify-center rounded-md border border-blue-200 bg-white px-3 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50">
                                                    Upload Images
                                                    <input x-ref="incidentUploadImages" id="incident_images" name="incident_images[]" type="file" accept="image/*" multiple class="sr-only" @change="updateIncidentImageCount($event)">
                                                </label>
                                                <button type="button" class="inline-flex h-10 items-center justify-center rounded-md border border-blue-200 bg-white px-3 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50" @click="$refs.incidentCameraImages?.click()">
                                                    Take Photo
                                                </button>
                                                <input x-ref="incidentCameraImages" id="incident_camera_images" name="incident_camera_images[]" type="file" accept="image/*" capture="environment" class="sr-only" @change="updateIncidentImageCount($event)">
                                            </div>
                                            <p class="mt-2 text-xs text-slate-500">
                                                Required when an incident is observed. Upload 2 to 3 images, or take one photo using the camera. Maximum 3 images total.
                                            </p>
                                            <div x-show="incidentImagePreviews.length" x-cloak class="mt-3">
                                                <p class="text-xs font-semibold text-slate-700">Selected image previews</p>
                                                <div class="mt-2 grid grid-cols-3 gap-2">
                                                    <template x-for="preview in incidentImagePreviews" :key="preview.id">
                                                        <figure class="min-w-0 overflow-hidden rounded-md border border-blue-100 bg-blue-50">
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
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-blue-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <p class="text-sm text-slate-500">
                                Submitting will complete the ESP32 RFID scan, face verification step, checklist, and incident report if provided.
                            </p>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <button type="button" class="inline-flex h-11 items-center justify-center rounded-md border border-blue-200 bg-white px-5 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60" @click="checklistModalOpen = false" :disabled="submittingPatrol">
                                    Review Scan
                                </button>
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-md bg-blue-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" :disabled="! patrolLogId || ! faceVerified || submittingPatrol" :class="(! patrolLogId || ! faceVerified || submittingPatrol) ? 'cursor-not-allowed opacity-60' : ''">
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

                <div x-show="submittingPatrol" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/50 p-4">
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
