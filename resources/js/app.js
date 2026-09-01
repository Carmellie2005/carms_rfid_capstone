import './bootstrap';

import Alpine from 'alpinejs';
import { Chart, registerables } from 'chart.js';
import * as faceapi from 'face-api.js';

Chart.register(...registerables);

window.Alpine = Alpine;
window.Chart = Chart;
window.faceapi = faceapi;

const FACE_MODEL_URL = '/models/face-api';
const LIVENESS_CHALLENGES = ['smile', 'turn'];
const BLINK_OPEN_THRESHOLD = 0.28;
const BLINK_CLOSED_THRESHOLD = 0.22;
const SMILE_RATIO_THRESHOLD = 0.38;
const SMILE_RATIO_DELTA = 0.035;
const TURN_HEAD_THRESHOLD = 0.16;
const LIVENESS_SCAN_DELAY = 120;
const LOCALHOST_HOSTNAMES = ['localhost', '127.0.0.1', '[::1]', '::1'];
let faceModelPromise = null;

function faceDetectorOptions() {
    return new faceapi.TinyFaceDetectorOptions({
        inputSize: 224,
        scoreThreshold: 0.5,
    });
}

async function loadFaceModels() {
    if (! faceModelPromise) {
        faceModelPromise = Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(FACE_MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(FACE_MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(FACE_MODEL_URL),
        ]);
    }

    await faceModelPromise;
}

async function descriptorFromImage(image) {
    const detections = await faceapi
        .detectAllFaces(image, faceDetectorOptions())
        .withFaceLandmarks()
        .withFaceDescriptors();

    if (detections.length === 0) {
        throw new Error('No face was detected. Use a clear front-facing camera capture.');
    }

    if (detections.length > 1) {
        throw new Error('More than one face was detected. Capture one guard face only.');
    }

    return Array.from(detections[0].descriptor).map((value) => Number(value.toFixed(8)));
}

function descriptorToJson(descriptor) {
    return Array.isArray(descriptor) ? JSON.stringify(descriptor) : '';
}

function imageFromDataUrl(dataUrl) {
    return new Promise((resolve, reject) => {
        const image = new Image();

        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('The captured image could not be read.'));
        image.src = dataUrl;
    });
}

function isLocalhostHostname(hostname = window.location.hostname) {
    return LOCALHOST_HOSTNAMES.includes(hostname);
}

function canUseLiveCameraPreview() {
    return Boolean(window.isSecureContext || isLocalhostHostname());
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function cameraAccessMessage(error = null, insecureFallbackMessage = null) {
    const name = error?.name || '';

    if (! canUseLiveCameraPreview()) {
        return insecureFallbackMessage || 'Live camera preview needs HTTPS on phones. Tap Take Photo to use the phone camera.';
    }

    if (['NotAllowedError', 'PermissionDeniedError', 'SecurityError'].includes(name)) {
        return 'Camera permission was blocked. Allow camera access in the browser, or use Take Photo.';
    }

    if (['NotFoundError', 'DevicesNotFoundError'].includes(name)) {
        return 'No camera was found on this device.';
    }

    if (['NotReadableError', 'TrackStartError'].includes(name)) {
        return 'The camera is already being used by another app.';
    }

    return error?.message || 'Camera permission was denied or unavailable.';
}

async function dataUrlFromImageFile(file, options = {}) {
    const { maxSize = 1280, quality = 0.82 } = options;

    if (! file || (file.type && ! file.type.startsWith('image/'))) {
        throw new Error('Choose a valid image file.');
    }

    const source = await new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(new Error('The selected image could not be read.'));
        reader.readAsDataURL(file);
    });

    const image = await imageFromDataUrl(source);
    const width = image.naturalWidth || image.width;
    const height = image.naturalHeight || image.height;
    const scale = Math.min(1, maxSize / Math.max(width, height));
    const canvas = document.createElement('canvas');

    canvas.width = Math.max(1, Math.round(width * scale));
    canvas.height = Math.max(1, Math.round(height * scale));

    const context = canvas.getContext('2d');

    if (! context) {
        throw new Error('The selected image could not be prepared.');
    }

    context.drawImage(image, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/jpeg', quality);
}

function pointDistance(first, second) {
    const x = Number(first.x) - Number(second.x);
    const y = Number(first.y) - Number(second.y);

    return Math.sqrt((x * x) + (y * y));
}

function eyeAspectRatio(eye) {
    if (! Array.isArray(eye) || eye.length < 6) {
        return null;
    }

    const horizontal = pointDistance(eye[0], eye[3]);

    if (horizontal === 0) {
        return null;
    }

    const vertical = pointDistance(eye[1], eye[5]) + pointDistance(eye[2], eye[4]);

    return vertical / (2 * horizontal);
}

function averageEyeAspectRatio(landmarks) {
    const leftEyeRatio = eyeAspectRatio(landmarks.getLeftEye());
    const rightEyeRatio = eyeAspectRatio(landmarks.getRightEye());

    if (leftEyeRatio === null || rightEyeRatio === null) {
        return null;
    }

    return (leftEyeRatio + rightEyeRatio) / 2;
}

function mouthWidthRatio(landmarks) {
    const mouth = landmarks.getMouth();
    const jaw = landmarks.getJawOutline();

    if (! Array.isArray(mouth) || mouth.length < 7 || ! Array.isArray(jaw) || jaw.length < 17) {
        return null;
    }

    const faceWidth = pointDistance(jaw[0], jaw[16]);

    if (faceWidth === 0) {
        return null;
    }

    return pointDistance(mouth[0], mouth[6]) / faceWidth;
}

function headTurnRatio(landmarks) {
    const nose = landmarks.getNose();
    const jaw = landmarks.getJawOutline();

    if (! Array.isArray(nose) || nose.length < 4 || ! Array.isArray(jaw) || jaw.length < 17) {
        return null;
    }

    const faceWidth = pointDistance(jaw[0], jaw[16]);

    if (faceWidth === 0) {
        return null;
    }

    const faceCenterX = (Number(jaw[0].x) + Number(jaw[16].x)) / 2;
    const noseTipX = Number(nose[3].x);

    return (noseTipX - faceCenterX) / faceWidth;
}

function randomLivenessChallenge() {
    return LIVENESS_CHALLENGES[Math.floor(Math.random() * LIVENESS_CHALLENGES.length)];
}

let deferredPwaInstallPrompt = null;
let serviceWorkerRegistrationPromise = null;
const pwaInstallPromptListeners = new Set();

function isPwaInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function canRegisterServiceWorker() {
    return 'serviceWorker' in navigator
        && (window.isSecureContext || isLocalhostHostname());
}

function registerServiceWorker() {
    if (! canRegisterServiceWorker()) {
        return Promise.resolve(null);
    }

    if (! serviceWorkerRegistrationPromise) {
        serviceWorkerRegistrationPromise = navigator.serviceWorker.register('/sw.js')
            .catch(() => null);
    }

    return serviceWorkerRegistrationPromise;
}

function notifyPwaInstallPromptListeners() {
    pwaInstallPromptListeners.forEach((listener) => listener(deferredPwaInstallPrompt));
}

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPwaInstallPrompt = event;
    notifyPwaInstallPromptListeners();
});

window.addEventListener('appinstalled', () => {
    deferredPwaInstallPrompt = null;
    notifyPwaInstallPromptListeners();
});

if (canRegisterServiceWorker()) {
    window.addEventListener('load', () => {
        registerServiceWorker();
    });
}

Alpine.data('pwaInstallPrompt', () => ({
    deferredPrompt: null,
    canInstall: false,
    installed: false,
    checkingInstall: true,
    serviceWorkerReady: false,
    message: '',
    promptListener: null,

    init() {
        this.installed = isPwaInstalled();
        this.syncInstallPrompt();

        this.promptListener = () => {
            this.syncInstallPrompt();
        };

        pwaInstallPromptListeners.add(this.promptListener);

        registerServiceWorker().then((registration) => {
            this.serviceWorkerReady = Boolean(registration || navigator.serviceWorker?.controller);
            this.checkingInstall = false;
        });

        setTimeout(() => {
            this.checkingInstall = false;
        }, 3000);

        window.addEventListener('appinstalled', () => {
            this.installed = true;
            this.canInstall = false;
            this.deferredPrompt = null;
            this.message = 'App installed.';
        });
    },

    destroy() {
        if (this.promptListener) {
            pwaInstallPromptListeners.delete(this.promptListener);
        }
    },

    syncInstallPrompt() {
        this.deferredPrompt = deferredPwaInstallPrompt;
        this.canInstall = Boolean(this.deferredPrompt) && ! this.installed;

        if (this.canInstall) {
            this.message = '';
        }
    },

    isLocalhost() {
        return isLocalhostHostname();
    },

    isIos() {
        return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
    },

    unavailableMessage() {
        if (this.isIos()) {
            return 'On iPhone, use Share then Add to Home Screen.';
        }

        if (! window.isSecureContext && ! this.isLocalhost()) {
            return 'Install needs HTTPS. Local Wi-Fi IP testing can still use the browser menu then Add to Home screen.';
        }

        if (this.checkingInstall || ! this.serviceWorkerReady) {
            return 'Preparing install. Wait a few seconds, then tap Install Now again.';
        }

        return 'Use Chrome or Edge, then open the browser menu and choose Install app or Add to Home screen.';
    },

    installLabel() {
        if (this.installed) {
            return 'Installed';
        }

        if (this.checkingInstall && ! this.canInstall) {
            return 'Preparing Install';
        }

        return 'Install Now';
    },

    async install() {
        await registerServiceWorker();

        this.installed = isPwaInstalled();
        this.syncInstallPrompt();

        if (this.installed) {
            this.message = 'App already installed.';
            return;
        }

        if (! this.deferredPrompt) {
            this.message = this.unavailableMessage();
            return;
        }

        const prompt = this.deferredPrompt;

        prompt.prompt();

        const choice = await prompt.userChoice.catch(() => ({ outcome: 'dismissed' }));

        if (choice.outcome === 'accepted') {
            this.message = 'Installing app...';
        } else {
            this.message = 'Installation cancelled.';
        }

        if (deferredPwaInstallPrompt === prompt) {
            deferredPwaInstallPrompt = null;
            notifyPwaInstallPromptListeners();
        }

        this.deferredPrompt = null;
        this.canInstall = false;
    },
}));

Alpine.data('guardFaceForm', (config = {}) => ({
    liveRegistration: Boolean(config.liveRegistration),
    liveCapture: '',
    liveDescriptor: null,
    liveProcessing: false,
    registrationCameraOpen: false,
    registrationCameraStream: null,
    livenessPassed: false,
    livenessChecking: false,
    livenessStatus: 'idle',
    livenessChallenge: '',
    livenessMessage: '',
    livenessCheckTimer: null,
    livenessBlinkReady: false,
    livenessBlinkClosed: false,
    livenessOpenFrames: 0,
    livenessClosedFrames: 0,
    livenessSmileBaseline: null,
    livenessSmileFrames: 0,
    livenessTurnFrames: 0,
    descriptorMessage: '',
    descriptorError: '',

    boot() {
        if (this.liveRegistration) {
            this.descriptorMessage = 'Open the camera when the guard is ready to register.';
        }
    },

    descriptorPayload(descriptor) {
        return descriptorToJson(descriptor);
    },

    livenessChallengeLabel() {
        return {
            photo: 'Phone camera photo',
            smile: 'Smile',
            turn: 'Turn head slightly',
        }[this.livenessChallenge] || 'Smile';
    },

    livenessChallengeBadge() {
        if (this.livenessPassed) {
            return 'Liveness confirmed';
        }

        if (this.livenessStatus === 'align') {
            return 'Align face';
        }

        if (this.livenessStatus === 'face') {
            return 'Face detected';
        }

        return this.livenessChallengeLabel();
    },

    async openRegistrationCamera() {
        this.stopRegistrationCamera();
        this.descriptorError = '';
        this.liveCapture = '';
        this.liveDescriptor = null;
        this.resetRegistrationLiveness();

        if (! canUseLiveCameraPreview() || ! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia) {
            this.descriptorMessage = 'Live preview is blocked on this connection. Taking a phone camera photo instead.';
            this.$refs.registrationPhotoInput?.click();
            return;
        }

        try {
            this.registrationCameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user' },
                audio: false,
            });
            this.$refs.registrationVideo.srcObject = this.registrationCameraStream;
            this.registrationCameraOpen = true;
            this.descriptorMessage = 'Loading liveness check...';
            await loadFaceModels();
            this.startRegistrationLivenessCheck();
        } catch (error) {
            this.descriptorError = cameraAccessMessage(error, 'Live face registration needs HTTPS on phones. Use Take Photo, or open the system through HTTPS.');
        }
    },

    async captureRegistrationFace() {
        const video = this.$refs.registrationVideo;
        const canvas = this.$refs.registrationCanvas;

        if (! this.livenessPassed) {
            this.descriptorError = 'Complete the random liveness challenge before capturing.';
            return;
        }

        if (! video || ! video.videoWidth) {
            this.descriptorError = 'Open the camera before capturing.';
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        this.liveCapture = canvas.toDataURL('image/jpeg', 0.85);
        this.liveDescriptor = null;
        this.liveProcessing = true;
        this.descriptorError = '';
        this.descriptorMessage = 'Processing live face capture...';
        this.stopRegistrationCamera();

        try {
            await loadFaceModels();

            const image = await imageFromDataUrl(this.liveCapture);
            this.liveDescriptor = await descriptorFromImage(image);
            this.descriptorMessage = 'Live face data is ready. Save Changes to finish registration.';
        } catch (error) {
            this.descriptorError = error.message || 'Live face data could not be generated.';
        } finally {
            this.liveProcessing = false;
        }
    },

    openRegistrationPhotoCapture() {
        if (this.liveProcessing) {
            return;
        }

        this.stopRegistrationCamera();
        this.descriptorError = '';
        this.$refs.registrationPhotoInput?.click();
    },

    async useRegistrationCaptureFile(event) {
        const file = event.target.files?.[0];

        if (! file || this.liveProcessing) {
            return;
        }

        this.stopRegistrationCamera();
        this.liveCapture = '';
        this.liveDescriptor = null;
        this.liveProcessing = true;
        this.descriptorError = '';
        this.resetRegistrationLiveness();
        this.livenessPassed = true;
        this.livenessStatus = 'complete';
        this.livenessChallenge = 'photo';
        this.descriptorMessage = 'Processing phone camera face photo...';

        try {
            this.liveCapture = await dataUrlFromImageFile(file, { maxSize: 1280, quality: 0.85 });
            await loadFaceModels();

            const image = await imageFromDataUrl(this.liveCapture);
            this.liveDescriptor = await descriptorFromImage(image);
            this.descriptorMessage = 'Face data is ready. Save Changes to finish registration.';
        } catch (error) {
            this.liveCapture = '';
            this.liveDescriptor = null;
            this.resetRegistrationLiveness();
            this.descriptorError = error.message || 'Face data could not be generated from the photo.';
            this.descriptorMessage = 'Take a clear front-facing photo and try again.';
        } finally {
            this.liveProcessing = false;

            if (event.target) {
                event.target.value = '';
            }
        }
    },

    retakeRegistrationFace() {
        this.liveCapture = '';
        this.liveDescriptor = null;
        this.descriptorError = '';
        this.resetRegistrationLiveness();
        this.descriptorMessage = 'Open the camera when the guard is ready to register.';
    },

    resetRegistrationLiveness() {
        this.stopRegistrationLivenessCheck();
        this.livenessPassed = false;
        this.livenessStatus = 'idle';
        this.livenessChallenge = '';
        this.livenessMessage = '';
        this.resetLivenessActionState();
    },

    resetLivenessActionState() {
        this.livenessBlinkReady = false;
        this.livenessBlinkClosed = false;
        this.livenessOpenFrames = 0;
        this.livenessClosedFrames = 0;
        this.livenessSmileBaseline = null;
        this.livenessSmileFrames = 0;
        this.livenessTurnFrames = 0;
    },

    startRegistrationLivenessCheck() {
        this.stopRegistrationLivenessCheck();
        this.livenessChallenge = randomLivenessChallenge();
        this.resetLivenessActionState();
        this.livenessChecking = true;
        this.livenessStatus = 'align';
        this.livenessMessage = 'Position your face inside the guide for the random challenge.';
        this.descriptorMessage = this.livenessMessage;
        this.runRegistrationLivenessCheck();
    },

    async runRegistrationLivenessCheck() {
        if (! this.registrationCameraOpen || this.liveCapture || this.livenessPassed) {
            this.stopRegistrationLivenessCheck();
            return;
        }

        const video = this.$refs.registrationVideo;

        if (! video || ! video.videoWidth) {
            this.scheduleRegistrationLivenessCheck();
            return;
        }

        try {
            const detection = await faceapi
                .detectSingleFace(video, faceDetectorOptions())
                .withFaceLandmarks();

            if (! detection) {
                this.livenessStatus = 'align';
                this.livenessMessage = 'Position your face inside the guide for the random challenge.';
                this.descriptorMessage = this.livenessMessage;
                this.resetLivenessActionState();
                return;
            }

            this.runSelectedLivenessChallenge(detection.landmarks);
        } catch (error) {
            this.livenessStatus = 'align';
            this.livenessMessage = 'Liveness check is still scanning.';
            this.descriptorMessage = this.livenessMessage;
        } finally {
            if (this.registrationCameraOpen && ! this.liveCapture && ! this.livenessPassed) {
                this.scheduleRegistrationLivenessCheck();
            }
        }
    },

    runSelectedLivenessChallenge(landmarks) {
        if (this.livenessChallenge === 'turn') {
            this.runTurnChallenge(landmarks);
            return;
        }

        this.runSmileChallenge(landmarks);
    },

    runBlinkChallenge(landmarks) {
        const eyeRatio = averageEyeAspectRatio(landmarks);

        if (eyeRatio === null) {
            this.livenessStatus = 'face';
            this.livenessMessage = 'Keep your eyes visible to the camera.';
            this.descriptorMessage = this.livenessMessage;
            return;
        }

        if (eyeRatio >= BLINK_OPEN_THRESHOLD) {
            this.livenessOpenFrames += 1;
            this.livenessClosedFrames = 0;

            if (this.livenessBlinkClosed) {
                this.markRegistrationLivenessPassed();
                return;
            }

            if (this.livenessOpenFrames >= 2) {
                this.livenessBlinkReady = true;
                this.livenessStatus = 'blink';
                this.livenessMessage = 'Challenge: blink once.';
                this.descriptorMessage = this.livenessMessage;
                return;
            }

            this.livenessStatus = 'face';
            this.livenessMessage = 'Face detected. Keep looking at the camera.';
            this.descriptorMessage = this.livenessMessage;
            return;
        }

        if (eyeRatio <= BLINK_CLOSED_THRESHOLD && this.livenessBlinkReady) {
            this.livenessClosedFrames += 1;

            if (this.livenessClosedFrames >= 1) {
                this.livenessBlinkClosed = true;
                this.livenessStatus = 'blink';
                this.livenessMessage = 'Blink detected. Open your eyes.';
                this.descriptorMessage = this.livenessMessage;
                return;
            }
        }

        this.livenessStatus = this.livenessBlinkReady ? 'blink' : 'face';
        this.livenessMessage = this.livenessBlinkReady
            ? 'Challenge: blink once.'
            : 'Face detected. Keep looking at the camera.';
        this.descriptorMessage = this.livenessMessage;
    },

    runSmileChallenge(landmarks) {
        const ratio = mouthWidthRatio(landmarks);

        if (ratio === null) {
            this.livenessStatus = 'face';
            this.livenessMessage = 'Keep your mouth visible to the camera.';
            this.descriptorMessage = this.livenessMessage;
            return;
        }

        this.livenessSmileBaseline = this.livenessSmileBaseline === null
            ? ratio
            : Math.min(this.livenessSmileBaseline, ratio);

        const smiled = ratio >= SMILE_RATIO_THRESHOLD
            || ratio >= this.livenessSmileBaseline + SMILE_RATIO_DELTA;

        if (smiled) {
            this.livenessSmileFrames += 1;

            if (this.livenessSmileFrames >= 2) {
                this.markRegistrationLivenessPassed();
                return;
            }
        } else {
            this.livenessSmileFrames = 0;
        }

        this.livenessStatus = 'smile';
        this.livenessMessage = 'Challenge: smile.';
        this.descriptorMessage = this.livenessMessage;
    },

    runTurnChallenge(landmarks) {
        const ratio = headTurnRatio(landmarks);

        if (ratio === null) {
            this.livenessStatus = 'face';
            this.livenessMessage = 'Keep your whole face visible to the camera.';
            this.descriptorMessage = this.livenessMessage;
            return;
        }

        if (Math.abs(ratio) >= TURN_HEAD_THRESHOLD) {
            this.livenessTurnFrames += 1;

            if (this.livenessTurnFrames >= 2) {
                this.markRegistrationLivenessPassed();
                return;
            }
        } else {
            this.livenessTurnFrames = 0;
        }

        this.livenessStatus = 'turn';
        this.livenessMessage = 'Challenge: turn your head slightly left or right.';
        this.descriptorMessage = this.livenessMessage;
    },

    scheduleRegistrationLivenessCheck() {
        this.stopRegistrationLivenessCheck();
        this.livenessChecking = true;
        this.livenessCheckTimer = setTimeout(() => this.runRegistrationLivenessCheck(), LIVENESS_SCAN_DELAY);
    },

    stopRegistrationLivenessCheck() {
        if (this.livenessCheckTimer) {
            clearTimeout(this.livenessCheckTimer);
            this.livenessCheckTimer = null;
        }

        this.livenessChecking = false;
    },

    markRegistrationLivenessPassed() {
        this.stopRegistrationLivenessCheck();
        this.livenessPassed = true;
        this.livenessStatus = 'complete';
        this.livenessMessage = `${this.livenessChallengeLabel()} confirmed. Capture is unlocked.`;
        this.descriptorMessage = this.livenessMessage;
    },

    stopRegistrationCamera() {
        this.stopRegistrationLivenessCheck();

        if (this.registrationCameraStream) {
            this.registrationCameraStream.getTracks().forEach((track) => track.stop());
            this.registrationCameraStream = null;
        }

        this.registrationCameraOpen = false;
    },

    handleSubmit(event) {
        if (this.liveProcessing) {
            event.preventDefault();
            this.descriptorError = 'Please wait until face data processing is finished.';
            return;
        }

        if (this.liveCapture && ! this.livenessPassed) {
            event.preventDefault();
            this.descriptorError = 'Complete the random liveness challenge before saving face registration.';
            return;
        }

        if (this.liveCapture && ! Array.isArray(this.liveDescriptor)) {
            event.preventDefault();
            this.descriptorError = 'Face data is not ready. Capture a clear face and wait for processing to finish.';
            return;
        }

        this.stopRegistrationCamera();
    },
}));

Alpine.data('patrolScan', (config = {}) => ({
    incident: config.incident || false,
    incidentImageCount: 0,
    incidentImageError: '',
    pendingScan: config.pendingScan || null,
    pendingScanUrl: config.pendingScanUrl,
    faceVerifyUrl: config.faceVerifyUrl,
    guardName: config.guardName || '',
    guardEmployeeNo: config.guardEmployeeNo || '',
    patrolLogId: config.patrolLogId || '',
    scanMessage: config.scanMessage || 'Waiting for your ESP32 checkpoint scan.',
    patrolScheduleOpen: config.patrolScheduleOpen ?? true,
    patrolScheduleTestingMode: config.patrolScheduleTestingMode || false,
    patrolScheduleMessage: config.patrolScheduleMessage || 'Guard patrol scanning is currently closed.',
    patrolTestingNotice: config.patrolTestingNotice || '',
    pollingTimer: null,
    faceModalOpen: false,
    checklistModalOpen: config.openChecklist || false,
    faceVerified: config.openChecklist || false,
    cameraOpen: false,
    cameraStream: null,
    faceCapture: config.faceCapture || '',
    capturedDescriptor: config.capturedDescriptor || '',
    cameraError: '',
    cameraOpening: false,
    capturingFace: false,
    faceModelLoading: false,
    verificationBusy: false,
    verificationMessage: '',
    matchDistance: config.matchDistance || null,
    submittingPatrol: false,

    boot() {
        this.verificationMessage = 'Capture the guard face, then verify it with the server before opening the checklist.';

        if (! this.patrolScheduleOpen) {
            this.scanMessage = this.patrolScheduleMessage;
            this.verificationMessage = this.patrolScheduleMessage;
            return;
        }

        if (! this.pendingScan) {
            this.startPolling();
        }
    },

    startPolling() {
        if (! this.patrolScheduleOpen) {
            return;
        }

        this.fetchPendingScan();
        this.pollingTimer = setInterval(() => this.fetchPendingScan(), 3000);
    },

    async fetchPendingScan() {
        if (this.pendingScan || this.faceVerified) {
            return;
        }

        try {
            const response = await fetch(this.pendingScanUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (! response.ok) {
                return;
            }

            const data = await response.json();

            if (data.pending && data.patrol_log) {
                this.pendingScan = data.patrol_log;
                this.patrolLogId = data.patrol_log.id;
                this.scanMessage = 'RFID scan received successfully. Continue to face verification.';
                clearInterval(this.pollingTimer);
            } else if (data.message) {
                this.scanMessage = data.message;
            }
        } catch (error) {
            this.scanMessage = 'Waiting for RFID scan. Check Wi-Fi if this takes too long.';
        }
    },

    async openFaceModal() {
        if (! this.patrolScheduleOpen) {
            this.scanMessage = this.patrolScheduleMessage;
            return;
        }

        if (! this.pendingScan) {
            this.scanMessage = 'Scan your RFID card at the checkpoint reader first.';
            return;
        }

        this.faceModalOpen = true;
        this.cameraError = '';

        this.faceModelLoading = true;

        try {
            this.verificationMessage = 'Loading face verification model...';
            await loadFaceModels();
            this.verificationMessage = 'Capture the guard face, then verify it with the server.';
        } catch (error) {
            this.cameraError = 'Face verification model could not be loaded.';
        } finally {
            this.faceModelLoading = false;
        }
    },

    async verifyCapturedFace() {
        if (! this.faceCapture) {
            this.cameraError = 'Capture the guard face before verifying.';
            return;
        }

        if (! this.patrolLogId) {
            this.cameraError = 'Scan your RFID card at the checkpoint reader first.';
            return;
        }

        if (! this.faceVerifyUrl) {
            this.cameraError = 'Face verification endpoint is not available.';
            return;
        }

        this.cameraError = '';
        this.verificationBusy = true;
        this.verificationMessage = 'Preparing face data...';

        try {
            await loadFaceModels();

            const image = await imageFromDataUrl(this.faceCapture);
            const descriptor = await descriptorFromImage(image);

            this.capturedDescriptor = descriptorToJson(descriptor);
            this.verificationMessage = 'Checking face with server...';

            const response = await fetch(this.faceVerifyUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    patrol_log_id: this.patrolLogId,
                    face_capture: this.faceCapture,
                    captured_descriptor: this.capturedDescriptor,
                }),
            });

            const data = await response.json().catch(() => ({}));
            const distance = Number(data.match_distance);

            this.matchDistance = Number.isFinite(distance) ? Number(distance.toFixed(6)) : null;

            if (! response.ok || ! data.verified) {
                this.faceVerified = false;
                this.cameraError = data.message || 'Face verification failed.';
                this.verificationMessage = 'Verification failed. Retake the photo and try again.';
                return;
            }

            this.faceVerified = true;
            this.faceModalOpen = false;
            this.stopCamera();
            this.checklistModalOpen = false;
            this.verificationMessage = this.matchDistance !== null
                ? `Face verified successfully. Match distance: ${this.matchDistance}.`
                : (data.message || 'Face verified successfully.');
            this.scanMessage = 'Face verified successfully. Continue to the checklist when ready.';
        } catch (error) {
            this.cameraError = error.message || 'Face verification failed.';
            this.verificationMessage = 'Verification failed. Retake the photo.';
        } finally {
            this.verificationBusy = false;
        }
    },

    closeFaceModal() {
        this.faceModalOpen = false;
        this.stopCamera();
    },

    openFacePhotoCapture() {
        if (this.faceModelLoading || this.cameraOpening || this.capturingFace || this.verificationBusy || this.submittingPatrol) {
            return;
        }

        this.stopCamera();
        this.cameraError = '';
        this.$refs.faceCaptureInput?.click();
    },

    async useFaceCaptureFile(event) {
        const file = event.target.files?.[0];

        if (! file || this.capturingFace || this.verificationBusy) {
            return;
        }

        this.capturingFace = true;
        this.cameraError = '';
        this.verificationMessage = 'Preparing phone camera photo...';

        try {
            this.stopCamera();

            const dataUrl = await dataUrlFromImageFile(file, { maxSize: 1280, quality: 0.82 });
            this.setFaceCapture(
                dataUrl,
                'Face photo captured successfully. Press Verify Face to check it.',
            );
        } catch (error) {
            this.cameraError = error.message || 'Face photo could not be prepared.';
            this.verificationMessage = 'Retake the photo and try again.';
        } finally {
            this.capturingFace = false;

            if (event.target) {
                event.target.value = '';
            }
        }
    },

    async openCamera() {
        if (this.cameraOpening || this.verificationBusy) {
            return;
        }

        this.cameraError = '';
        this.cameraOpening = true;
        this.verificationMessage = 'Opening camera...';

        if (! canUseLiveCameraPreview()) {
            this.cameraError = cameraAccessMessage();
            this.verificationMessage = 'Opening phone camera capture...';
            this.cameraOpening = false;
            this.$refs.faceCaptureInput?.click();
            return;
        }

        if (! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia) {
            this.cameraError = 'Live camera preview is not available in this browser. Use Take Photo instead.';
            this.cameraOpening = false;
            return;
        }

        try {
            this.stopCamera();
            this.cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user' },
                audio: false,
            });
            this.$refs.faceVideo.srcObject = this.cameraStream;
            this.cameraOpen = true;
            this.verificationMessage = 'Camera is ready. Position your face, then capture.';
        } catch (error) {
            this.cameraError = cameraAccessMessage(error);
            this.verificationMessage = 'Camera could not be opened.';
        } finally {
            this.cameraOpening = false;
        }
    },

    setFaceCapture(dataUrl, message) {
        this.faceCapture = dataUrl;
        this.capturedDescriptor = '';
        this.matchDistance = null;
        this.faceVerified = false;
        this.verificationMessage = message;
    },

    captureFace() {
        if (this.capturingFace || this.verificationBusy) {
            return;
        }

        const video = this.$refs.faceVideo;
        const canvas = this.$refs.faceCanvas;

        if (! video || ! video.videoWidth) {
            this.cameraError = 'Open the camera before capturing.';
            return;
        }

        this.capturingFace = true;
        this.cameraError = '';
        this.verificationMessage = 'Capturing face...';

        try {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            this.setFaceCapture(
                canvas.toDataURL('image/jpeg', 0.8),
                'Face captured successfully. Press Verify Face to check it.',
            );
            this.stopCamera();
        } finally {
            this.capturingFace = false;
        }
    },

    retakeFace() {
        this.faceCapture = '';
        this.capturedDescriptor = '';
        this.matchDistance = null;
        this.faceVerified = false;
        this.cameraError = '';
        this.verificationMessage = 'Capture the guard face, then verify it with the server.';

        if (this.$refs.faceCaptureInput) {
            this.$refs.faceCaptureInput.value = '';
        }
    },

    continueToChecklist() {
        if (! this.faceVerified || this.submittingPatrol) {
            return;
        }

        this.checklistModalOpen = true;
        this.$nextTick(() => document.getElementById('area_secure')?.focus());
    },

    incidentFileCount(refName) {
        return this.$refs[refName]?.files?.length || 0;
    },

    updateIncidentImageCount(event = null) {
        let uploadCount = this.incidentFileCount('incidentUploadImages');
        let cameraCount = this.incidentFileCount('incidentCameraImages');
        let total = uploadCount + cameraCount;

        if (total > 3 && event?.target) {
            event.target.value = '';
            uploadCount = this.incidentFileCount('incidentUploadImages');
            cameraCount = this.incidentFileCount('incidentCameraImages');
            total = uploadCount + cameraCount;
            this.incidentImageError = 'Attach up to 3 incident images only.';
            this.incidentImageCount = total;

            return false;
        }

        this.incidentImageCount = total;

        if (this.incident && total === 0) {
            this.incidentImageError = 'Attach at least one incident image before submitting.';

            return false;
        }

        if (uploadCount === 1 && cameraCount === 0) {
            this.incidentImageError = 'Upload at least 2 images, or use Take Photo for one camera image.';

            return false;
        }

        this.incidentImageError = '';

        return true;
    },

    stopCamera() {
        if (this.cameraStream) {
            this.cameraStream.getTracks().forEach((track) => track.stop());
            this.cameraStream = null;
        }

        this.cameraOpen = false;
    },

    handleSubmit(event) {
        if (! this.patrolScheduleOpen) {
            event.preventDefault();
            this.verificationMessage = this.patrolScheduleMessage;
            return;
        }

        if (this.incident && ! this.updateIncidentImageCount()) {
            event.preventDefault();
            this.checklistModalOpen = true;
            return;
        }

        if (! this.patrolLogId || ! this.faceVerified) {
            event.preventDefault();
            this.verificationMessage = this.patrolLogId
                ? 'Verify the guard face before submitting.'
                : 'Wait for an RFID scan before submitting.';
            return;
        }

        this.submittingPatrol = true;
        this.verificationMessage = 'Submitting patrol record...';
        this.stopCamera();
    },
}));

Alpine.data('guardManagementPage', (config = {}) => ({
    createModalOpen: Boolean(config.createModalOpen),
    recordModalOpen: false,
    recordLoading: false,
    recordError: '',
    selectedGuard: null,
    recordStats: {},
    recordPatrols: [],
    recordIncidents: [],
    recordFaceAttempts: [],

    init() {
        this.updateBodyScrollLock();

        if (this.createModalOpen) {
            this.$nextTick(() => this.$refs.createGuardFirstField?.focus());
        }
    },

    openCreateGuardModal() {
        this.recordModalOpen = false;
        this.createModalOpen = true;
        this.updateBodyScrollLock();
        this.$nextTick(() => this.$refs.createGuardFirstField?.focus());
    },

    closeCreateGuardModal() {
        this.createModalOpen = false;
        this.updateBodyScrollLock();
    },

    async openGuardRecord(url) {
        if (! url) {
            return;
        }

        this.createModalOpen = false;
        this.recordModalOpen = true;
        this.recordLoading = true;
        this.recordError = '';
        this.selectedGuard = null;
        this.recordStats = {};
        this.recordPatrols = [];
        this.recordIncidents = [];
        this.recordFaceAttempts = [];
        this.updateBodyScrollLock();

        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json().catch(() => ({}));

            if (! response.ok) {
                throw new Error(data.message || 'Guard records could not be loaded.');
            }

            this.selectedGuard = data.guard || null;
            this.recordStats = data.stats || {};
            this.recordPatrols = data.patrol_logs || [];
            this.recordIncidents = data.incidents || [];
            this.recordFaceAttempts = data.face_attempts || [];
            this.$nextTick(() => this.$refs.recordCloseButton?.focus());
        } catch (error) {
            this.recordError = error.message || 'Guard records could not be loaded.';
        } finally {
            this.recordLoading = false;
        }
    },

    closeGuardRecord() {
        this.recordModalOpen = false;
        this.updateBodyScrollLock();
    },

    updateBodyScrollLock() {
        document.body.classList.toggle('overflow-y-hidden', this.createModalOpen || this.recordModalOpen);
    },

    guardRecordSubtitle() {
        if (! this.selectedGuard) {
            return '';
        }

        return [
            this.selectedGuard.employee_no || 'No employee number',
            this.selectedGuard.rfid_uid || 'No RFID UID',
        ].join(' / ');
    },

    badgeClass(value) {
        const status = String(value || '').toLowerCase();

        if (['active', 'valid', 'verified', 'completed', 'resolved'].includes(status)) {
            return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
        }

        if (['pending', 'pending_face', 'open', 'in_progress'].includes(status)) {
            return 'bg-blue-50 text-blue-700 ring-blue-200';
        }

        if (['suspicious', 'profile_incomplete', 'medium', 'high'].includes(status)) {
            return 'bg-amber-50 text-amber-700 ring-amber-200';
        }

        if (['failed', 'invalid', 'critical'].includes(status)) {
            return 'bg-red-50 text-red-700 ring-red-200';
        }

        return 'bg-slate-50 text-slate-600 ring-slate-200';
    },
}));

Alpine.data('checkpointManagementPage', (config = {}) => ({
    createModalOpen: Boolean(config.createModalOpen),

    init() {
        this.updateBodyScrollLock();

        if (this.createModalOpen) {
            this.$nextTick(() => this.$refs.createCheckpointFirstField?.focus());
        }
    },

    openCreateCheckpointModal() {
        this.createModalOpen = true;
        this.updateBodyScrollLock();
        this.$nextTick(() => this.$refs.createCheckpointFirstField?.focus());
    },

    closeCreateCheckpointModal() {
        this.createModalOpen = false;
        this.updateBodyScrollLock();
    },

    updateBodyScrollLock() {
        document.body.classList.toggle('overflow-y-hidden', this.createModalOpen);
    },
}));

Alpine.data('dashboardCharts', (analytics) => ({
    charts: [],

    render() {
        this.destroy();

        this.$nextTick(() => {
            this.createPatrolTrendChart();
            this.createScanStatusChart();
            this.createIncidentPriorityChart();
            this.createCheckpointActivityChart();
        });
    },

    destroy() {
        this.charts.forEach((chart) => chart.destroy());
        this.charts = [];
    },

    chartTextColor() {
        return document.documentElement.classList.contains('dark') ? '#cbd5e1' : '#475569';
    },

    gridColor() {
        return document.documentElement.classList.contains('dark') ? '#1e293b' : '#dbeafe';
    },

    baseOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: {
                    labels: {
                        color: this.chartTextColor(),
                        boxWidth: 12,
                        font: {
                            family: 'Figtree',
                        },
                    },
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 12,
                    titleFont: {
                        family: 'Figtree',
                    },
                    bodyFont: {
                        family: 'Figtree',
                    },
                },
            },
        };
    },

    axisOptions() {
        return {
            x: {
                grid: {
                    color: this.gridColor(),
                },
                ticks: {
                    color: this.chartTextColor(),
                    font: {
                        family: 'Figtree',
                    },
                },
            },
            y: {
                beginAtZero: true,
                precision: 0,
                grid: {
                    color: this.gridColor(),
                },
                ticks: {
                    color: this.chartTextColor(),
                    stepSize: 1,
                    font: {
                        family: 'Figtree',
                    },
                },
            },
        };
    },

    createPatrolTrendChart() {
        if (! this.$refs.patrolTrendChart) {
            return;
        }

        this.charts.push(new Chart(this.$refs.patrolTrendChart, {
            type: 'line',
            data: {
                labels: analytics.patrolTrend.labels,
                datasets: [{
                    label: 'Patrol scans',
                    data: analytics.patrolTrend.data,
                    borderColor: '#1d4ed8',
                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                    pointBackgroundColor: '#1d4ed8',
                    pointBorderColor: '#ffffff',
                    pointRadius: 4,
                    tension: 0.35,
                    fill: true,
                }],
            },
            options: {
                ...this.baseOptions(),
                scales: this.axisOptions(),
            },
        }));
    },

    createScanStatusChart() {
        if (! this.$refs.scanStatusChart) {
            return;
        }

        this.charts.push(new Chart(this.$refs.scanStatusChart, {
            type: 'doughnut',
            data: {
                labels: analytics.scanStatus.labels,
                datasets: [{
                    data: analytics.scanStatus.data,
                    backgroundColor: ['#059669', '#d97706', '#dc2626', '#7c3aed', '#f59e0b'],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                }],
            },
            options: {
                ...this.baseOptions(),
                cutout: '68%',
            },
        }));
    },

    createIncidentPriorityChart() {
        if (! this.$refs.incidentPriorityChart) {
            return;
        }

        this.charts.push(new Chart(this.$refs.incidentPriorityChart, {
            type: 'bar',
            data: {
                labels: analytics.incidentPriority.labels,
                datasets: [{
                    label: 'Incidents',
                    data: analytics.incidentPriority.data,
                    backgroundColor: ['#60a5fa', '#2563eb', '#f59e0b', '#dc2626'],
                    borderRadius: 6,
                }],
            },
            options: {
                ...this.baseOptions(),
                scales: this.axisOptions(),
            },
        }));
    },

    createCheckpointActivityChart() {
        if (! this.$refs.checkpointActivityChart) {
            return;
        }

        this.charts.push(new Chart(this.$refs.checkpointActivityChart, {
            type: 'bar',
            data: {
                labels: analytics.checkpointActivity.labels,
                datasets: [{
                    label: 'Valid patrols',
                    data: analytics.checkpointActivity.data,
                    backgroundColor: '#2563eb',
                    borderRadius: 6,
                }],
            },
            options: {
                ...this.baseOptions(),
                indexAxis: 'y',
                scales: this.axisOptions(),
            },
        }));
    },
}));

Alpine.start();
