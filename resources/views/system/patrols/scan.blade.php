<x-app-layout>
    @php
        $incidentDefault = old('has_incident') || ($showIncident ?? false);
        $checkpointChecklistItems = \App\Support\PatrolChecklist::items();
        $incidentCategories = \App\Support\PatrolChecklist::incidentCategories();
        $guardName = $guardProfile?->name ?? Auth::user()->name;
        $guardEmployeeNo = $guardProfile?->employee_no ?? 'Account only';
        $guardRfid = $guardProfile?->rfid_uid ?? '';
        $guardShift = $guardProfile?->shift ?? 'Unassigned shift';
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

    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">{{ __('Scan Checkpoint') }}</h2>
                <p class="mt-1 text-sm text-blue-600">ESP32 RFID checkpoint scan with camera verification and patrol checklist</p>
            </div>
            <a href="{{ route('patrol-logs.index') }}" class="inline-flex w-full items-center justify-center rounded-md border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 sm:w-auto">
                My Patrol Logs
            </a>
        </div>
    </x-slot>

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

            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-blue-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Guard</p>
                    <p class="mt-2 text-lg font-semibold text-blue-950">{{ $guardName }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $guardEmployeeNo }}</p>
                </div>
                <div class="rounded-lg border border-blue-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Assigned RFID</p>
                    <p class="mt-2 font-mono text-lg font-semibold text-blue-950">{{ $guardRfid ?: 'No RFID assigned' }}</p>
                    <p class="mt-1 text-sm text-slate-500">Use this card at the checkpoint reader</p>
                </div>
                <div class="rounded-lg border border-blue-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Shift</p>
                    <p class="mt-2 text-lg font-semibold text-blue-950">{{ $guardShift }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ now()->timezone('Asia/Manila')->format('h:i A') }}</p>
                </div>
            </section>

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

                <section class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-6">
                    <div class="grid gap-5 lg:grid-cols-[0.85fr_1.15fr]">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Device Flow</p>
                            <h3 class="mt-2 text-lg font-semibold text-blue-950">Checkpoint RFID Scan</h3>

                            <div class="mt-5 grid gap-3">
                                <div class="flex items-center gap-3 rounded-md border border-blue-100 p-3" :class="pendingScan ? 'bg-blue-50/60' : 'bg-white'">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold transition" :class="pendingScan ? 'bg-white text-blue-700 ring-2 ring-blue-200' : 'bg-blue-700 text-white'">
                                        <svg x-show="pendingScan" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <span x-show="! pendingScan">1</span>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-blue-950">RFID checkpoint scan</p>
                                        <p class="text-xs text-slate-500">Tap the registered guard card on the ESP32 reader.</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 rounded-md border border-blue-100 p-3" :class="pendingScan ? 'bg-blue-50/60' : 'bg-white'">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold transition" :class="faceVerified ? 'bg-white text-blue-700 ring-2 ring-blue-200' : (pendingScan ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-500')">
                                        <svg x-show="faceVerified" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <span x-show="! faceVerified">2</span>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-blue-950">Camera verification</p>
                                        <p class="text-xs text-slate-500">Capture the guard face after the RFID scan is accepted.</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 rounded-md border border-blue-100 p-3" :class="faceVerified ? 'bg-blue-50/60' : 'bg-white'">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold" :class="faceVerified ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-500'">3</span>
                                    <div>
                                        <p class="text-sm font-semibold text-blue-950">Checklist and incident</p>
                                        <p class="text-xs text-slate-500">Submit the patrol checklist after face verification.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-blue-100 bg-blue-50/60 p-4 sm:p-5">
                            <div x-show="! pendingScan" x-transition.opacity.duration.200ms class="flex min-h-72 flex-col items-center justify-center rounded-md border border-dashed border-blue-200 bg-white p-6 text-center">
                                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-700">
                                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M7 7.5h10v9H7v-9ZM9.5 4.5h5M9.5 19.5h5M4 10v4M20 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                                <h3 class="mt-4 text-lg font-semibold text-blue-950">Waiting for RFID scan</h3>
                                <p class="mt-2 max-w-md text-sm leading-6 text-slate-500" x-text="scanMessage"></p>
                                <div x-show="patrolScheduleOpen" class="mt-5 inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-800 ring-1 ring-blue-100">
                                    <span class="h-2 w-2 animate-pulse rounded-full bg-blue-700"></span>
                                    <span x-text="patrolScheduleTestingMode ? 'Testing mode: open anytime' : 'Listening for ESP32 scan'"></span>
                                </div>
                                <div x-show="! patrolScheduleOpen" x-cloak class="mt-5 inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">
                                    Scheduled patrol only
                                </div>
                            </div>

                            <div x-show="pendingScan" x-cloak x-transition.opacity.duration.200ms class="space-y-5">
                                <div class="rounded-md border border-emerald-100 bg-white p-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">RFID Accepted</p>
                                            <h3 class="mt-2 text-lg font-semibold text-blue-950" x-text="pendingScan?.checkpoint?.name || 'Checkpoint'"></h3>
                                            <p class="mt-1 text-sm text-slate-500" x-text="pendingScan?.scanned_at || ''"></p>
                                        </div>
                                        <span class="inline-flex w-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
                                            Face required
                                        </span>
                                    </div>
                                </div>

                                <dl class="grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-md border border-blue-100 bg-white p-3">
                                        <dt class="text-xs font-semibold uppercase text-blue-800">Guard</dt>
                                        <dd class="mt-1 font-semibold text-slate-900" x-text="pendingScan?.guard?.name || guardName"></dd>
                                        <dd class="text-xs text-slate-500" x-text="pendingScan?.guard?.employee_no || guardEmployeeNo"></dd>
                                    </div>
                                    <div class="rounded-md border border-blue-100 bg-white p-3">
                                        <dt class="text-xs font-semibold uppercase text-blue-800">RFID UID</dt>
                                        <dd class="mt-1 font-mono text-slate-900" x-text="pendingScan?.rfid_uid || ''"></dd>
                                    </div>
                                    <div class="rounded-md border border-blue-100 bg-white p-3">
                                        <dt class="text-xs font-semibold uppercase text-blue-800">Checkpoint Code</dt>
                                        <dd class="mt-1 font-mono text-slate-900" x-text="pendingScan?.checkpoint?.code || pendingScan?.checkpoint_code || ''"></dd>
                                    </div>
                                    <div class="rounded-md border border-blue-100 bg-white p-3">
                                        <dt class="text-xs font-semibold uppercase text-blue-800">Device UID</dt>
                                        <dd class="mt-1 font-mono text-slate-900" x-text="pendingScan?.checkpoint?.device_uid || 'Device recorded'"></dd>
                                    </div>
                                </dl>

                                <x-input-error :messages="$errors->get('patrol_log_id')" class="mt-2" />

                                <div x-show="faceVerified" x-cloak x-transition.opacity.duration.200ms class="rounded-md border border-emerald-100 bg-emerald-50 px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-emerald-700 ring-1 ring-emerald-100">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-emerald-800">Face verification complete</p>
                                            <p class="mt-0.5 text-xs text-emerald-700">Review the scan details, then continue to the checklist.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <span class="text-sm font-semibold" :class="faceVerified ? 'text-emerald-700' : 'text-blue-700'" x-text="faceVerified ? 'Ready for checklist.' : 'Continue to camera verification.'"></span>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <button type="button" class="inline-flex h-11 items-center justify-center rounded-md bg-blue-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" x-show="faceVerified" x-transition.opacity.duration.150ms @click="continueToChecklist()">
                                            Continue to Checklist
                                        </button>
                                        <button type="button" class="inline-flex h-11 items-center justify-center rounded-md px-5 text-sm font-semibold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :class="faceVerified ? 'border border-blue-200 bg-white text-blue-700 hover:bg-blue-50' : 'bg-blue-700 text-white hover:bg-blue-800 disabled:bg-blue-300'" @click="openFaceModal()" :disabled="! patrolScheduleOpen || faceModelLoading || cameraOpening || verificationBusy || submittingPatrol">
                                            <svg x-show="faceModelLoading || cameraOpening" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                                            </svg>
                                            <span x-text="! patrolScheduleOpen ? 'Closed' : (faceModelLoading ? 'Loading...' : (cameraOpening ? 'Opening...' : (faceVerified ? 'Recheck Face' : 'Open Camera')))"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div x-show="faceModalOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-6">
                    <section class="face-verification-modal mobile-scroll-area overflow-y-auto rounded-lg bg-white shadow-2xl dark:bg-slate-900">
                        <div class="flex items-center justify-between gap-3 border-b border-blue-100 px-4 py-2.5 dark:border-slate-800">
                            <div>
                                <h3 class="text-base font-semibold text-blue-950">Camera Verification</h3>
                            </div>
                            <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-blue-100 bg-white text-slate-700 hover:bg-blue-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" @click="closeFaceModal()" aria-label="Close camera modal">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                </svg>
                            </button>
                        </div>

                        <div class="p-3 sm:p-4">
                            <div class="face-verification-preview relative aspect-video overflow-hidden rounded-md bg-slate-950">
                                <video x-ref="faceVideo" x-show="! faceCapture" class="h-full w-full object-cover" autoplay playsinline muted></video>
                                <img x-show="faceCapture" :src="faceCapture" alt="Captured face" class="h-full w-full object-cover">
                                <div x-show="! cameraOpen && ! faceCapture" class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-white">
                                    <span x-text="cameraOpening ? 'Opening camera...' : 'Camera preview'"></span>
                                </div>
                                <div x-show="faceModelLoading || cameraOpening || verificationBusy" x-cloak x-transition.opacity.duration.150ms class="absolute inset-0 flex items-center justify-center bg-slate-950/75 p-4 text-white">
                                    <x-brand-spinner>
                                        <span x-text="faceModelLoading ? 'Loading face model...' : (cameraOpening ? 'Opening camera...' : 'Verifying face...')"></span>
                                        <x-slot name="description">
                                            <span x-text="faceModelLoading ? 'Preparing the face matcher.' : (cameraOpening ? 'Requesting camera access.' : 'Matching the captured face reference.')"></span>
                                        </x-slot>
                                    </x-brand-spinner>
                                </div>
                            </div>
                            <canvas x-ref="faceCanvas" class="hidden"></canvas>
                            <input x-ref="faceCaptureInput" type="file" accept="image/*" capture="user" class="sr-only" @change="useFaceCaptureFile($event)">

                            <p x-show="cameraError" x-text="cameraError" class="mt-3 text-sm font-semibold text-red-700"></p>
                            <p x-show="verificationMessage" x-text="verificationMessage" class="mt-2 text-sm font-semibold leading-5 text-blue-800"></p>
                            <p class="mt-2 text-xs font-semibold text-blue-700" x-show="matchDistance !== null" x-text="`Match distance: ${matchDistance}`"></p>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button type="button" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 bg-white px-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60" @click="openCamera()" :disabled="faceModelLoading || cameraOpening || verificationBusy || submittingPatrol">
                                    <svg x-show="cameraOpening" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                                    </svg>
                                    <span x-text="cameraOpening ? 'Opening...' : 'Open Camera'"></span>
                                </button>
                                <button type="button" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 bg-white px-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60" @click="openFacePhotoCapture()" :disabled="faceModelLoading || cameraOpening || capturingFace || verificationBusy || submittingPatrol">
                                    <span x-text="capturingFace ? 'Preparing...' : 'Take Photo'"></span>
                                </button>
                                <button type="button" class="inline-flex h-9 items-center justify-center rounded-md bg-blue-700 px-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-blue-300" @click="captureFace()" :disabled="! cameraOpen || faceModelLoading || cameraOpening || capturingFace || verificationBusy || submittingPatrol">
                                    <svg x-show="capturingFace" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                                    </svg>
                                    <span x-text="capturingFace ? 'Capturing...' : 'Capture'"></span>
                                </button>
                                <button type="button" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 bg-white px-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60" @click="retakeFace()" :disabled="faceModelLoading || cameraOpening || capturingFace || verificationBusy || submittingPatrol">
                                    Retake
                                </button>
                            </div>

                            <div class="mt-2">
                                <button type="button" class="inline-flex h-10 w-full items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-blue-300" @click="verifyCapturedFace()" :disabled="! faceCapture || faceModelLoading || cameraOpening || verificationBusy || submittingPatrol">
                                    <svg x-show="faceModelLoading || verificationBusy" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                                    </svg>
                                    <span x-text="faceModelLoading ? 'Loading...' : (verificationBusy ? 'Verifying...' : 'Verify Face')"></span>
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
