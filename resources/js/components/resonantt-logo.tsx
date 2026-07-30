interface ResonanttLogoProps {
    className?: string;
}

export default function ResonanttLogo({ className }: ResonanttLogoProps) {
    return (
        <img
            src="/reso.png"
            alt="Resonantt"
            className={className}
            style={{ objectFit: 'contain' }}
        />
    );
}
