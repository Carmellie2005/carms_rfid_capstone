import './bootstrap';

import Alpine from 'alpinejs';
import { Chart, registerables } from 'chart.js';
import * as faceapi from 'face-api.js';

Chart.register(...registerables);

window.Alpine = Alpine;
window.Chart = Chart;
window.faceapi = faceapi;

const FACE_MODEL_URL = '/models/face-api';
const REGISTRATION_FACE_SAMPLE_GUIDES = [
    {
        key: 'front_neutral',
        label: 'Front neutral',
        challenge: 'center',
        instruction: 'Look straight at the camera with a relaxed face.',
    },
    {
        key: 'front_smile',
        label: 'Front smile',
        challenge: 'smile',
        instruction: 'Look straight at the camera and smile.',
    },
    {
        key: 'slight_left',
        label: 'Slight left turn',
        challenge: 'turn-left',
        instruction: 'Turn your head slightly to the left.',
    },
    {
        key: 'slight_right',
        label: 'Slight right turn',
        challenge: 'turn-right',
        instruction: 'Turn your head slightly to the right.',
    },
    {
        key: 'low_light',
        label: 'Normal or low-light',
        challenge: 'center',
        instruction: 'Use the lighting normally used during patrol, then look straight at the camera.',
    },
];
const SMILE_RATIO_THRESHOLD = 0.38;
const SMILE_RATIO_DELTA = 0.035;
const TURN_HEAD_THRESHOLD = 0.16;
const LIVENESS_SCAN_DELAY = 120;
const PWA_LAUNCH_SPLASH_MS = 1400;
const PWA_LAUNCH_SPLASH_STORAGE_KEY = 'slsu-pwa-launch-splash-shown';
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
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';
}

function updateCsrfToken(token) {
    if (! token) {
        return;
    }

    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
    document.querySelectorAll('input[name="_token"]').forEach((input) => {
        input.value = token;
    });
}

async function refreshCsrfToken(refreshUrl = '/csrf-token') {
    let response;

    try {
        response = await fetch(refreshUrl, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    } catch (error) {
        return false;
    }

    const data = await response.json().catch(() => ({}));

    if (! response.ok || ! data.token) {
        return false;
    }

    updateCsrfToken(data.token);

    return true;
}

function csrfJsonHeaders() {
    const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const token = csrfToken();

    if (token) {
        headers['X-CSRF-TOKEN'] = token;
    }

    return headers;
}

function cameraAccessMessage(error = null, insecureFallbackMessage = null, permissionFallbackMessage = null) {
    const name = error?.name || '';

    if (! canUseLiveCameraPreview()) {
        return insecureFallbackMessage || 'Live camera preview needs HTTPS on phones. Open this system through HTTPS and try again.';
    }

    if (['NotAllowedError', 'PermissionDeniedError', 'SecurityError'].includes(name)) {
        return permissionFallbackMessage || 'Camera permission was blocked. Allow camera access in the browser settings, then try again.';
    }

    if (['NotFoundError', 'DevicesNotFoundError'].includes(name)) {
        return 'No camera was found on this device.';
    }

    if (['NotReadableError', 'TrackStartError'].includes(name)) {
        return 'The camera is already being used by another app.';
    }

    return error?.message || 'Camera permission was denied or unavailable.';
}

function cameraTorchCapable(stream) {
    const [track] = stream?.getVideoTracks?.() || [];
    const capabilities = track?.getCapabilities?.() || {};

    return Boolean(capabilities.torch);
}

async function setCameraTorch(stream, enabled) {
    const [track] = stream?.getVideoTracks?.() || [];

    if (! track || typeof track.applyConstraints !== 'function' || ! cameraTorchCapable(stream)) {
        return false;
    }

    try {
        await track.applyConstraints({ advanced: [{ torch: Boolean(enabled) }] });

        return true;
    } catch (error) {
        return false;
    }
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

function livenessLabelFor(challenge) {
    return {
        center: 'Face centered',
        smile: 'Smile',
        turn: 'Turn head slightly',
        'turn-left': 'Turn head left',
        'turn-right': 'Turn head right',
    }[challenge] || 'Complete random challenge';
}

function registrationFaceSamples(labels = {}) {
    return REGISTRATION_FACE_SAMPLE_GUIDES.map((sample) => ({
        ...sample,
        label: labels[sample.key] || sample.label,
        capture: '',
        descriptor: null,
    }));
}

let deferredPwaInstallPrompt = null;
let serviceWorkerRegistrationPromise = null;
const pwaInstallPromptListeners = new Set();

function isPwaInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function shouldShowPwaLaunchSplash() {
    if (! isPwaInstalled()) {
        return false;
    }

    try {
        if (window.sessionStorage.getItem(PWA_LAUNCH_SPLASH_STORAGE_KEY)) {
            return false;
        }

        window.sessionStorage.setItem(PWA_LAUNCH_SPLASH_STORAGE_KEY, '1');
    } catch {
        return true;
    }

    return true;
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

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let index = 0; index < rawData.length; index += 1) {
        outputArray[index] = rawData.charCodeAt(index);
    }

    return outputArray;
}

async function pushJson(url, method = 'GET', payload = null) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: csrfJsonHeaders(),
        body: payload ? JSON.stringify(payload) : null,
    });

    if (! response.ok) {
        throw new Error('Push request failed.');
    }

    return response.json();
}

function pushContentEncoding() {
    const encodings = window.PushManager?.supportedContentEncodings;

    if (encodings && encodings.includes('aes128gcm')) {
        return 'aes128gcm';
    }

    return 'aesgcm';
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

Alpine.data('pwaLaunchSplash', () => ({
    visible: false,

    init() {
        if (! shouldShowPwaLaunchSplash()) {
            return;
        }

        this.visible = true;

        setTimeout(() => {
            this.visible = false;
        }, PWA_LAUNCH_SPLASH_MS);
    },
}));

Alpine.data('pushNotifications', (config = {}) => ({
    configUrl: config.configUrl,
    subscribeUrl: config.subscribeUrl,
    unsubscribeUrl: config.unsubscribeUrl,
    supported: false,
    configured: false,
    subscribed: false,
    busy: false,
    permission: 'default',
    publicKey: '',
    message: '',

    async init() {
        this.supported = 'Notification' in window
            && 'PushManager' in window
            && canRegisterServiceWorker();

        if (! this.supported) {
            this.message = 'Phone alerts are not supported on this browser.';
            return;
        }

        this.permission = window.Notification.permission;

        try {
            const pushConfig = await pushJson(this.configUrl);
            this.publicKey = pushConfig.publicKey || '';
            this.configured = Boolean(pushConfig.enabled && this.publicKey);

            if (! this.configured) {
                this.message = 'Phone alerts need server keys.';
                return;
            }

            const registration = await registerServiceWorker();
            const subscription = await registration?.pushManager.getSubscription();

            this.subscribed = Boolean(subscription);
            this.message = this.subscribed ? 'Phone alerts are active on this device.' : '';
        } catch {
            this.message = 'Phone alerts are unavailable right now.';
        }
    },

    disabled() {
        return this.busy || ! this.supported || ! this.configured || this.permission === 'denied';
    },

    buttonLabel() {
        if (this.busy) {
            return 'Updating...';
        }

        if (this.permission === 'denied') {
            return 'Phone alerts blocked';
        }

        if (this.subscribed) {
            return 'Phone alerts on';
        }

        return 'Enable phone alerts';
    },

    async toggle() {
        if (this.disabled()) {
            return;
        }

        if (this.subscribed) {
            await this.disable();
            return;
        }

        await this.enable();
    },

    async enable() {
        this.busy = true;
        this.message = '';

        try {
            const permission = await window.Notification.requestPermission();
            this.permission = permission;

            if (permission !== 'granted') {
                this.message = 'Notifications were not allowed.';
                return;
            }

            const registration = await registerServiceWorker();

            if (! registration) {
                this.message = 'Phone alerts are unavailable right now.';
                return;
            }

            let subscription = await registration.pushManager.getSubscription();

            if (! subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(this.publicKey),
                });
            }

            await pushJson(this.subscribeUrl, 'POST', {
                ...subscription.toJSON(),
                contentEncoding: pushContentEncoding(),
            });

            this.subscribed = true;
            this.message = 'Phone alerts are active on this device.';
        } catch {
            this.message = 'Could not enable phone alerts.';
        } finally {
            this.busy = false;
        }
    },

    async disable() {
        this.busy = true;
        this.message = '';

        try {
            const registration = await registerServiceWorker();
            const subscription = await registration?.pushManager.getSubscription();

            if (subscription) {
                await pushJson(this.unsubscribeUrl, 'DELETE', {
                    endpoint: subscription.endpoint,
                });

                await subscription.unsubscribe();
            }

            this.subscribed = false;
            this.message = 'Phone alerts are off on this device.';
        } catch {
            this.message = 'Could not turn off phone alerts.';
        } finally {
            this.busy = false;
        }
    },
}));

Alpine.data('pwaInstallPrompt', (config = {}) => ({
    appName: config.appName || 'BC Patrol',
    startUrl: config.startUrl || '/',
    deferredPrompt: null,
    canInstall: false,
    installed: false,
    checkingInstall: true,
    serviceWorkerReady: false,
    installState: 'idle',
    installModalOpen: false,
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
            this.installState = 'installed';
            this.installModalOpen = true;
            this.message = `${this.appName} installed successfully.`;
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

    isBusy() {
        return ['preparing', 'prompting', 'installing'].includes(this.installState);
    },

    isLocalhost() {
        return isLocalhostHostname();
    },

    canDismissInstallModal() {
        return this.installModalOpen && ! this.isBusy();
    },

    closeInstallModal() {
        if (! this.canDismissInstallModal()) {
            return;
        }

        this.installModalOpen = false;
    },

    installModalTitle() {
        if (this.installState === 'preparing') {
            return 'Preparing install';
        }

        if (this.installState === 'prompting') {
            return 'Confirm installation';
        }

        if (this.installState === 'installing') {
            return 'Installing...';
        }

        if (this.installState === 'installed') {
            return 'Installed successfully';
        }

        if (this.message === 'Installation cancelled.') {
            return 'Installation cancelled';
        }

        return 'Install not available';
    },

    installModalMessage() {
        if (this.installState === 'preparing') {
            return 'Preparing the app for installation.';
        }

        if (this.installState === 'prompting') {
            return 'Confirm the install request in your browser.';
        }

        if (this.installState === 'installing') {
            return 'Please wait while the app is installed.';
        }

        if (this.installState === 'installed') {
            return `${this.appName} is ready. Open the app login to continue.`;
        }

        return this.message || this.unavailableMessage();
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
        if (this.installState === 'preparing' || (this.checkingInstall && ! this.canInstall)) {
            return 'Preparing Install';
        }

        if (this.installState === 'prompting') {
            return 'Confirm Install';
        }

        if (this.installState === 'installing') {
            return 'Installing...';
        }

        if (this.installed || this.installState === 'installed') {
            return 'Open App';
        }

        return 'Install Now';
    },

    openApp() {
        window.location.href = this.startUrl;
    },

    async install() {
        if (this.isBusy()) {
            return;
        }

        this.installModalOpen = true;

        if (this.installed || this.installState === 'installed') {
            this.installState = 'installed';
            this.message = `${this.appName} is ready.`;
            return;
        }

        this.installState = 'preparing';
        this.message = 'Preparing install...';
        await registerServiceWorker();

        this.installed = isPwaInstalled();
        this.syncInstallPrompt();

        if (this.installed) {
            this.installState = 'installed';
            this.message = `${this.appName} is ready.`;
            return;
        }

        if (! this.deferredPrompt) {
            this.installState = 'idle';
            this.message = this.unavailableMessage();
            return;
        }

        const prompt = this.deferredPrompt;

        this.installState = 'prompting';
        this.message = 'Confirm the install request in your browser.';

        try {
            prompt.prompt();
        } catch {
            this.installState = 'idle';
            this.message = this.unavailableMessage();
            return;
        }

        const choice = await prompt.userChoice.catch(() => ({ outcome: 'dismissed' }));

        if (choice.outcome === 'accepted') {
            this.installState = 'installing';
            this.message = `Installing ${this.appName}...`;
        } else {
            this.installState = 'idle';
            this.message = 'Installation cancelled.';
        }

        if (deferredPwaInstallPrompt === prompt) {
            deferredPwaInstallPrompt = null;
            notifyPwaInstallPromptListeners();
        }

        this.deferredPrompt = null;
        this.canInstall = false;

        if (choice.outcome === 'accepted') {
            setTimeout(() => {
                if (! this.installed) {
                    this.installed = true;
                    this.installState = 'installed';
                    this.message = `${this.appName} installed successfully.`;
                }
            }, 1200);
        }
    },
}));

Alpine.data('guardFaceForm', (config = {}) => ({
    liveRegistration: Boolean(config.liveRegistration),
    registrationModalOpen: Boolean(config.openRegistration),
    faceSamples: registrationFaceSamples(config.registrationSampleTypes),
    requiredFaceSampleCount: Number(config.requiredFaceSampleCount) || REGISTRATION_FACE_SAMPLE_GUIDES.length,
    currentSampleIndex: 0,
    liveCapture: '',
    liveDescriptor: null,
    liveProcessing: false,
    registrationCameraOpen: false,
    registrationCameraStream: null,
    registrationLightAssist: false,
    registrationTorchSupported: false,
    registrationTorchActive: false,
    registrationLightMessage: '',
    livenessPassed: false,
    livenessChecking: false,
    livenessStatus: 'idle',
    livenessChallenge: 'center',
    livenessMessage: '',
    livenessCheckTimer: null,
    livenessSmileBaseline: null,
    livenessSmileFrames: 0,
    livenessTurnFrames: 0,
    stableRegistrationFaceFrames: 0,
    requiredStableRegistrationFaceFrames: 4,
    descriptorMessage: '',
    descriptorError: '',

    boot() {
        if (this.liveRegistration) {
            this.syncCurrentSampleState();
            this.descriptorMessage = `Capture ${this.requiredFaceSampleCount} live face samples before saving.`;

            if (this.registrationModalOpen) {
                this.$nextTick(() => this.$refs.registrationPrimaryAction?.focus());
            }
        }
    },

    currentFaceSample() {
        return this.faceSamples[this.currentSampleIndex] || this.faceSamples[0] || null;
    },

    sampleReady(sample) {
        return Boolean(sample?.capture && Array.isArray(sample?.descriptor));
    },

    currentSampleReady() {
        return this.sampleReady(this.currentFaceSample());
    },

    completedFaceSamples() {
        return this.faceSamples.filter((sample) => this.sampleReady(sample));
    },

    completedFaceSampleCount() {
        return this.completedFaceSamples().length;
    },

    allFaceSamplesReady() {
        return this.faceSamples.length >= this.requiredFaceSampleCount
            && this.faceSamples.every((sample) => this.sampleReady(sample));
    },

    hasAnyFaceSample() {
        return this.faceSamples.some((sample) => sample.capture || Array.isArray(sample.descriptor));
    },

    currentSampleTitle() {
        const sample = this.currentFaceSample();

        return sample
            ? `${this.currentSampleIndex + 1} of ${this.requiredFaceSampleCount}: ${sample.label}`
            : 'Live Face Sample';
    },

    currentSampleInstruction() {
        return this.currentFaceSample()?.instruction || 'Center the guard face, then capture the sample.';
    },

    registrationStatusTitle() {
        if (this.liveProcessing) {
            return 'Processing face sample';
        }

        if (this.allFaceSamplesReady()) {
            return 'Five face samples ready';
        }

        if (this.registrationCameraOpen) {
            return this.currentSampleTitle();
        }

        return 'Face registration required';
    },

    registrationStatusMessage() {
        if (this.allFaceSamplesReady()) {
            return 'Save Changes to finish the face registration.';
        }

        if (this.liveProcessing) {
            return 'Please wait while the face sample is prepared.';
        }

        return `${this.completedFaceSampleCount()} of ${this.requiredFaceSampleCount} live face samples are ready.`;
    },

    registrationActionLabel() {
        if (this.allFaceSamplesReady()) {
            return 'Review Samples';
        }

        if (this.liveProcessing) {
            return 'Processing...';
        }

        return this.hasAnyFaceSample() ? 'Continue Registration' : 'Register Face';
    },

    registrationCameraActionLabel() {
        if (this.registrationCameraOpen) {
            return 'Camera Open';
        }

        return this.currentSampleReady() ? 'Retake Current Sample' : 'Open Camera';
    },

    openRegistrationModal() {
        if (this.liveProcessing) {
            return;
        }

        this.registrationModalOpen = true;
        this.syncCurrentSampleState();
        this.$nextTick(() => this.$refs.registrationPrimaryAction?.focus());
    },

    closeRegistrationModal() {
        if (this.liveProcessing) {
            return;
        }

        this.stopRegistrationCamera();
        this.syncCurrentSampleState();
        this.registrationModalOpen = false;
    },

    selectRegistrationSample(index) {
        if (this.liveProcessing || index === this.currentSampleIndex || ! this.faceSamples[index]) {
            return;
        }

        this.stopRegistrationCamera();
        this.currentSampleIndex = index;
        this.syncCurrentSampleState();
        this.descriptorError = '';
        this.descriptorMessage = this.currentSampleReady()
            ? `${this.currentFaceSample().label} sample is ready.`
            : this.currentSampleInstruction();
    },

    nextIncompleteSampleIndex() {
        const afterCurrent = this.faceSamples.findIndex((sample, index) => index > this.currentSampleIndex && ! this.sampleReady(sample));

        if (afterCurrent !== -1) {
            return afterCurrent;
        }

        const firstMissing = this.faceSamples.findIndex((sample) => ! this.sampleReady(sample));

        return firstMissing === -1 ? null : firstMissing;
    },

    selectNextIncompleteSample() {
        const nextIndex = this.nextIncompleteSampleIndex();

        if (nextIndex !== null) {
            this.selectRegistrationSample(nextIndex);
        }
    },

    syncCurrentSampleState() {
        const sample = this.currentFaceSample();

        this.liveCapture = sample?.capture || '';
        this.liveDescriptor = sample?.descriptor || null;
        this.stopRegistrationLivenessCheck();
        this.livenessChallenge = sample?.challenge || 'center';
        this.livenessPassed = this.sampleReady(sample);
        this.livenessStatus = this.livenessPassed ? 'complete' : 'idle';
        this.livenessMessage = '';
        this.stableRegistrationFaceFrames = 0;
        this.resetLivenessActionState();
    },

    descriptorPayload(descriptor) {
        return descriptorToJson(descriptor);
    },

    livenessChallengeLabel() {
        return livenessLabelFor(this.livenessChallenge);
    },

    livenessChallengeBadge() {
        if (this.livenessPassed) {
            return 'Guide confirmed';
        }

        if (this.livenessStatus === 'align') {
            return 'Align face';
        }

        if (this.livenessStatus === 'face') {
            return 'Face detected';
        }

        return this.livenessChallengeLabel();
    },

    registrationLightAssistLabel() {
        return this.registrationLightAssist ? 'Light Assist On' : 'Light Assist';
    },

    async toggleRegistrationLightAssist() {
        if (this.liveProcessing) {
            return;
        }

        this.registrationLightAssist = ! this.registrationLightAssist;
        await this.syncRegistrationLightAssist();
    },

    async syncRegistrationLightAssist() {
        const stream = this.registrationCameraStream;

        if (! stream) {
            this.registrationTorchSupported = false;
            this.registrationTorchActive = false;
            this.registrationLightMessage = this.registrationLightAssist ? 'Screen light is ready.' : '';
            return;
        }

        this.registrationTorchSupported = cameraTorchCapable(stream);

        if (! this.registrationLightAssist) {
            await setCameraTorch(stream, false);
            this.registrationTorchActive = false;
            this.registrationLightMessage = '';
            return;
        }

        this.registrationTorchActive = await setCameraTorch(stream, true);
        this.registrationLightMessage = this.registrationTorchActive
            ? 'Screen light and torch are on.'
            : 'Screen light is on.';
    },

    async openRegistrationCamera() {
        const sample = this.currentFaceSample();

        if (! sample || this.liveProcessing) {
            return;
        }

        this.registrationModalOpen = true;
        this.stopRegistrationCamera();
        this.descriptorError = '';
        this.resetRegistrationLiveness();

        if (! canUseLiveCameraPreview()) {
            this.descriptorError = cameraAccessMessage(
                null,
                'Live face registration needs HTTPS on phones. Open this system through HTTPS and try again.',
            );
            return;
        }

        if (! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia) {
            this.descriptorError = 'Live camera preview is not available in this browser.';
            return;
        }

        try {
            this.registrationCameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 720 },
                    height: { ideal: 960 },
                    aspectRatio: { ideal: 0.75 },
                },
                audio: false,
            });
            this.clearCurrentFaceSample();
            this.resetRegistrationLiveness();
            this.$refs.registrationVideo.srcObject = this.registrationCameraStream;
            await this.$refs.registrationVideo.play().catch(() => null);
            this.registrationCameraOpen = true;
            await this.syncRegistrationLightAssist();
            this.descriptorMessage = 'Loading face guide...';
            await loadFaceModels();
            this.startRegistrationLivenessCheck();
        } catch (error) {
            this.descriptorError = cameraAccessMessage(
                error,
                'Live face registration needs HTTPS on phones. Open this system through HTTPS and try again.',
                'Camera permission was blocked. Allow camera access in the browser settings, then try again.',
            );
        }
    },

    async captureRegistrationFace() {
        const sample = this.currentFaceSample();
        const video = this.$refs.registrationVideo;
        const canvas = this.$refs.registrationCanvas;

        if (! sample) {
            this.descriptorError = 'Choose a face sample before capturing.';
            return;
        }

        if (! this.livenessPassed) {
            this.descriptorError = 'Complete the guided live action before capturing.';
            return;
        }

        if (! video || ! video.videoWidth) {
            this.descriptorError = 'Open the camera before capturing.';
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        const capture = canvas.toDataURL('image/jpeg', 0.82);
        this.liveCapture = capture;
        this.liveDescriptor = null;
        this.liveProcessing = true;
        this.descriptorError = '';
        this.descriptorMessage = 'Processing live face sample...';
        this.stopRegistrationCamera();

        try {
            await loadFaceModels();

            const image = await imageFromDataUrl(capture);
            const descriptor = await descriptorFromImage(image);
            const savedLabel = sample.label;

            sample.capture = capture;
            sample.descriptor = descriptor;
            this.liveDescriptor = descriptor;

            if (this.allFaceSamplesReady()) {
                this.descriptorMessage = 'All five live face samples are ready. Save Changes to finish registration.';
                this.registrationModalOpen = false;
                return;
            }

            const nextIndex = this.nextIncompleteSampleIndex();

            if (nextIndex !== null) {
                this.currentSampleIndex = nextIndex;
                this.syncCurrentSampleState();
                this.descriptorMessage = `${savedLabel} saved. Continue with ${this.currentFaceSample().label}.`;
            }
        } catch (error) {
            sample.capture = '';
            sample.descriptor = null;
            this.liveCapture = '';
            this.liveDescriptor = null;
            this.resetRegistrationLiveness();
            this.descriptorError = error.message || 'Live face data could not be generated.';
            this.descriptorMessage = this.currentSampleInstruction();
        } finally {
            this.liveProcessing = false;
        }
    },

    clearCurrentFaceSample() {
        const sample = this.currentFaceSample();

        if (! sample) {
            return;
        }

        sample.capture = '';
        sample.descriptor = null;
        this.liveCapture = '';
        this.liveDescriptor = null;
    },

    retakeRegistrationFace() {
        this.stopRegistrationCamera();
        this.clearCurrentFaceSample();
        this.descriptorError = '';
        this.resetRegistrationLiveness();
        this.descriptorMessage = this.currentSampleInstruction();
    },

    resetRegistrationLiveness() {
        this.stopRegistrationLivenessCheck();
        this.livenessPassed = false;
        this.livenessStatus = 'idle';
        this.livenessChallenge = this.currentFaceSample()?.challenge || 'center';
        this.livenessMessage = '';
        this.stableRegistrationFaceFrames = 0;
        this.resetLivenessActionState();
    },

    resetLivenessActionState() {
        this.livenessSmileBaseline = null;
        this.livenessSmileFrames = 0;
        this.livenessTurnFrames = 0;
    },

    startRegistrationLivenessCheck() {
        this.stopRegistrationLivenessCheck();
        this.livenessChallenge = this.currentFaceSample()?.challenge || 'center';
        this.resetLivenessActionState();
        this.stableRegistrationFaceFrames = 0;
        this.livenessChecking = true;
        this.livenessStatus = 'align';
        this.livenessMessage = this.currentSampleInstruction();
        this.descriptorMessage = this.livenessMessage;
        this.runRegistrationLivenessCheck();
    },

    async runRegistrationLivenessCheck() {
        if (! this.registrationCameraOpen || this.liveCapture || this.livenessPassed) {
            this.stopRegistrationLivenessCheck();
            return;
        }

        const video = this.$refs.registrationVideo;

        if (! video || ! video.videoWidth || ! video.videoHeight) {
            this.scheduleRegistrationLivenessCheck();
            return;
        }

        try {
            const detection = await faceapi
                .detectSingleFace(video, faceDetectorOptions())
                .withFaceLandmarks();

            if (! detection) {
                this.livenessStatus = 'align';
                this.livenessMessage = 'Position your face inside the guide.';
                this.descriptorMessage = this.livenessMessage;
                this.stableRegistrationFaceFrames = 0;
                this.resetLivenessActionState();
                return;
            }

            const guide = this.registrationFacePositionGuide(detection.detection.box, video);

            if (! guide.ready) {
                this.livenessStatus = 'align';
                this.livenessMessage = guide.message;
                this.descriptorMessage = this.livenessMessage;
                this.stableRegistrationFaceFrames = 0;
                this.resetLivenessActionState();
                return;
            }

            this.stableRegistrationFaceFrames += 1;

            if (this.stableRegistrationFaceFrames < this.requiredStableRegistrationFaceFrames) {
                this.livenessStatus = 'face';
                this.livenessMessage = 'Face detected. Hold still for the guided action.';
                this.descriptorMessage = this.livenessMessage;
                return;
            }

            if (this.runSelectedLivenessChallenge(detection.landmarks)) {
                this.markRegistrationLivenessPassed();
            }
        } catch (error) {
            this.livenessStatus = 'align';
            this.livenessMessage = 'Face guide is still scanning.';
            this.descriptorMessage = this.livenessMessage;
        } finally {
            if (this.registrationCameraOpen && ! this.liveCapture && ! this.livenessPassed) {
                this.scheduleRegistrationLivenessCheck();
            }
        }
    },

    registrationFacePositionGuide(box, video) {
        const videoWidth = video.videoWidth || 1;
        const videoHeight = video.videoHeight || 1;
        const centerX = Number(box.x) + (Number(box.width) / 2);
        const centerY = Number(box.y) + (Number(box.height) / 2);
        const horizontalOffset = (centerX - (videoWidth / 2)) / videoWidth;
        const verticalOffset = (centerY - (videoHeight / 2)) / videoHeight;
        const faceWidthRatio = Number(box.width) / videoWidth;
        const faceHeightRatio = Number(box.height) / videoHeight;

        if (faceWidthRatio < 0.22 || faceHeightRatio < 0.24) {
            return { ready: false, message: 'Move closer to the camera.' };
        }

        if (faceWidthRatio > 0.72 || faceHeightRatio > 0.86) {
            return { ready: false, message: 'Move slightly farther from the camera.' };
        }

        if (Math.abs(horizontalOffset) > 0.18) {
            return { ready: false, message: horizontalOffset > 0 ? 'Move slightly left.' : 'Move slightly right.' };
        }

        if (Math.abs(verticalOffset) > 0.18) {
            return { ready: false, message: verticalOffset > 0 ? 'Raise the phone slightly.' : 'Lower the phone slightly.' };
        }

        return { ready: true, message: 'Face centered.' };
    },

    runSelectedLivenessChallenge(landmarks) {
        if (this.livenessChallenge === 'center') {
            this.livenessStatus = 'center';
            this.livenessMessage = 'Face centered. Capture is unlocked.';
            this.descriptorMessage = this.livenessMessage;
            return true;
        }

        if (this.livenessChallenge === 'turn-left' || this.livenessChallenge === 'turn-right' || this.livenessChallenge === 'turn') {
            return this.runTurnChallenge(landmarks);
        }

        return this.runSmileChallenge(landmarks);
    },

    runSmileChallenge(landmarks) {
        const ratio = mouthWidthRatio(landmarks);

        if (ratio === null) {
            this.livenessStatus = 'face';
            this.livenessMessage = 'Keep your mouth visible to the camera.';
            this.descriptorMessage = this.livenessMessage;
            return false;
        }

        this.livenessSmileBaseline = this.livenessSmileBaseline === null
            ? ratio
            : Math.min(this.livenessSmileBaseline, ratio);

        const smiled = ratio >= SMILE_RATIO_THRESHOLD
            || ratio >= this.livenessSmileBaseline + SMILE_RATIO_DELTA;

        if (smiled) {
            this.livenessSmileFrames += 1;

            if (this.livenessSmileFrames >= 2) {
                return true;
            }
        } else {
            this.livenessSmileFrames = 0;
        }

        this.livenessStatus = 'smile';
        this.livenessMessage = 'Challenge: smile.';
        this.descriptorMessage = this.livenessMessage;

        return false;
    },

    runTurnChallenge(landmarks) {
        const ratio = headTurnRatio(landmarks);

        if (ratio === null) {
            this.livenessStatus = 'face';
            this.livenessMessage = 'Keep your whole face visible to the camera.';
            this.descriptorMessage = this.livenessMessage;
            return false;
        }

        const turned = this.livenessChallenge === 'turn-left'
            ? ratio <= -TURN_HEAD_THRESHOLD
            : (this.livenessChallenge === 'turn-right'
                ? ratio >= TURN_HEAD_THRESHOLD
                : Math.abs(ratio) >= TURN_HEAD_THRESHOLD);

        if (turned) {
            this.livenessTurnFrames += 1;

            if (this.livenessTurnFrames >= 2) {
                return true;
            }
        } else {
            this.livenessTurnFrames = 0;
        }

        this.livenessStatus = this.livenessChallenge;
        this.livenessMessage = `Challenge: ${this.livenessChallengeLabel()}.`;
        this.descriptorMessage = this.livenessMessage;

        return false;
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
            void setCameraTorch(this.registrationCameraStream, false);
            this.registrationCameraStream.getTracks().forEach((track) => track.stop());
            this.registrationCameraStream = null;
        }

        this.registrationTorchActive = false;
        this.registrationCameraOpen = false;
    },

    handleSubmit(event) {
        if (this.liveProcessing) {
            event.preventDefault();
            this.descriptorError = 'Please wait until face sample processing is finished.';
            return;
        }

        if (this.liveRegistration && this.hasAnyFaceSample() && ! this.allFaceSamplesReady()) {
            event.preventDefault();
            this.registrationModalOpen = true;
            this.descriptorError = 'Complete all five live face samples before saving face registration.';
            return;
        }

        this.stopRegistrationCamera();
    },
}));

Alpine.data('patrolScan', (config = {}) => ({
    incident: config.incident || false,
    incidentImageCount: 0,
    incidentImageError: '',
    incidentFormError: '',
    incidentImagePreviews: [],
    checklistPhotoError: '',
    checklistPhotoPreviews: {},
    checklistItems: config.checklistItems || [],
    checklistPhotoModalOpen: false,
    selectedChecklistPhoto: null,
    pendingScan: config.pendingScan || null,
    pendingScanUrl: config.pendingScanUrl,
    csrfRefreshUrl: config.csrfRefreshUrl || '/csrf-token',
    guardName: config.guardName || '',
    guardEmployeeNo: config.guardEmployeeNo || '',
    patrolLogId: config.patrolLogId || '',
    scanMessage: config.scanMessage || 'Waiting for your ESP32 checkpoint scan.',
    patrolScheduleOpen: config.patrolScheduleOpen ?? true,
    patrolScheduleTestingMode: config.patrolScheduleTestingMode || false,
    patrolScheduleMessage: config.patrolScheduleMessage || 'Guard patrol scanning is currently closed.',
    patrolTestingNotice: config.patrolTestingNotice || '',
    areaSelfieCapture: config.areaSelfieCapture || '',
    areaSelfieCapturedAt: config.areaSelfieCapturedAt || '',
    areaSelfieLatitude: config.areaSelfieLatitude || '',
    areaSelfieLongitude: config.areaSelfieLongitude || '',
    areaSelfieAccuracy: config.areaSelfieAccuracy || '',
    areaSelfieError: '',
    areaSelfieMessage: '',
    areaSelfieLocationBusy: false,
    areaSelfieCameraOpen: false,
    areaSelfieLastPosition: null,
    areaSelfieUnmirrorFrontCamera: true,
    pollingTimer: null,
    checklistModalOpen: config.openChecklist || false,
    incidentModalOpen: config.openIncident || false,
    cameraOpen: false,
    cameraStream: null,
    cameraError: '',
    cameraOpening: false,
    verificationMessage: '',
    submittingPatrol: false,

    boot() {
        this.verificationMessage = 'Scan RFID, take the required area selfie, then complete the patrol checklist.';

        if (! this.patrolScheduleOpen) {
            this.scanMessage = this.patrolScheduleMessage;
            this.verificationMessage = this.patrolScheduleMessage;
            return;
        }

        if (this.incidentModalOpen) {
            this.checklistModalOpen = false;
            this.$nextTick(() => this.focusIncidentForm());
        } else if (this.pendingScan && this.areaSelfieComplete()) {
            this.checklistModalOpen = true;
            this.scanMessage = 'Area selfie captured. Complete the checklist.';
            this.$nextTick(() => document.getElementById('doors_locked_normal')?.focus());
        } else if (this.pendingScan) {
            this.scanMessage = 'RFID accepted. Take the required area selfie.';
        } else if (! this.pendingScan) {
            this.startPolling();
        }
    },

    hasAreaSelfieValue(value) {
        return value !== null && value !== undefined && String(value).trim() !== '';
    },

    areaSelfieComplete() {
        return Boolean(this.areaSelfieCapture)
            && this.hasAreaSelfieValue(this.areaSelfieCapturedAt)
            && this.hasAreaSelfieValue(this.areaSelfieLatitude)
            && this.hasAreaSelfieValue(this.areaSelfieLongitude);
    },

    areaSelfieLocationLabel() {
        return this.pendingScan?.checkpoint?.location
            || this.pendingScan?.checkpoint?.name
            || this.pendingScan?.checkpoint?.code
            || this.pendingScan?.checkpoint_code
            || 'Checkpoint area';
    },

    areaSelfieCapturedLabel() {
        if (! this.areaSelfieCapturedAt) {
            return 'Not captured yet';
        }

        const date = new Date(this.areaSelfieCapturedAt);

        if (Number.isNaN(date.getTime())) {
            return this.areaSelfieCapturedAt;
        }

        return this.formatAreaSelfieStampTime(date);
    },

    areaSelfieGpsLabel() {
        if (! this.hasAreaSelfieValue(this.areaSelfieLatitude) || ! this.hasAreaSelfieValue(this.areaSelfieLongitude)) {
            return 'GPS not recorded yet';
        }

        const latitude = Number(this.areaSelfieLatitude);
        const longitude = Number(this.areaSelfieLongitude);
        const accuracy = Number(this.areaSelfieAccuracy);
        const gps = `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;

        return Number.isFinite(accuracy) && accuracy > 0
            ? `${gps} (+/- ${Math.round(accuracy)}m)`
            : gps;
    },

    areaSelfieStampPreview() {
        return [
            `Name: ${this.pendingScan?.guard?.name || this.guardName || 'Guard'}`,
            `Location: ${this.areaSelfieLocationLabel()}`,
            `Time: ${this.formatAreaSelfieStampTime(new Date())}`,
            'GPS: capturing on photo',
        ].join(' | ');
    },

    formatAreaSelfieStampTime(date) {
        return date.toLocaleString('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    },

    async openAreaSelfieCamera() {
        if (this.cameraOpening || this.submittingPatrol) {
            return;
        }

        if (! this.pendingScan) {
            this.areaSelfieError = 'Scan your RFID card at the checkpoint reader first.';
            return;
        }

        if (! canUseLiveCameraPreview()) {
            this.areaSelfieError = cameraAccessMessage(
                null,
                'Live camera preview needs HTTPS on phones. Open this system through HTTPS and try again.',
            );
            return;
        }

        if (! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia) {
            this.areaSelfieError = 'Live camera preview is not available in this browser.';
            return;
        }

        this.areaSelfieError = '';
        this.cameraError = '';
        this.cameraOpening = true;
        this.areaSelfieLocationBusy = true;
        this.areaSelfieMessage = 'Allow location access so GPS can be stamped on the photo.';

        try {
            this.areaSelfieLastPosition = await this.getAreaSelfiePosition({
                maximumAge: 30000,
                timeout: 10000,
            });
            this.areaSelfieLocationBusy = false;
            this.areaSelfieCameraOpen = true;
            this.areaSelfieMessage = 'Opening camera...';
            this.stopCamera();
            this.cameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 1280 },
                },
                audio: false,
            });
            this.cameraOpen = true;

            await new Promise((resolve) => this.$nextTick(resolve));
            const video = this.$refs.areaSelfieVideo;

            if (video) {
                video.srcObject = this.cameraStream;
                await video.play().catch(() => null);
            }

            this.areaSelfieMessage = 'Camera ready. Include your face and checkpoint area in the frame.';
        } catch (error) {
            this.cameraError = error?.code
                ? ''
                : cameraAccessMessage(
                    error,
                    'Live camera preview needs HTTPS on phones. Open this system through HTTPS and try again.',
                    'Camera permission was blocked. Allow camera access in the browser settings, then try again.',
                );
            this.areaSelfieError = error?.code ? this.areaSelfieLocationErrorMessage(error) : '';
            this.areaSelfieMessage = '';
            this.areaSelfieCameraOpen = false;
        } finally {
            this.cameraOpening = false;
            this.areaSelfieLocationBusy = false;
        }
    },

    closeAreaSelfieCamera() {
        if (this.cameraOpening || this.areaSelfieLocationBusy) {
            return;
        }

        this.areaSelfieCameraOpen = false;
        this.stopCamera();
    },

    getAreaSelfiePosition(options = {}) {
        return new Promise((resolve, reject) => {
            if (! navigator.geolocation) {
                reject(new Error('GPS location is not available in this browser.'));
                return;
            }

            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 0,
                ...options,
            });
        });
    },

    areaSelfieLocationErrorMessage(error) {
        if (error?.code === 1) {
            return 'Location permission was denied. Tap the lock or site settings icon in your browser, allow Location for this site, then press Take Photo again.';
        }

        if (error?.code === 2) {
            return 'GPS location is unavailable. Turn on phone location services, then press Take Photo again.';
        }

        if (error?.code === 3) {
            return 'GPS took too long to respond. Move where the phone can detect location, then try again.';
        }

        return error?.message || 'GPS is required before capturing the area selfie.';
    },

    async captureAreaSelfie() {
        if (this.areaSelfieLocationBusy || this.submittingPatrol) {
            return;
        }

        const video = this.$refs.areaSelfieVideo;
        const canvas = this.$refs.areaSelfieCanvas;

        if (! video || ! video.videoWidth || ! video.videoHeight || ! canvas) {
            this.cameraError = 'Open the camera before capturing the area selfie.';
            return;
        }

        this.areaSelfieError = '';
        this.cameraError = '';
        this.areaSelfieLocationBusy = true;
        this.areaSelfieMessage = 'Getting GPS before stamping the photo...';

        try {
            const position = await this.getAreaSelfiePosition({
                maximumAge: 15000,
                timeout: 10000,
            }).catch((error) => {
                if (this.areaSelfieLastPosition) {
                    return this.areaSelfieLastPosition;
                }

                throw error;
            });
            this.areaSelfieLastPosition = position;
            const capturedAt = new Date();
            const maxWidth = 1024;
            const scale = Math.min(1, maxWidth / video.videoWidth);
            const width = Math.round(video.videoWidth * scale);
            const height = Math.round(video.videoHeight * scale);
            const context = canvas.getContext('2d');

            if (! context) {
                throw new Error('Camera capture is not available in this browser.');
            }

            canvas.width = width;
            canvas.height = height;

            if (this.areaSelfieUnmirrorFrontCamera) {
                context.translate(width, 0);
                context.scale(-1, 1);
            }

            context.drawImage(video, 0, 0, width, height);
            context.setTransform(1, 0, 0, 1, 0, 0);
            this.drawAreaSelfieStamp(context, width, height, capturedAt, position.coords);

            this.areaSelfieCapture = canvas.toDataURL('image/jpeg', 0.76);
            this.areaSelfieCapturedAt = capturedAt.toISOString();
            this.areaSelfieLatitude = Number(position.coords.latitude).toFixed(7);
            this.areaSelfieLongitude = Number(position.coords.longitude).toFixed(7);
            this.areaSelfieAccuracy = Number.isFinite(Number(position.coords.accuracy))
                ? Number(position.coords.accuracy).toFixed(2)
                : '';
            this.areaSelfieMessage = 'Area selfie captured. Continue to checklist.';
            this.scanMessage = 'Area selfie captured. Complete the checklist.';
            this.areaSelfieCameraOpen = false;
            this.stopCamera();
        } catch (error) {
            this.areaSelfieError = this.areaSelfieLocationErrorMessage(error);
            this.areaSelfieMessage = '';
        } finally {
            this.areaSelfieLocationBusy = false;
        }
    },

    drawAreaSelfieStamp(context, width, height, capturedAt, coords) {
        const fontSize = Math.max(18, Math.round(width * 0.026));
        const padding = Math.round(width * 0.024);
        const lineHeight = Math.round(fontSize * 1.45);
        const lines = [
            `Name: ${this.pendingScan?.guard?.name || this.guardName || 'Guard'}`,
            `Location: ${this.areaSelfieLocationLabel()}`,
            `Time: ${this.formatAreaSelfieStampTime(capturedAt)}`,
            `GPS: ${Number(coords.latitude).toFixed(6)}, ${Number(coords.longitude).toFixed(6)}`,
        ];
        const boxHeight = (lineHeight * lines.length) + (padding * 2);
        const boxTop = Math.max(0, height - boxHeight);

        context.fillStyle = 'rgba(15, 23, 42, 0.78)';
        context.fillRect(0, boxTop, width, boxHeight);
        context.fillStyle = '#ffffff';
        context.font = `600 ${fontSize}px Arial, sans-serif`;
        context.textBaseline = 'top';

        lines.forEach((line, index) => {
            context.fillText(
                this.fitStampText(context, line, width - (padding * 2)),
                padding,
                boxTop + padding + (index * lineHeight),
            );
        });
    },

    fitStampText(context, text, maxWidth) {
        if (context.measureText(text).width <= maxWidth) {
            return text;
        }

        let shortened = text;

        while (shortened.length > 12 && context.measureText(`${shortened}...`).width > maxWidth) {
            shortened = shortened.slice(0, -1);
        }

        return `${shortened}...`;
    },

    clearAreaSelfie() {
        this.areaSelfieCapture = '';
        this.areaSelfieCapturedAt = '';
        this.areaSelfieLatitude = '';
        this.areaSelfieLongitude = '';
        this.areaSelfieAccuracy = '';
        this.areaSelfieError = '';
        this.areaSelfieMessage = '';
        this.areaSelfieLastPosition = null;
        this.scanMessage = this.pendingScan
            ? 'RFID accepted. Take the required area selfie.'
            : 'Waiting for your ESP32 checkpoint scan.';
        this.areaSelfieCameraOpen = false;
        this.stopCamera();
    },

    startPolling() {
        if (! this.patrolScheduleOpen) {
            return;
        }

        this.fetchPendingScan();
        this.pollingTimer = setInterval(() => this.fetchPendingScan(), 3000);
    },

    async fetchPendingScan() {
        if (this.pendingScan) {
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
                this.scanMessage = this.areaSelfieComplete()
                    ? 'Area selfie captured. Complete the checklist.'
                    : 'RFID accepted. Take the required area selfie.';
                clearInterval(this.pollingTimer);

                if (this.areaSelfieComplete()) {
                    this.checklistModalOpen = true;
                    this.$nextTick(() => document.getElementById('doors_locked_normal')?.focus());
                }
            } else if (data.message) {
                this.scanMessage = data.message;
            }
        } catch (error) {
            this.scanMessage = 'Waiting for RFID scan. Check Wi-Fi if this takes too long.';
        }
    },

    continueToChecklist() {
        if (this.submittingPatrol) {
            return;
        }

        if (! this.areaSelfieComplete()) {
            this.areaSelfieError = 'Take the required area selfie with GPS before opening the checklist.';
            return;
        }

        this.areaSelfieError = '';
        this.checklistModalOpen = true;
        this.incidentModalOpen = false;
        this.$nextTick(() => document.getElementById('doors_locked_normal')?.focus());
    },

    checklistPhotoCount() {
        return Object.keys(this.checklistPhotoPreviews).length;
    },

    checklistPhotoInput(field) {
        return this.$refs[`checklistPhoto_${field}`];
    },

    takeChecklistPhoto(field) {
        if (this.submittingPatrol) {
            return;
        }

        this.checklistPhotoInput(field)?.click();
    },

    updateChecklistPhoto(field, event) {
        const file = event.target.files?.[0];

        if (! file) {
            return;
        }

        if (file.type && ! file.type.startsWith('image/')) {
            event.target.value = '';
            this.checklistPhotoError = 'Choose a valid checklist proof photo.';
            return;
        }

        this.removeChecklistPhoto(field, false);

        this.checklistPhotoPreviews = {
            ...this.checklistPhotoPreviews,
            [field]: {
                field,
                name: file.name || 'Checklist proof photo',
                url: URL.createObjectURL(file),
            },
        };
        this.checklistPhotoError = '';
    },

    removeChecklistPhoto(field, clearInput = true) {
        const preview = this.checklistPhotoPreviews[field];

        if (preview?.url) {
            URL.revokeObjectURL(preview.url);
        }

        const nextPreviews = { ...this.checklistPhotoPreviews };
        delete nextPreviews[field];
        this.checklistPhotoPreviews = nextPreviews;

        if (this.selectedChecklistPhoto?.field === field) {
            this.closeChecklistPhotoPreview();
        }

        if (clearInput) {
            const input = this.checklistPhotoInput(field);

            if (input) {
                input.value = '';
            }
        }
    },

    openChecklistPhotoPreview(field) {
        const preview = this.checklistPhotoPreviews[field];

        if (! preview) {
            return;
        }

        this.selectedChecklistPhoto = preview;
        this.checklistPhotoModalOpen = true;
    },

    closeChecklistPhotoPreview() {
        this.checklistPhotoModalOpen = false;
        this.selectedChecklistPhoto = null;
    },

    validateChecklistPhotos() {
        const missingIssuePhotos = this.checklistItems
            .filter((item) => {
                const selected = document.querySelector(`input[name="checklist_statuses[${item.field}]"]:checked`);

                return selected?.value === 'issue' && ! this.checklistPhotoPreviews[item.field];
            })
            .map((item) => item.label);

        if (missingIssuePhotos.length > 0) {
            this.checklistPhotoError = `Take a proof photo for each Issue Found item: ${missingIssuePhotos.join(', ')}.`;
            return false;
        }

        this.checklistPhotoError = '';
        return true;
    },

    focusIncidentForm() {
        if (this.$refs.incidentDescription && ! this.$refs.incidentDescription.value.trim()) {
            this.$refs.incidentDescription.focus();
            return;
        }

        this.$refs.incidentCategory?.focus();
    },

    openIncidentModal() {
        if (this.submittingPatrol) {
            return;
        }

        this.incident = true;
        this.incidentModalOpen = true;
        this.checklistModalOpen = false;
        this.$nextTick(() => this.focusIncidentForm());
    },

    closeIncidentModal() {
        if (this.submittingPatrol) {
            return;
        }

        this.incidentModalOpen = false;
        this.checklistModalOpen = true;
        this.$nextTick(() => document.getElementById('area_secure')?.focus());
    },

    handleIncidentToggle(event) {
        if (event?.target?.checked) {
            this.openIncidentModal();
            return;
        }

        this.clearIncidentReport();
    },

    clearIncidentReport() {
        this.incident = false;
        this.incidentModalOpen = false;
        this.incidentFormError = '';
        this.incidentImageError = '';
        this.incidentImageCount = 0;
        this.clearIncidentImagePreviews();

        if (this.$refs.incidentUploadImages) {
            this.$refs.incidentUploadImages.value = '';
        }

        if (this.$refs.incidentCameraImages) {
            this.$refs.incidentCameraImages.value = '';
        }

        if (this.$refs.incidentDescription) {
            this.$refs.incidentDescription.value = '';
        }

        if (this.$refs.incidentCategory) {
            this.$refs.incidentCategory.selectedIndex = 0;
        }

        if (this.$refs.incidentPriority) {
            this.$refs.incidentPriority.value = 'normal';
        }
    },

    incidentSummary() {
        if (! this.incident) {
            return 'No incident report will be attached.';
        }

        if (this.incidentImageCount > 0) {
            return `${this.incidentImageCount} image${this.incidentImageCount === 1 ? '' : 's'} selected for the incident report.`;
        }

        return 'Incident details are started. Add the required image before submitting.';
    },

    incidentDetailsComplete() {
        if (! this.incident) {
            this.incidentFormError = '';
            return true;
        }

        const category = this.$refs.incidentCategory?.value || '';
        const description = this.$refs.incidentDescription?.value?.trim() || '';

        if (! category || ! description) {
            this.incidentFormError = 'Complete the incident category and description before submitting.';
            return false;
        }

        this.incidentFormError = '';
        return true;
    },

    incidentFileCount(refName) {
        return this.$refs[refName]?.files?.length || 0;
    },

    incidentFiles() {
        return [
            ...Array.from(this.$refs.incidentUploadImages?.files || []),
            ...Array.from(this.$refs.incidentCameraImages?.files || []),
        ];
    },

    clearIncidentImagePreviews() {
        this.incidentImagePreviews.forEach((preview) => {
            if (preview.url) {
                URL.revokeObjectURL(preview.url);
            }
        });

        this.incidentImagePreviews = [];
    },

    refreshIncidentImagePreviews() {
        this.clearIncidentImagePreviews();

        this.incidentImagePreviews = this.incidentFiles()
            .filter((file) => ! file.type || file.type.startsWith('image/'))
            .slice(0, 3)
            .map((file, index) => ({
                id: `${file.name}-${file.size}-${file.lastModified}-${index}`,
                name: file.name,
                url: URL.createObjectURL(file),
            }));
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
            this.refreshIncidentImagePreviews();

            return false;
        }

        this.incidentImageCount = total;
        this.refreshIncidentImagePreviews();
        this.incidentFormError = '';

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
            void setCameraTorch(this.cameraStream, false);
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

        if (! this.patrolLogId || ! this.areaSelfieComplete()) {
            event.preventDefault();
            this.areaSelfieError = this.patrolLogId
                ? 'Take the required area selfie with GPS before submitting.'
                : 'Wait for an RFID scan before submitting.';
            this.checklistModalOpen = false;
            return;
        }

        if (! this.validateChecklistPhotos()) {
            event.preventDefault();
            this.checklistModalOpen = true;
            return;
        }

        if (this.incident) {
            const incidentDetailsComplete = this.incidentDetailsComplete();
            const incidentImagesValid = this.updateIncidentImageCount();

            if (! incidentDetailsComplete || ! incidentImagesValid) {
                event.preventDefault();
                this.openIncidentModal();
                return;
            }
        }

        this.submittingPatrol = true;
        this.verificationMessage = 'Submitting patrol record...';
        this.stopCamera();
    },
}));

Alpine.data('guardManagementPage', (config = {}) => ({
    createModalOpen: Boolean(config.createModalOpen),
    editModalOpen: Boolean(config.editModalOpen),
    editGuardId: config.editGuardId ? String(config.editGuardId) : '',
    deleteModalOpen: false,
    deleteGuardAction: '',
    deleteGuardName: '',
    recordModalOpen: false,
    recordLoading: false,
    recordError: '',
    selectedGuard: null,
    recordStats: {},
    recordPatrols: [],
    recordIncidents: [],
    recordFaceAttempts: [],
    resizeHandler: null,

    init() {
        this.resizeHandler = () => this.updateBodyScrollLock();
        window.addEventListener('resize', this.resizeHandler);

        this.updateBodyScrollLock();

        if (this.createModalOpen) {
            this.$nextTick(() => this.$refs.createGuardFirstField?.focus());
        }

        if (this.editModalOpen && this.editGuardId) {
            this.$nextTick(() => this.focusEditGuardField());
        }
    },

    destroy() {
        if (this.resizeHandler) {
            window.removeEventListener('resize', this.resizeHandler);
        }

        document.body.classList.remove('overflow-y-hidden');
    },

    sidePanelOpen() {
        return this.createModalOpen || this.editModalOpen;
    },

    isCompactPanelViewport() {
        return window.matchMedia('(max-width: 1023px)').matches;
    },

    closeSidePanel() {
        this.createModalOpen = false;
        this.editModalOpen = false;
        this.editGuardId = '';
        this.updateBodyScrollLock();
    },

    openCreateGuardModal() {
        this.editModalOpen = false;
        this.editGuardId = '';
        this.deleteModalOpen = false;
        this.recordModalOpen = false;
        this.createModalOpen = true;
        this.updateBodyScrollLock();
        this.$nextTick(() => this.$refs.createGuardFirstField?.focus());
    },

    closeCreateGuardModal() {
        this.createModalOpen = false;
        this.updateBodyScrollLock();
    },

    openEditGuardModal(guardId) {
        this.createModalOpen = false;
        this.recordModalOpen = false;
        this.deleteModalOpen = false;
        this.editGuardId = String(guardId || '');
        this.editModalOpen = Boolean(this.editGuardId);
        this.updateBodyScrollLock();
        this.$nextTick(() => this.focusEditGuardField());
    },

    closeEditGuardModal() {
        this.editModalOpen = false;
        this.editGuardId = '';
        this.updateBodyScrollLock();
    },

    focusEditGuardField() {
        if (! this.editGuardId) {
            return;
        }

        document.querySelector(`[data-edit-guard-first-field="${this.editGuardId}"]`)?.focus();
    },

    openDeleteGuardModal(action, name) {
        this.createModalOpen = false;
        this.editModalOpen = false;
        this.editGuardId = '';
        this.recordModalOpen = false;
        this.deleteGuardAction = action || '';
        this.deleteGuardName = name || 'this guard';
        this.deleteModalOpen = true;
        this.updateBodyScrollLock();
        this.$nextTick(() => this.$refs.deleteGuardCancelButton?.focus());
    },

    closeDeleteGuardModal() {
        this.deleteModalOpen = false;
        this.deleteGuardAction = '';
        this.deleteGuardName = '';
        this.updateBodyScrollLock();
    },

    submitDeleteGuard() {
        if (! this.deleteGuardAction) {
            return;
        }

        this.$refs.deleteGuardForm?.submit();
    },

    async openGuardRecord(url) {
        if (! url) {
            return;
        }

        this.createModalOpen = false;
        this.editModalOpen = false;
        this.editGuardId = '';
        this.deleteModalOpen = false;
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
        document.body.classList.toggle(
            'overflow-y-hidden',
            this.recordModalOpen
                || this.deleteModalOpen
                || (this.isCompactPanelViewport() && this.sidePanelOpen()),
        );
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
            return 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/35 dark:text-emerald-200 dark:ring-emerald-400/45';
        }

        if (['pending', 'pending_face', 'pending_selfie', 'pending_checklist', 'open', 'in_progress'].includes(status)) {
            return 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/35 dark:text-blue-200 dark:ring-blue-400/45';
        }

        if (['suspicious', 'profile_incomplete', 'medium', 'high'].includes(status)) {
            return 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/35 dark:text-amber-200 dark:ring-amber-400/45';
        }

        if (['failed', 'invalid', 'critical'].includes(status)) {
            return 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/35 dark:text-red-200 dark:ring-red-400/45';
        }

        return 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-slate-950/50 dark:text-slate-200 dark:ring-slate-500/60';
    },
}));

Alpine.data('checkpointManagementPage', (config = {}) => ({
    createModalOpen: Boolean(config.createModalOpen),
    editModalOpen: Boolean(config.editModalOpen),
    editCheckpointId: config.editCheckpointId ? String(config.editCheckpointId) : '',
    deleteModalOpen: false,
    deleteCheckpointAction: '',
    deleteCheckpointName: '',
    resizeHandler: null,

    init() {
        this.resizeHandler = () => this.updateBodyScrollLock();
        window.addEventListener('resize', this.resizeHandler);

        this.updateBodyScrollLock();

        if (this.createModalOpen) {
            this.$nextTick(() => this.$refs.createCheckpointFirstField?.focus());
        }

        if (this.editModalOpen && this.editCheckpointId) {
            this.$nextTick(() => this.focusEditCheckpointField());
        }
    },

    destroy() {
        if (this.resizeHandler) {
            window.removeEventListener('resize', this.resizeHandler);
        }

        document.body.classList.remove('overflow-y-hidden');
    },

    sidePanelOpen() {
        return this.createModalOpen || this.editModalOpen;
    },

    isCompactPanelViewport() {
        return window.matchMedia('(max-width: 1023px)').matches;
    },

    openCreateCheckpointModal() {
        this.editModalOpen = false;
        this.editCheckpointId = '';
        this.deleteModalOpen = false;
        this.createModalOpen = true;
        this.updateBodyScrollLock();
        this.$nextTick(() => this.$refs.createCheckpointFirstField?.focus());
    },

    closeCreateCheckpointModal() {
        this.createModalOpen = false;
        this.updateBodyScrollLock();
    },

    openEditCheckpointModal(checkpointId) {
        this.createModalOpen = false;
        this.deleteModalOpen = false;
        this.editCheckpointId = String(checkpointId || '');
        this.editModalOpen = Boolean(this.editCheckpointId);
        this.updateBodyScrollLock();
        this.$nextTick(() => this.focusEditCheckpointField());
    },

    closeEditCheckpointModal() {
        this.editModalOpen = false;
        this.editCheckpointId = '';
        this.updateBodyScrollLock();
    },

    focusEditCheckpointField() {
        if (! this.editCheckpointId) {
            return;
        }

        document.querySelector(`[data-edit-checkpoint-first-field="${this.editCheckpointId}"]`)?.focus();
    },

    openDeleteCheckpointModal(action, name) {
        this.createModalOpen = false;
        this.editModalOpen = false;
        this.editCheckpointId = '';
        this.deleteCheckpointAction = action || '';
        this.deleteCheckpointName = name || 'this checkpoint';
        this.deleteModalOpen = true;
        this.updateBodyScrollLock();
        this.$nextTick(() => this.$refs.deleteCheckpointCancelButton?.focus());
    },

    closeDeleteCheckpointModal() {
        this.deleteModalOpen = false;
        this.deleteCheckpointAction = '';
        this.deleteCheckpointName = '';
        this.updateBodyScrollLock();
    },

    updateBodyScrollLock() {
        document.body.classList.toggle(
            'overflow-y-hidden',
            this.deleteModalOpen || (this.isCompactPanelViewport() && this.sidePanelOpen()),
        );
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

    isCompactChart() {
        return window.matchMedia('(max-width: 640px)').matches;
    },

    baseOptions({ legend = false } = {}) {
        const compact = this.isCompactChart();

        return {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 100,
            animation: false,
            layout: {
                padding: compact ? 0 : 4,
            },
            plugins: {
                legend: {
                    display: legend,
                    position: compact ? 'bottom' : 'top',
                    labels: {
                        color: this.chartTextColor(),
                        boxWidth: compact ? 10 : 12,
                        boxHeight: compact ? 10 : 12,
                        padding: compact ? 10 : 12,
                        usePointStyle: true,
                        font: {
                            family: 'Figtree',
                            size: compact ? 10 : 12,
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
        const compact = this.isCompactChart();

        return {
            x: {
                grid: {
                    color: this.gridColor(),
                },
                ticks: {
                    autoSkip: true,
                    color: this.chartTextColor(),
                    maxRotation: 0,
                    maxTicksLimit: compact ? 4 : 8,
                    font: {
                        family: 'Figtree',
                        size: compact ? 10 : 12,
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
                    maxTicksLimit: compact ? 5 : 8,
                    stepSize: 1,
                    font: {
                        family: 'Figtree',
                        size: compact ? 10 : 12,
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
                cutout: this.isCompactChart() ? '62%' : '68%',
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
