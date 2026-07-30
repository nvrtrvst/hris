import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-surface pt-6 sm:justify-center sm:pt-0">
            <div>
                <Link href="/">
                    <ApplicationLogo className="h-20 w-20 fill-current text-text-muted" />
                </Link>
            </div>

            <div className="mt-6 w-full card p-6 sm:max-w-md">
                {children}
            </div>
        </div>
    );
}
