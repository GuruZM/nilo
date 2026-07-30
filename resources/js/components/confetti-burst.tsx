import { motion, useReducedMotion } from 'framer-motion';
import * as React from 'react';

/** Brand blues with the red accent, plus two warm notes to lift the burst. */
const COLORS = ['#00417d', '#1a4b8c', '#ff5757', '#f59e0b', '#10b981'];

interface ConfettiBurstProps {
    /** Fires the burst once when this becomes true. */
    active: boolean;
    pieces?: number;
}

/**
 * A one-shot confetti burst for genuine milestones.
 *
 * Deliberately dependency-free — it is a handful of absolutely positioned
 * pieces animated by framer-motion, which the app already ships. Rendering is
 * client-only and skipped entirely when the viewer prefers reduced motion.
 */
export default function ConfettiBurst({
    active,
    pieces = 70,
}: ConfettiBurstProps) {
    const reduceMotion = useReducedMotion();
    const [visible, setVisible] = React.useState(false);

    React.useEffect(() => {
        if (!active || reduceMotion) {
            return;
        }

        setVisible(true);

        /** Long enough for the slowest piece to clear the viewport. */
        const timer = window.setTimeout(() => setVisible(false), 3600);

        return () => window.clearTimeout(timer);
    }, [active, reduceMotion]);

    /**
     * Randomised once. Safe against hydration mismatch because nothing renders
     * until the effect above has run on the client.
     */
    const confetti = React.useMemo(
        () =>
            Array.from({ length: pieces }, (_, index) => ({
                id: index,
                left: Math.random() * 100,
                drift: (Math.random() - 0.5) * 240,
                rotate: (Math.random() - 0.5) * 720,
                width: 6 + Math.random() * 6,
                height: 10 + Math.random() * 8,
                color: COLORS[index % COLORS.length],
                delay: Math.random() * 0.5,
                duration: 2.2 + Math.random() * 1.1,
                round: Math.random() > 0.7,
            })),
        [pieces],
    );

    if (!visible) {
        return null;
    }

    return (
        <div
            aria-hidden
            className="pointer-events-none fixed inset-0 z-100 overflow-hidden"
        >
            {confetti.map((piece) => (
                <motion.span
                    key={piece.id}
                    className="absolute top-0 block"
                    style={{
                        left: `${piece.left}%`,
                        width: piece.width,
                        height: piece.height,
                        backgroundColor: piece.color,
                        borderRadius: piece.round ? '9999px' : '2px',
                    }}
                    initial={{ y: -40, x: 0, rotate: 0, opacity: 0 }}
                    animate={{
                        y: '105vh',
                        x: piece.drift,
                        rotate: piece.rotate,
                        opacity: [0, 1, 1, 0],
                    }}
                    transition={{
                        duration: piece.duration,
                        delay: piece.delay,
                        ease: 'easeIn',
                        opacity: {
                            duration: piece.duration,
                            delay: piece.delay,
                            times: [0, 0.1, 0.75, 1],
                        },
                    }}
                />
            ))}
        </div>
    );
}
