import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

interface AppLogoProps {
    className?: string;
}

export default function AppLogo({ className }: AppLogoProps) {
    return (
        <AppLogoIcon
            className={cn(
                'h-5 w-auto dark:brightness-0 dark:invert',
                className,
            )}
        />
    );
}
