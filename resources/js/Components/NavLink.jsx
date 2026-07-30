import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-primary text-text-primary'
                    : 'border-transparent text-text-secondary hover:border-border-dark hover:text-text-primary') +
                className
            }
        >
            {children}
        </Link>
    );
}
