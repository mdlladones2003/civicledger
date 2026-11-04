import { useEffect, useRef, useState, type FormEvent } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { Camera, CameraOff } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { csrfToken, Panel, PageHeading } from '@/components/app/Primitives';
import { AppShell } from '@/components/app/AppShell';
import { date, text } from '@/lib/app';
import type { SharedProps } from '@/types/app';

type BarcodeDetectorResult = {
    rawValue: string;
};

type BarcodeDetectorConstructor = new (options: { formats: string[] }) => {
    detect(source: HTMLVideoElement): Promise<BarcodeDetectorResult[]>;
};

type WindowWithBarcodeDetector = Window & {
    BarcodeDetector?: BarcodeDetectorConstructor;
};

export default function AttendanceScan() {
    const { errors, flash } = usePage<SharedProps>().props;
    const { data, setData, post, processing, errors: formErrors, clearErrors } = useForm({ token: '' });
    const [cameraActive, setCameraActive] = useState(false);
    const [cameraError, setCameraError] = useState('');
    const [challengeError, setChallengeError] = useState('');
    const [remainingSeconds, setRemainingSeconds] = useState<number | null>(null);
    const [challengeDuration, setChallengeDuration] = useState<number | null>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const scanTimeoutRef = useRef<number | null>(null);
    const scanGenerationRef = useRef(0);

    useEffect(() => {
        if (remainingSeconds === null || remainingSeconds <= 0) {
            return;
        }

        const timer = window.setTimeout(() => {
            setRemainingSeconds((seconds) => seconds === null ? null : Math.max(0, seconds - 1));
        }, 1000);

        return () => window.clearTimeout(timer);
    }, [remainingSeconds]);

    useEffect(() => () => {
        scanGenerationRef.current += 1;
        if (scanTimeoutRef.current !== null) {
            window.clearTimeout(scanTimeoutRef.current);
        }
        streamRef.current?.getTracks().forEach((track) => track.stop());
    }, []);

    function stopCamera() {
        scanGenerationRef.current += 1;
        if (scanTimeoutRef.current !== null) {
            window.clearTimeout(scanTimeoutRef.current);
            scanTimeoutRef.current = null;
        }
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        if (videoRef.current) {
            videoRef.current.srcObject = null;
        }
        setCameraActive(false);
    }

    async function checkChallenge(token: string) {
        const csrf = csrfToken();

        if (!csrf) {
            setChallengeError('The security token is missing. Refresh the page and scan again.');
            return;
        }

        try {
            const response = await fetch('/attendance/scan/challenge', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ token }),
            });
            const result: unknown = await response.json();

            if (typeof result !== 'object' || result === null) {
                throw new Error('The server returned an invalid QR challenge response.');
            }

            const payload = result as Record<string, unknown>;

            if (!response.ok) {
                throw new Error(typeof payload.message === 'string'
                    ? payload.message
                    : 'This QR challenge could not be verified. Scan the current code again.');
            }

            if (typeof payload.remaining_seconds !== 'number' || !Number.isInteger(payload.remaining_seconds)) {
                throw new Error('The server did not return the QR challenge expiry time. Scan the current code again.');
            }

            setRemainingSeconds(payload.remaining_seconds);
            setChallengeDuration(payload.remaining_seconds);
        } catch (error) {
            setRemainingSeconds(null);
            setChallengeDuration(null);
            setChallengeError(error instanceof Error
                ? error.message
                : 'The QR challenge could not be checked. Check your connection and scan again.');
        }
    }

    async function startCamera() {
        const detectorConstructor = (window as WindowWithBarcodeDetector).BarcodeDetector;

        if (!detectorConstructor) {
            setCameraError('QR scanning is not supported in this browser. Use Chrome on Android or enter the token manually.');
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setCameraError('Camera access requires a secure connection and a supported browser. Enter the token manually instead.');
            return;
        }

        stopCamera();
        setCameraError('');
        setChallengeError('');
        setRemainingSeconds(null);
        setChallengeDuration(null);

        const generation = scanGenerationRef.current;

        try {
            const detector = new detectorConstructor({ formats: ['qr_code'] });
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: 'environment' } },
            });

            if (generation !== scanGenerationRef.current) {
                stream.getTracks().forEach((track) => track.stop());
                return;
            }

            streamRef.current = stream;

            if (!videoRef.current) {
                throw new Error('The camera preview could not be opened. Try again or enter the token manually.');
            }

            videoRef.current.srcObject = stream;
            await videoRef.current.play();
            setCameraActive(true);

            const scanFrame = async () => {
                if (generation !== scanGenerationRef.current || !videoRef.current) {
                    return;
                }

                try {
                    const [result] = await detector.detect(videoRef.current);

                    if (generation !== scanGenerationRef.current) {
                        return;
                    }

                    if (result?.rawValue.trim()) {
                        const token = result.rawValue.trim();
                        stopCamera();
                        setData('token', token);
                        clearErrors();
                        await checkChallenge(token);
                        return;
                    }

                    scanTimeoutRef.current = window.setTimeout(() => {
                        void scanFrame();
                    }, 250);
                } catch {
                    if (generation !== scanGenerationRef.current) {
                        return;
                    }

                    stopCamera();
                    setCameraError('The camera could not read this QR code. Check camera permissions and try again.');
                }
            };

            void scanFrame();
        } catch (error) {
            stopCamera();
            if (error instanceof DOMException && error.name === 'NotAllowedError') {
                setCameraError('Camera permission was denied. Allow camera access in your browser settings or enter the token manually.');
            } else if (error instanceof DOMException && error.name === 'NotFoundError') {
                setCameraError('No camera was found on this device. Enter the token manually instead.');
            } else if (error instanceof Error) {
                setCameraError(error.message);
            } else {
                setCameraError('The camera could not be started. Check browser permissions and try again.');
            }
        }
    }

    function submitToken(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        clearErrors();
        post('/attendance/scan', { preserveScroll: true });
    }

    function updateToken(token: string) {
        setData('token', token);
        setRemainingSeconds(null);
        setChallengeDuration(null);
        setChallengeError('');
    }

    const tokenError = formErrors.token ?? errors?.token;
    const challengeExpired = remainingSeconds === 0;
    const progress = remainingSeconds !== null && challengeDuration
        ? Math.max(0, (remainingSeconds / challengeDuration) * 100)
        : 0;

    return (
        <AppShell title="Scan attendance">
            <div className="mx-auto grid w-full max-w-xl gap-6">
                <PageHeading
                    eyebrow="Attendance"
                    title="Check in to service"
                    description="Scan the current QR challenge with your camera, or enter its token manually."
                />
                {flash?.scan && (
                    <Alert variant="success" role="status">
                        <AlertTitle>Attendance updated</AlertTitle>
                        <AlertDescription>
                            {text(flash.scan.action)} · {date(flash.scan.at, { dateStyle: 'medium', timeStyle: 'short' })}
                        </AlertDescription>
                    </Alert>
                )}
                {(tokenError || flash?.scan_reason) && (
                    <Alert variant="destructive">
                        <AlertTitle>Check-in could not be completed</AlertTitle>
                        <AlertDescription>{tokenError ?? flash?.scan_reason}</AlertDescription>
                    </Alert>
                )}
                <Panel title="Scan QR challenge">
                    <div className="grid gap-4">
                        <video
                            ref={videoRef}
                            autoPlay
                            muted
                            playsInline
                            aria-label="Camera preview for scanning an attendance QR code"
                            className={cameraActive ? 'aspect-video w-full rounded-lg bg-foreground object-cover' : 'hidden'}
                        />
                        {!cameraActive && (
                            <div className="grid min-h-36 place-items-center rounded-lg bg-muted text-muted-foreground" aria-hidden="true">
                                <Camera className="size-8" />
                            </div>
                        )}
                        <div className="flex flex-wrap items-center gap-3">
                            <Button type="button" onClick={startCamera} disabled={cameraActive || processing}>
                                <Camera />
                                Open camera
                            </Button>
                            {cameraActive && (
                                <Button type="button" variant="outline" onClick={stopCamera}>
                                    <CameraOff />
                                    Stop camera
                                </Button>
                            )}
                        </div>
                        {cameraError && <p className="text-sm text-destructive" role="alert">{cameraError}</p>}
                        {challengeError && <p className="text-sm text-destructive" role="alert">{challengeError}</p>}
                        {remainingSeconds !== null && (
                            <div className="grid gap-2">
                                <p className="text-sm text-muted-foreground" role="status" aria-live="polite">
                                    {challengeExpired
                                        ? 'This code has expired. Ask the coordinator to show the current code.'
                                        : `This QR challenge expires in ${remainingSeconds} seconds. Submit it before it expires.`}
                                </p>
                                <div
                                    className="h-2 overflow-hidden rounded-full bg-muted"
                                    role="progressbar"
                                    aria-label="QR challenge time remaining"
                                    aria-valuemin={0}
                                    aria-valuemax={challengeDuration ?? 0}
                                    aria-valuenow={remainingSeconds}
                                >
                                    <div
                                        className="h-full rounded-full bg-civic transition-[width] duration-1000 ease-linear"
                                        style={{ width: `${progress}%` }}
                                    />
                                </div>
                            </div>
                        )}
                    </div>
                </Panel>
                <Panel title="Enter token manually">
                    <form onSubmit={submitToken} className="grid gap-3">
                        <Label className="grid gap-2 text-sm font-medium text-foreground">
                            <span>QR challenge token</span>
                            <Textarea
                                name="token"
                                rows={4}
                                autoComplete="off"
                                required
                                value={data.token}
                                onChange={(event) => updateToken(event.target.value)}
                                className="break-all text-sm"
                                aria-describedby="scan-help"
                            />
                        </Label>
                        <p id="scan-help" className="text-xs leading-5 text-muted-foreground">
                            A scanned token's remaining time is shown above. Expired tokens must be rescanned from the current code.
                        </p>
                        <Button type="submit" size="lg" className="min-h-11 w-fit" disabled={processing || challengeExpired || !data.token.trim()}>
                            {processing ? 'Checking in…' : 'Submit secure check-in'}
                        </Button>
                    </form>
                </Panel>
            </div>
        </AppShell>
    );
}
