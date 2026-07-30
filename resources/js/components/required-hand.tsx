import { motion } from 'framer-motion';

/**
 * Calls out the field that blocked a wizard step. The hand points downwards, so
 * it renders directly above the input it refers to.
 */
export default function RequiredHand({
    show,
    message,
}: {
    show: boolean;
    message: string;
}) {
    if (!show) {
        return null;
    }

    return (
        /**
         * Zero height, so the hand overlays the label row rather than pushing
         * the field down. It sits above the control it flags, matching how the
         * welcome email places the same illustration. The message is announced
         * rather than drawn — the toast already carries it, and rendering it
         * here would collide with the label in narrow columns.
         */
        <div className="pointer-events-none relative z-10 h-0" role="alert">
            <motion.img
                src="/pointer-hand.svg"
                alt=""
                aria-hidden
                width={36}
                height={36}
                initial={{ opacity: 0, y: -6 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.2, ease: 'easeOut' }}
                className="absolute right-1 bottom-0 h-9 w-9 max-w-none select-none"
            />
            <span className="sr-only">{message}</span>
        </div>
    );
}
