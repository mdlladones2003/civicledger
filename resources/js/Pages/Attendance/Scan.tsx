import { useEffect, useRef, useState, type FormEvent } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Camera, CameraOff } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Panel, PageHeading } from '@/components/app/Primitives';
import { AppShell } from '@/components/app/AppShell';
import { date } from '@/lib/app';
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

function describeScan(action: string | undefined, at: string | undefined): { title: string; message: string } {
    const time = date(at, { timeStyle: 'short' });

    switch (action) {
        case 'check_in':
            return { title: 'Attendance recorded', message: `Checked in at ${time}.` };
        case 'check_out':
            return { title: 'Attendance recorded', message: `Checked out at ${time}.` };
        case 'already_checked_out':
            return { title: 'Already recorded', message: `You already checked out at ${time}.` };
        default:
            return { title: 'Already recorded', message: `You already checked in at ${time}.` };
    }
}

export default function AttendanceScan() {
    const { flash } = usePage<SharedProps>().props;
    const [manualToken, setManualToken] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [requestError, setRequestError] = useState('');
    const [cameraActive, setCameraActive] = useState(false);
    const [cameraError, setCameraError] = useState('');
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const scanTimeoutRef = useRef<number | null>(null);
    const scanGenerationRef = useRef(0);

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

    /** A new attempt replaces the last result, so an old message can't be read as the answer to this one. */
    function clearLastResult() {
        setRequestError('');
        router.replace({
            preserveScroll: true,
            preserveState: true,
            props: (props) => ({ ...props, errors: {}, flash: {} }),
        });
    }

    /** No page visit follows a failed request, so Inertia won't bring the message into view. */
    function showRequestError(message: string) {
        setRequestError(message);
        window.scrollTo({ top: 0 });
    }

    function submitToken(token: string) {
        clearLastResult();
        setIsSubmitting(true);

        router.post('/attendance/scan', { token }, {
            onSuccess: () => setManualToken(''),
            onHttpException: (response) => {
                showRequestError(response.status === 429
                    ? 'Too many scans. Wait a minute, then scan again.'
                    : 'Your attendance could not be recorded. Refresh this page and scan again, or ask your coordinator for a manual entry.');

                return false;
            },
            onNetworkError: () => {
                showRequestError('We couldn’t reach CivicLedger. Check your connection and scan again, or ask your coordinator for a manual entry.');

                return false;
            },
            onFinish: () => setIsSubmitting(false),
        });
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
        clearLastResult();

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
                        stopCamera();
                        submitToken(result.rawValue.trim());
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

    function submitManualToken(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        submitToken(manualToken.trim());
    }

    const scanResult = flash?.scan ? describeScan(flash.scan.action, flash.scan.at) : null;

    return (
        <AppShell title="Scan attendance">
            <div className="mx-auto grid w-full max-w-xl gap-6">
                <PageHeading
                    eyebrow="Attendance"
                    title="Check in to service"
                    description="Scan the current QR challenge with your camera, or enter its token manually."
                />
                {scanResult && (
                    <Alert variant="success" role="status">
                        <AlertTitle>{scanResult.title}</AlertTitle>
                        <AlertDescription>{scanResult.message}</AlertDescription>
                    </Alert>
                )}
                {/* AppShell already shows the reason when the server rejects a scan. This covers a request that got no usable answer. */}
                {requestError && (
                    <Alert variant="destructive">
                        <AlertTitle>Scan not recorded</AlertTitle>
                        <AlertDescription>{requestError}</AlertDescription>
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
                            <Button type="button" onClick={startCamera} disabled={cameraActive || isSubmitting}>
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
                        {isSubmitting && <p className="text-sm text-muted-foreground" role="status">Recording your attendance…</p>}
                    </div>
                </Panel>
                <Panel title="Enter token manually">
                    <form onSubmit={submitManualToken} className="grid gap-3">
                        <Label className="grid gap-2 text-sm font-medium text-foreground">
                            <span>QR challenge token</span>
                            <Textarea
                                name="token"
                                rows={4}
                                autoComplete="off"
                                required
                                value={manualToken}
                                onChange={(event) => setManualToken(event.target.value)}
                                className="break-all text-sm"
                                aria-describedby="scan-help"
                            />
                        </Label>
                        <p id="scan-help" className="text-xs leading-5 text-muted-foreground">
                            Expired tokens must be replaced with the current code.
                        </p>
                        <Button type="submit" size="lg" className="min-h-11 w-fit" disabled={isSubmitting || !manualToken.trim()}>
                            {isSubmitting ? 'Submitting…' : 'Submit secure check-in'}
                        </Button>
                    </form>
                </Panel>
            </div>
        </AppShell>
    );
}
