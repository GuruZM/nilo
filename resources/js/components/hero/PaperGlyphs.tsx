interface GlyphProps {
    className?: string;
}

export function PaperSheet({ className }: GlyphProps) {
    return (
        <svg viewBox="0 0 96 120" fill="none" aria-hidden className={className}>
            <path
                d="M12 6h50l26 26v78a6 6 0 0 1-6 6H12a6 6 0 0 1-6-6V12a6 6 0 0 1 6-6Z"
                fill="rgba(255,255,255,0.07)"
                stroke="rgba(255,255,255,0.45)"
                strokeWidth="2.5"
            />
            <path
                d="M62 6v20a6 6 0 0 0 6 6h20"
                fill="none"
                stroke="rgba(255,255,255,0.45)"
                strokeWidth="2.5"
            />
            <line
                x1="22"
                y1="52"
                x2="74"
                y2="52"
                stroke="rgba(255,255,255,0.3)"
                strokeWidth="3.5"
                strokeLinecap="round"
            />
            <line
                x1="22"
                y1="68"
                x2="74"
                y2="68"
                stroke="rgba(255,255,255,0.3)"
                strokeWidth="3.5"
                strokeLinecap="round"
            />
            <line
                x1="22"
                y1="84"
                x2="58"
                y2="84"
                stroke="rgba(255,255,255,0.3)"
                strokeWidth="3.5"
                strokeLinecap="round"
            />
        </svg>
    );
}

export function CheckedDocument({ className }: GlyphProps) {
    return (
        <svg
            viewBox="0 0 140 172"
            fill="none"
            aria-hidden
            className={className}
        >
            <path
                d="M16 8h76l32 32v116a8 8 0 0 1-8 8H16a8 8 0 0 1-8-8V16a8 8 0 0 1 8-8Z"
                fill="rgba(255,255,255,0.1)"
                stroke="rgba(255,255,255,0.6)"
                strokeWidth="3"
            />
            <path
                d="M92 8v24a8 8 0 0 0 8 8h24"
                fill="none"
                stroke="rgba(255,255,255,0.6)"
                strokeWidth="3"
            />
            <line
                x1="28"
                y1="70"
                x2="112"
                y2="70"
                stroke="rgba(255,255,255,0.4)"
                strokeWidth="4"
                strokeLinecap="round"
            />
            <line
                x1="28"
                y1="90"
                x2="112"
                y2="90"
                stroke="rgba(255,255,255,0.4)"
                strokeWidth="4"
                strokeLinecap="round"
            />
            <line
                x1="28"
                y1="110"
                x2="84"
                y2="110"
                stroke="rgba(255,255,255,0.4)"
                strokeWidth="4"
                strokeLinecap="round"
            />
            <circle cx="104" cy="132" r="22" fill="#7fadd8" />
            <path
                d="m94 132 7 7 14-14"
                fill="none"
                stroke="#001d3a"
                strokeWidth="4.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
