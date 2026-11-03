import React from 'react';
import { avatarTone, initials } from '@/Utils/avatar';
import { cn } from '@/Components/AdminUI/lib/cn';

export default function Avatar({
    src,
    name = '',
    alt = '',
    className = 'h-10 w-10',
    textClassName = 'text-xs',
}) {
    const [failed, setFailed] = React.useState(false);

    React.useEffect(() => {
        setFailed(false);
    }, [src]);

    if (src && !failed) {
        return (
            <img
                src={src}
                alt={alt}
                loading="lazy"
                onError={() => setFailed(true)}
                className={cn('shrink-0 rounded-full object-cover', className)}
            />
        );
    }

    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full font-extrabold',
                avatarTone(name),
                className,
                textClassName,
            )}
        >
            {initials(name)}
        </span>
    );
}
