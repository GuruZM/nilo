import { cn } from '@/lib/utils';

interface NiloSpinnerProps {
    /** Overall diameter of the spinner in pixels. */
    size?: number;
    /** Duration of one full orbit, in seconds. */
    speed?: number;
    className?: string;
}

/**
 * Brand loader echoing the Nilo favicon: a steady blue "O" with the red
 * accent dot orbiting around it to signal loading.
 */
export default function NiloSpinner({
    size = 20,
    speed = 0.9,
    className,
}: NiloSpinnerProps) {
    const ring = Math.max(2, Math.round(size * 0.12));
    const dot = Math.max(3, Math.round(size * 0.28));

    return (
        <span
            role="status"
            aria-label="Loading"
            className={cn('relative inline-flex shrink-0', className)}
            style={{ width: size, height: size }}
        >
            {/* The steady blue "O". */}
            <span
                aria-hidden
                className="absolute inset-0 rounded-full"
                style={{ border: `${ring}px solid #1a4b8c` }}
            />

            {/* The red dot orbiting the O. */}
            <span
                aria-hidden
                className="absolute inset-0 animate-spin motion-reduce:animate-none"
                style={{ animationDuration: `${speed}s` }}
            >
                <span
                    className="absolute top-0 left-1/2 rounded-full"
                    style={{
                        width: dot,
                        height: dot,
                        backgroundColor: '#ff5757',
                        transform: 'translate(-50%, -50%)',
                    }}
                />
            </span>
        </span>
    );
}
