import './bootstrap';

import Alpine from 'alpinejs';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

window.Alpine = Alpine;
window.Chart = Chart;

const PWA_LAUNCH_SPLASH_MS = 1400;
const PWA_LAUNCH_SPLASH_STORAGE_KEY = 'slsu-pwa-launch-splash-shown';
const LOCALHOST_HOSTNAMES = ['localhost', '127.0.0.1', '[::1]', '::1'];

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

function compressedImageName(name) {
    const cleanName = String(name || 'patrol-photo').trim() || 'patrol-photo';
    const withoutExtension = cleanName.replace(/\.[^.]+$/, '');

    return `${withoutExtension || 'patrol-photo'}.jpg`;
}

async function compressedImageFile(file, options = {}) {
    if (! file || (file.type && ! file.type.startsWith('image/'))) {
        return file;
    }

    try {
        const dataUrl = await dataUrlFromImageFile(file, {
            maxSize: 1280,
            quality: 0.72,
            ...options,
        });
        const response = await fetch(dataUrl);
        const blob = await response.blob();

        if (! blob?.size || typeof File === 'undefined') {
            return file;
        }

        return new File([blob], compressedImageName(file.name), {
            type: 'image/jpeg',
            lastModified: file.lastModified || Date.now(),
        });
    } catch (error) {
        return file;
    }
}

async function replaceInputImagesWithCompressedCopies(input, options = {}) {
    if (! input?.files?.length || typeof DataTransfer === 'undefined') {
        return false;
    }

    const transfer = new DataTransfer();

    for (const file of Array.from(input.files)) {
        transfer.items.add(await compressedImageFile(file, options));
    }

    input.files = transfer.files;

    return true;
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


Alpine.data('patrolScan', (config = {}) => ({
    incident: config.incident || false,
    incidentImageCount: 0,
    incidentImageError: '',
    incidentFormError: '',
    incidentImagePreviews: [],
    checklistPhotoError: '',
    checklistPhotoPreviews: {},
    imageCompressionBusy: false,
    imageCompressionMessage: '',
    checklistItems: config.checklistItems || [],
    checklistPhotoModalOpen: false,
    selectedChecklistPhoto: null,
    pendingScan: config.pendingScan || null,
    pendingScanUrl: config.pendingScanUrl,
    cancelScanUrl: config.cancelScanUrl,
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
    cancelScanModalOpen: false,
    cancellingScan: false,
    cancelScanError: '',
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

    clearAreaSelfieFields() {
        this.areaSelfieCapture = '';
        this.areaSelfieCapturedAt = '';
        this.areaSelfieLatitude = '';
        this.areaSelfieLongitude = '';
        this.areaSelfieAccuracy = '';
        this.areaSelfieError = '';
        this.areaSelfieMessage = '';
        this.areaSelfieLastPosition = null;
    },

    clearAreaSelfie() {
        this.clearAreaSelfieFields();
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

    openCancelScanModal() {
        if (! this.pendingScan || this.submittingPatrol || this.cancellingScan) {
            return;
        }

        this.cancelScanError = '';
        this.cancelScanModalOpen = true;
    },

    closeCancelScanModal() {
        if (this.cancellingScan) {
            return;
        }

        this.cancelScanModalOpen = false;
        this.cancelScanError = '';
    },

    async cancelPendingScan() {
        if (! this.patrolLogId || this.cancellingScan) {
            return;
        }

        this.cancellingScan = true;
        this.cancelScanError = '';

        try {
            const body = JSON.stringify({ patrol_log_id: this.patrolLogId });
            let response = await fetch(this.cancelScanUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfJsonHeaders(),
                body,
            });

            if (response.status === 419 && await refreshCsrfToken(this.csrfRefreshUrl)) {
                response = await fetch(this.cancelScanUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: csrfJsonHeaders(),
                    body,
                });
            }

            const data = await response.json().catch(() => ({}));

            if (! response.ok) {
                throw new Error(data.message || 'The pending scan could not be cancelled.');
            }

            this.resetAfterPendingScanCancelled(data.message || 'Pending scan cancelled. Scan your RFID again when ready.');
        } catch (error) {
            this.cancelScanError = error?.message || 'The pending scan could not be cancelled.';
        } finally {
            this.cancellingScan = false;
        }
    },

    clearChecklistPhotos() {
        Object.keys(this.checklistPhotoPreviews).forEach((field) => this.removeChecklistPhoto(field));
        this.checklistPhotoError = '';
    },

    resetAfterPendingScanCancelled(message) {
        this.stopCamera();
        this.clearAreaSelfieFields();
        this.clearChecklistPhotos();
        this.clearIncidentReport();
        this.pendingScan = null;
        this.patrolLogId = '';
        this.scanMessage = message;
        this.verificationMessage = '';
        this.areaSelfieCameraOpen = false;
        this.checklistModalOpen = false;
        this.incidentModalOpen = false;
        this.cancelScanModalOpen = false;
        this.cancelScanError = '';

        if (this.pollingTimer) {
            clearInterval(this.pollingTimer);
            this.pollingTimer = null;
        }

        this.startPolling();
    },

    checklistPhotoCount() {
        return Object.keys(this.checklistPhotoPreviews).length;
    },

    checklistPhotoInput(field) {
        return this.$refs[`checklistPhoto_${field}`];
    },

    checklistStatus(field) {
        return document.querySelector(`input[name="checklist_statuses[${field}]"]:checked`)?.value || 'normal';
    },

    isChecklistIssue(field) {
        return this.checklistStatus(field) === 'issue';
    },

    handleChecklistStatusChange(field, event) {
        this.checklistPhotoError = '';

        if (event?.target?.value !== 'issue') {
            this.removeChecklistPhoto(field);
        }
    },

    takeChecklistPhoto(field) {
        if (this.submittingPatrol || ! this.isChecklistIssue(field)) {
            return;
        }

        this.checklistPhotoInput(field)?.click();
    },

    async updateChecklistPhoto(field, event) {
        this.imageCompressionBusy = true;
        this.imageCompressionMessage = 'Preparing photo...';

        try {
            await replaceInputImagesWithCompressedCopies(event.target, {
                maxSize: 1280,
                quality: 0.72,
            });
        } finally {
            this.imageCompressionBusy = false;
            this.imageCompressionMessage = '';
        }

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
        this.$nextTick(() => document.getElementById('doors_locked_normal')?.focus());
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

    async prepareIncidentImages(event) {
        this.imageCompressionBusy = true;
        this.imageCompressionMessage = 'Preparing selected photos...';

        try {
            await replaceInputImagesWithCompressedCopies(event?.target, {
                maxSize: 1280,
                quality: 0.72,
            });
        } finally {
            this.imageCompressionBusy = false;
            this.imageCompressionMessage = '';
        }

        return this.updateIncidentImageCount(event);
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
        if (this.imageCompressionBusy) {
            event.preventDefault();
            this.verificationMessage = 'Please wait while photos are being prepared.';
            return;
        }

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
    rfidEnrollmentLatestUrl: config.rfidEnrollmentLatestUrl || '',
    rfidEnrollmentInputId: '',
    rfidEnrollmentStartedAt: '',
    rfidEnrollmentMessage: '',
    rfidEnrollmentBusy: false,
    rfidEnrollmentTimer: null,
    rfidEnrollmentTimeoutTimer: null,

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

        this.stopRfidEnrollment();
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
        this.stopRfidEnrollment();
        this.updateBodyScrollLock();
    },

    openCreateGuardModal() {
        this.editModalOpen = false;
        this.editGuardId = '';
        this.deleteModalOpen = false;
        this.recordModalOpen = false;
        this.stopRfidEnrollment();
        this.createModalOpen = true;
        this.updateBodyScrollLock();
        this.$nextTick(() => this.$refs.createGuardFirstField?.focus());
    },

    closeCreateGuardModal() {
        this.createModalOpen = false;
        this.stopRfidEnrollment();
        this.updateBodyScrollLock();
    },

    openEditGuardModal(guardId) {
        this.createModalOpen = false;
        this.recordModalOpen = false;
        this.deleteModalOpen = false;
        this.stopRfidEnrollment();
        this.editGuardId = String(guardId || '');
        this.editModalOpen = Boolean(this.editGuardId);
        this.updateBodyScrollLock();
        this.$nextTick(() => this.focusEditGuardField());
    },

    closeEditGuardModal() {
        this.editModalOpen = false;
        this.editGuardId = '';
        this.stopRfidEnrollment();
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
        this.stopRfidEnrollment();
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
        this.stopRfidEnrollment();
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

    startRfidEnrollment(inputId) {
        if (! this.rfidEnrollmentLatestUrl || ! inputId) {
            this.rfidEnrollmentMessage = 'RFID enrollment is unavailable right now.';
            return;
        }

        this.stopRfidEnrollment();
        this.rfidEnrollmentInputId = inputId;
        this.rfidEnrollmentStartedAt = new Date().toISOString();
        this.rfidEnrollmentMessage = 'Waiting for card tap on enrollment reader...';
        this.rfidEnrollmentBusy = true;

        this.pollRfidEnrollment();
        this.rfidEnrollmentTimer = window.setInterval(() => this.pollRfidEnrollment(), 1200);
        this.rfidEnrollmentTimeoutTimer = window.setTimeout(() => {
            if (this.rfidEnrollmentBusy && this.rfidEnrollmentInputId === inputId) {
                this.rfidEnrollmentBusy = false;
                this.clearRfidEnrollmentTimers();
                this.rfidEnrollmentMessage = 'No card captured. Tap Scan Card to try again.';
            }
        }, 45000);
    },

    async pollRfidEnrollment() {
        if (! this.rfidEnrollmentBusy || ! this.rfidEnrollmentInputId) {
            return;
        }

        try {
            const url = new URL(this.rfidEnrollmentLatestUrl, window.location.origin);
            url.searchParams.set('since', this.rfidEnrollmentStartedAt);

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json().catch(() => ({}));

            if (! response.ok) {
                throw new Error(data.message || 'RFID enrollment scan could not be checked.');
            }

            if (data.rfid_uid) {
                this.applyRfidEnrollmentUid(data.rfid_uid, data.device_uid);
            }
        } catch {
            this.rfidEnrollmentBusy = false;
            this.clearRfidEnrollmentTimers();
            this.rfidEnrollmentMessage = 'Could not check the enrollment reader right now.';
        }
    },

    applyRfidEnrollmentUid(rfidUid, deviceUid = null) {
        const input = document.getElementById(this.rfidEnrollmentInputId);

        if (input) {
            input.value = rfidUid;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            input.focus();
        }

        this.rfidEnrollmentBusy = false;
        this.clearRfidEnrollmentTimers();
        this.rfidEnrollmentMessage = deviceUid
            ? `Captured ${rfidUid} from ${deviceUid}.`
            : `Captured ${rfidUid}.`;
    },

    stopRfidEnrollment() {
        this.clearRfidEnrollmentTimers();
        this.rfidEnrollmentBusy = false;
        this.rfidEnrollmentInputId = '';
        this.rfidEnrollmentStartedAt = '';
        this.rfidEnrollmentMessage = '';
    },

    clearRfidEnrollmentTimers() {
        if (this.rfidEnrollmentTimer) {
            window.clearInterval(this.rfidEnrollmentTimer);
            this.rfidEnrollmentTimer = null;
        }

        if (this.rfidEnrollmentTimeoutTimer) {
            window.clearTimeout(this.rfidEnrollmentTimeoutTimer);
            this.rfidEnrollmentTimeoutTimer = null;
        }
    },

    isRfidEnrollmentActive(inputId) {
        return this.rfidEnrollmentBusy && this.rfidEnrollmentInputId === inputId;
    },

    rfidEnrollmentStatus(inputId) {
        return this.rfidEnrollmentInputId === inputId ? this.rfidEnrollmentMessage : '';
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
