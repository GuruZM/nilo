import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { Eye, EyeOff, type LucideIcon } from 'lucide-react';
import * as React from 'react';

interface AuthFieldProps extends React.ComponentProps<'input'> {
    id: string;
    label: string;
    icon: LucideIcon;
    error?: string;
    hint?: string;
    labelAside?: React.ReactNode;
}

export default function AuthField({
    id,
    label,
    icon: Icon,
    error,
    hint,
    labelAside,
    type = 'text',
    className,
    onChange,
    ...props
}: AuthFieldProps): React.ReactElement {
    const [reveal, setReveal] = React.useState(false);
    const [filled, setFilled] = React.useState(
        Boolean(props.defaultValue ?? props.value),
    );
    const isPassword = type === 'password';
    const resolvedType = isPassword ? (reveal ? 'text' : 'password') : type;

    const handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
        setFilled(event.target.value.length > 0);
        onChange?.(event);
    };

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between">
                <Label
                    htmlFor={id}
                    className="text-sm font-medium text-foreground/90"
                >
                    {label}
                </Label>
                {labelAside}
            </div>

            <div className="group relative">
                <Icon
                    aria-hidden
                    className={cn(
                        'pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 transition-colors duration-200 group-focus-within:text-brand',
                        filled ? 'text-brand' : 'text-muted-foreground',
                    )}
                />
                <Input
                    id={id}
                    type={resolvedType}
                    aria-invalid={error ? true : undefined}
                    onChange={handleChange}
                    className={cn(
                        'h-12 rounded-xl border-border/70 bg-transparent pl-11 text-[15px] shadow-sm transition-all duration-200',
                        'hover:border-border',
                        'focus-visible:border-brand focus-visible:ring-[3px] focus-visible:ring-brand/20',
                        'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
                        isPassword && 'pr-11',
                        className,
                    )}
                    {...props}
                />
                {isPassword && (
                    <button
                        type="button"
                        tabIndex={-1}
                        onClick={() => setReveal((value) => !value)}
                        aria-label={reveal ? 'Hide password' : 'Show password'}
                        className="absolute top-1/2 right-3 -translate-y-1/2 rounded-md p-1 text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-brand/40 focus-visible:outline-none"
                    >
                        {reveal ? (
                            <EyeOff className="size-[18px]" />
                        ) : (
                            <Eye className="size-[18px]" />
                        )}
                    </button>
                )}
            </div>

            {error ? (
                <InputError message={error} />
            ) : hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
        </div>
    );
}
