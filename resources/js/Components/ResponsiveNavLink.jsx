import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2.5 pe-4 ps-3 ${
                active
                    ? 'border-primary bg-primary-50 text-primary'
                    : 'border-transparent text-text-secondary hover:border-border-dark hover:bg-gray-50 hover:text-text-primary'
            } text-sm font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
