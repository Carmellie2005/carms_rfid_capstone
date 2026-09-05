@php
    $isSupervisor = $user->role === 'admin';
    $isGuard = $user->role === 'guard';
    $guardProfile = $isGuard ? $user->guardProfile : null;
    $hasFaceRegistration = $guardProfile?->faceDescriptors?->isNotEmpty() ?? false;
    $hasProcessedFaceRegistration = $guardProfile?->faceDescriptors
        ? $guardProfile->faceDescriptors->contains(fn ($sample) => is_array($sample->descriptor) && count($sample->descriptor) === 128)
        : false;
    $roleLabel = $isSupervisor ? 'Supervisor' : ucfirst($user->role ?? 'User');
    $fallbackIconPath = $isSupervisor
        ? 'images/user-icons/supervisor-account.png'
        : 'images/user-icons/guard-account.png';
    $profileFallbackPhotoUrl = asset($fallbackIconPath);
    $profilePhotoUrl = $user->profile_photo_path
        && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_photo_path)
        ? asset('storage/'.$user->profile_photo_path)
        : $profileFallbackPhotoUrl;
    $profileCompletionItems = [
        filled($user->name),
        filled($user->username),
        filled($user->email),
        filled($user->phone),
        filled($user->profile_photo_path),
    ];

    if ($isGuard) {
        $profileCompletionItems[] = (bool) $guardProfile;
        $profileCompletionItems[] = $hasProcessedFaceRegistration;
    }

    $profileCompletionPercent = count($profileCompletionItems) > 0
        ? (int) round((count(array_filter($profileCompletionItems)) / count($profileCompletionItems)) * 100)
        : 0;
    $faceDataLabel = $hasProcessedFaceRegistration
        ? 'Face Data Processed'
        : ($hasFaceRegistration ? 'Face Data Needs Processing' : 'Face Data Missing');
    $faceRegistrationHasErrors = $errors->has('face_registration_capture')
        || $errors->has('face_liveness_confirmed')
        || count($errors->get('face_descriptors.*')) > 0;
@endphp

<section>
    <header>
        <h2 class="text-base font-semibold text-blue-950">
            {{ __('Profile Picture & Information') }}
        </h2>

        <p class="mt-1 text-xs text-slate-600">
            {{ __('Profile photo and personal account details.') }}
        </p>

        @if ($isGuard)
            <div class="mt-3 grid gap-3 border-y border-blue-100 py-2 sm:grid-cols-2">
                <div>
                    <p class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">Profile Completion</p>
                    <p class="mt-0.5 text-lg font-bold text-blue-950">{{ $profileCompletionPercent }}%</p>
                    <p class="text-xs font-semibold {{ $profileCompletionPercent === 100 ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ $profileCompletionPercent === 100 ? 'Complete' : 'Needs updates' }}
                    </p>
                </div>
                <div>
                    <p class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">Face Registration</p>
                    <p class="mt-1 text-xs font-semibold {{ $hasProcessedFaceRegistration ? 'text-emerald-700' : 'text-amber-700' }}">{{ $faceDataLabel }}</p>
                </div>
            </div>
        @endif
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form
        method="post"
        action="{{ route('profile.update') }}"
        enctype="multipart/form-data"
        class="mt-4 space-y-4"
        x-data="guardFaceForm({
            faceSamples: [],
            liveRegistration: @js($isGuard && $guardProfile && ! $hasProcessedFaceRegistration),
            openRegistration: @js($faceRegistrationHasErrors),
        })"
        x-init="boot()"
        x-on:submit="handleSubmit($event)"
        x-on:beforeunload.window="stopRegistrationCamera()"
    >
        @csrf
        @method('patch')

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <span class="inline-flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-50 ring-1 ring-blue-100">
                <img src="{{ $profilePhotoUrl }}" alt="{{ $roleLabel }} profile photo" onerror="this.onerror=null; this.src='{{ $profileFallbackPhotoUrl }}';" class="h-full w-full object-cover">
            </span>

            <div class="min-w-0 flex-1">
                <x-input-label for="profile_photo" :value="__('Profile Picture')" />
                <input id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-md border border-slate-300 text-xs text-slate-700 shadow-sm file:mr-3 file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                <p class="mt-1.5 text-[0.68rem] text-slate-500">JPG, PNG, WEBP. Maximum size: 2 MB.</p>
                @if ($user->profile_photo_path)
                    <label for="remove_profile_photo" class="mt-2 flex items-center gap-2 text-xs font-medium text-slate-700">
                        <input id="remove_profile_photo" name="remove_profile_photo" type="checkbox" value="1" class="rounded border-slate-300 text-blue-700 shadow-sm focus:ring-blue-500">
                        <span>Remove current profile picture</span>
                    </label>
                @endif
                <x-input-error class="mt-2" :messages="$errors->get('profile_photo')" />
                <x-input-error class="mt-2" :messages="$errors->get('remove_profile_photo')" />
            </div>
        </div>

        @if ($isGuard)
            <div class="border-t border-blue-100 pt-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-blue-950">Face Registration</h3>
                        <p class="mt-1 text-xs text-slate-600">One-time face reference for checkpoint verification.</p>
                    </div>
                    <span class="inline-flex w-fit rounded-md px-2.5 py-1 text-[0.68rem] font-semibold ring-1 {{ $hasProcessedFaceRegistration ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : ($hasFaceRegistration ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-red-50 text-red-700 ring-red-200') }}">
                        {{ $hasProcessedFaceRegistration ? 'Ready' : ($hasFaceRegistration ? 'Needs face data' : 'Required') }}
                    </span>
                </div>

                @if (! $guardProfile)
                    <p class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
                        This account is not linked to a guard profile yet.
                    </p>
                @elseif (! $hasProcessedFaceRegistration)
                    <div class="mt-3">
                        <input type="hidden" name="face_registration_capture" :value="liveCapture">
                        <input type="hidden" name="face_liveness_confirmed" :value="livenessPassed ? '1' : ''">
                        <template x-if="liveDescriptor">
                            <input type="hidden" name="face_descriptors[]" :value="descriptorPayload(liveDescriptor)">
                        </template>

                        <div class="rounded-md border border-teal-100 bg-teal-50/70 p-3 sm:p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-teal-700 ring-1 ring-teal-100">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M5 5h4M15 5h4M5 5v4M19 5v4M5 15v4M5 19h4M19 15v4M15 19h4M9.5 12a2.5 2.5 0 1 1 5 0 2.5 2.5 0 0 1-5 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-teal-900" x-text="registrationStatusTitle()"></p>
                                        <p class="mt-1 text-xs leading-5 text-slate-600" x-text="registrationStatusMessage()"></p>
                                        <p class="mt-2 text-xs font-semibold" x-show="descriptorMessage && ! descriptorError" x-cloak :class="liveDescriptor ? 'text-emerald-700' : 'text-teal-700'" x-text="descriptorMessage"></p>
                                    </div>
                                </div>

                                <button type="button" class="inline-flex h-10 shrink-0 items-center justify-center rounded-md bg-teal-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:bg-teal-300" x-on:click="openRegistrationModal()" x-bind:disabled="liveProcessing">
                                    <span x-text="registrationActionLabel()"></span>
                                </button>
                            </div>

                            <div class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700" x-show="descriptorError" x-cloak>
                                <span x-text="descriptorError"></span>
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('face_registration_capture')" />
                            <x-input-error class="mt-2" :messages="$errors->get('face_liveness_confirmed')" />
                            @foreach ($errors->get('face_descriptors.*') as $messages)
                                <x-input-error :messages="$messages" class="mt-2" />
                            @endforeach
                        </div>

                        <div x-show="registrationModalOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[85] flex items-stretch justify-center overflow-hidden bg-slate-950/60 p-0 sm:items-center sm:px-4 sm:py-6" x-on:click.self="closeRegistrationModal()" x-on:keydown.escape.window="registrationModalOpen && closeRegistrationModal()">
                            <section class="flex h-[100svh] max-h-[100dvh] w-full flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:max-h-[92vh] sm:max-w-3xl sm:rounded-lg">
                                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-teal-100 px-4 py-4 sm:px-5">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Face Registration</p>
                                        <h3 class="text-lg font-semibold text-blue-950">Register Guard Face</h3>
                                        <p class="mt-1 text-sm text-slate-500">Complete the face guide and random challenge before saving.</p>
                                    </div>
                                    <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-teal-100 bg-white text-slate-700 hover:bg-teal-50" x-on:click="closeRegistrationModal()" aria-label="Close face registration">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="mobile-scroll-area flex-1 overflow-y-auto bg-gradient-to-br from-teal-50 via-white to-emerald-50 p-4 sm:p-5">
                                    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(17rem,0.9fr)] lg:items-center">
                                        <div class="rounded-md border border-teal-100 bg-white/85 p-4 shadow-sm">
                                            <div class="mx-auto face-verification-circle">
                                                <div class="relative aspect-square">
                                                    <span x-show="registrationCameraOpen && ! liveCapture && ! livenessPassed" x-cloak class="face-auto-scan-ring opacity-80"></span>
                                                    <div class="absolute inset-0 z-10 overflow-hidden rounded-full border-[6px] border-teal-600 bg-gradient-to-b from-teal-100 via-sky-50 to-emerald-50 shadow-[0_16px_45px_rgba(13,148,136,0.20)]">
                                                        <video x-ref="registrationVideo" x-show="registrationCameraOpen && ! liveCapture" class="camera-unmirrored h-full w-full object-cover" autoplay playsinline muted></video>
                                                        <img x-show="liveCapture" :src="liveCapture" alt="Captured live face registration" class="camera-unmirrored h-full w-full object-cover" x-cloak>
                                                        <div x-show="! registrationCameraOpen && ! liveCapture" class="absolute inset-0 flex flex-col items-center justify-center px-6 text-center text-teal-800">
                                                            <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                <path d="M5 5h4M15 5h4M5 5v4M19 5v4M5 15v4M5 19h4M19 15v4M15 19h4M9.5 12a2.5 2.5 0 1 1 5 0 2.5 2.5 0 0 1-5 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                                            </svg>
                                                            <span class="mt-3 text-sm font-semibold">Ready for face registration</span>
                                                        </div>
                                                    </div>
                                                    <div x-show="liveProcessing" x-cloak x-transition.opacity.duration.150ms class="absolute inset-0 z-20 flex items-center justify-center rounded-full bg-white/85 p-4 text-center text-teal-900 backdrop-blur-sm">
                                                        <div>
                                                            <svg class="mx-auto h-7 w-7 animate-spin text-teal-700" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                                                            </svg>
                                                            <p class="mt-2 text-xs font-semibold">Processing face data...</p>
                                                        </div>
                                                    </div>
                                                    <div x-show="registrationCameraOpen && ! liveCapture" x-cloak class="absolute left-1/2 top-3 z-20 -translate-x-1/2 rounded-full px-3 py-1 text-xs font-semibold shadow-sm ring-1" :class="livenessPassed ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-white/90 text-teal-800 ring-teal-100'">
                                                        <span x-text="livenessChallengeBadge()"></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <canvas x-ref="registrationCanvas" class="hidden"></canvas>
                                            <input x-ref="registrationPhotoInput" type="file" accept="image/*" capture="user" class="sr-only" x-on:change="useRegistrationCaptureFile($event)">

                                            <div x-show="registrationCameraOpen && ! liveCapture" x-cloak class="mx-auto mt-5 max-w-64">
                                                <div class="h-1.5 overflow-hidden rounded-full bg-teal-100">
                                                    <div class="h-full rounded-full bg-teal-600 transition-all duration-150" :style="`width: ${livenessPassed ? 100 : (livenessStatus === 'align' ? 25 : 65)}%`"></div>
                                                </div>
                                            </div>

                                            <div class="mt-4 rounded-md border border-teal-100 bg-teal-50 px-3 py-2 text-sm text-teal-800" x-show="descriptorMessage && ! descriptorError" x-cloak>
                                                <span x-text="descriptorMessage"></span>
                                            </div>
                                        </div>

                                        <div class="flex flex-col justify-between gap-3">
                                            <div class="rounded-md border border-teal-100 bg-white px-3 py-3 text-xs text-teal-800 shadow-sm">
                                                <p class="font-semibold">Live Face Reference</p>
                                                <p class="mt-1 text-slate-600">Center the guard face, complete the random action, then capture the reference.</p>
                                                <p class="mt-1.5 text-[0.68rem] font-semibold text-teal-700">Challenge: smile or turn head slightly.</p>
                                            </div>

                                            <div class="grid gap-2 text-xs">
                                                <div class="flex items-center gap-2 rounded-md border border-teal-100 bg-white px-3 py-2 shadow-sm">
                                                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[0.68rem] font-bold ring-1" :class="['face', 'blink', 'smile', 'turn', 'complete'].includes(livenessStatus) ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-50 text-slate-500 ring-slate-200'">1</span>
                                                    <span class="font-medium text-slate-700">Face inside guide</span>
                                                </div>
                                                <div class="flex items-center gap-2 rounded-md border border-teal-100 bg-white px-3 py-2 shadow-sm">
                                                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[0.68rem] font-bold ring-1" :class="livenessPassed ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-50 text-slate-500 ring-slate-200'">2</span>
                                                    <span class="font-medium text-slate-700" x-text="livenessPassed ? livenessChallengeLabel() : (registrationCameraOpen ? livenessChallengeLabel() : 'Complete random challenge')"></span>
                                                </div>
                                                <div class="flex items-center gap-2 rounded-md border border-teal-100 bg-white px-3 py-2 shadow-sm">
                                                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[0.68rem] font-bold ring-1" :class="liveDescriptor ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-50 text-slate-500 ring-slate-200'">3</span>
                                                    <span class="font-medium text-slate-700">Capture reference</span>
                                                </div>
                                            </div>

                                            <div class="grid gap-2">
                                                <button x-ref="registrationPrimaryAction" type="button" class="inline-flex h-10 items-center justify-center rounded-md border border-teal-200 bg-white px-3 text-xs font-semibold text-teal-700 shadow-sm transition hover:bg-teal-50 disabled:cursor-not-allowed disabled:opacity-60" x-on:click="openRegistrationCamera()" x-bind:disabled="liveProcessing">
                                                    Open Camera
                                                </button>
                                                <button type="button" class="inline-flex h-10 items-center justify-center rounded-md border border-teal-200 bg-white px-3 text-xs font-semibold text-teal-700 shadow-sm transition hover:bg-teal-50 disabled:cursor-not-allowed disabled:opacity-60" x-on:click="openRegistrationPhotoCapture()" x-bind:disabled="liveProcessing">
                                                    Take Photo
                                                </button>
                                                <button type="button" class="inline-flex h-10 items-center justify-center rounded-md bg-teal-700 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:bg-teal-300" x-on:click="captureRegistrationFace()" x-bind:disabled="! registrationCameraOpen || ! livenessPassed || liveProcessing">
                                                    <span x-text="liveProcessing ? 'Processing...' : 'Capture Face'"></span>
                                                </button>
                                                <button type="button" class="inline-flex h-10 items-center justify-center rounded-md border border-teal-200 bg-white px-3 text-xs font-semibold text-teal-700 shadow-sm transition hover:bg-teal-50" x-show="liveCapture" x-cloak x-on:click="retakeRegistrationFace()">
                                                    Retake
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700" x-show="descriptorError" x-cloak>
                                        <span x-text="descriptorError"></span>
                                    </div>
                                    <x-input-error class="mt-2" :messages="$errors->get('face_registration_capture')" />
                                    <x-input-error class="mt-2" :messages="$errors->get('face_liveness_confirmed')" />
                                    @foreach ($errors->get('face_descriptors.*') as $messages)
                                        <x-input-error :messages="$messages" class="mt-2" />
                                    @endforeach
                                </div>
                            </section>
                        </div>
                    </div>
                @else
                    <div class="mt-4 rounded-md border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">
                        Face registration is completed and locked.
                    </div>
                @endif
            </div>
        @endif

        <div class="grid gap-3 md:grid-cols-2">
            <div>
                <x-input-label for="name" :value="__('Full Name')" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="username" :value="__('Username or Email')" />
                <x-text-input id="username" name="username" type="text" class="mt-1 block w-full font-mono" :value="old('username', $user->username)" required autocomplete="username" inputmode="email" />
                <x-input-error class="mt-2" :messages="$errors->get('username')" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email Address')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="email" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div>
                        <p class="mt-2 text-sm text-slate-800">
                            {{ __('Your email address is unverified.') }}

                            <button form="send-verification" class="rounded-md text-sm text-blue-700 underline hover:text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                {{ __('Click here to re-send the verification email.') }}
                            </button>
                        </p>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 text-sm font-medium text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <div>
                <x-input-label for="phone" :value="__('Contact Number')" />
                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $user->phone)" autocomplete="tel" />
                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
            </div>
        </div>

        @if ($isSupervisor)
            <div class="border-t border-blue-100 pt-4">
                <h3 class="text-sm font-semibold text-blue-950">Office Information</h3>
                <dl class="mt-3 grid gap-3 md:grid-cols-2">
                    <div class="border-b border-blue-100 pb-2">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">Position</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">Head / Supervisor</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">Office</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">Security and Safety Services Office</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2 md:border-b-0 md:pb-0">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">Role in System</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $roleLabel }}</dd>
                    </div>
                    <div>
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">Account Status</dt>
                        <dd class="mt-1 text-sm font-semibold text-emerald-700">Active</dd>
                    </div>
                </dl>
            </div>
        @elseif ($isGuard && $guardProfile)
            <div class="border-t border-blue-100 pt-4">
                <h3 class="text-sm font-semibold text-blue-950">Guard Information</h3>
                <dl class="mt-3 grid gap-3 md:grid-cols-2">
                    <div class="border-b border-blue-100 pb-2">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">Position</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">Security Guard</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">Office</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">Security and Safety Services Office</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">Employee No.</dt>
                        <dd class="mt-1 font-mono text-sm font-semibold text-slate-800">{{ $guardProfile->employee_no }}</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">RFID UID</dt>
                        <dd class="mt-1 font-mono text-sm font-semibold text-slate-800">{{ $guardProfile->rfid_uid }}</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">Assigned Shift</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $guardProfile->shift ?: 'Unassigned' }}</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">Account Status</dt>
                        <dd class="mt-1 text-sm font-semibold {{ $guardProfile->status === 'active' ? 'text-emerald-700' : 'text-slate-600' }}">{{ ucfirst($guardProfile->status) }}</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2 md:border-b-0 md:pb-0">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">Face Reference</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $guardProfile->face_reference ?: 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400">Live Face Registration</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $hasProcessedFaceRegistration ? 'Completed' : 'Not registered' }}</dd>
                    </div>
                </dl>
            </div>
        @elseif ($isGuard)
            <div class="border-t border-blue-100 pt-4">
                <h3 class="text-sm font-semibold text-blue-950">Guard Information</h3>
                <p class="mt-2 text-xs text-amber-700">This account is not linked to a guard profile yet.</p>
            </div>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save Changes') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-slate-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
