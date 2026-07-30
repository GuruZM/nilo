interface AppLogoIconProps {
    className?: string;
}

export default function AppLogoIcon({ className }: AppLogoIconProps) {
    return (
        <img
            src="/logo.svg"
            alt="Nilo"
            className={className}
            style={{ objectFit: 'contain' }}
        />
    );
}
