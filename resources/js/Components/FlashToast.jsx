import { useState, useEffect } from 'react';
import { usePage } from '@inertiajs/react';

export default function FlashToast() {
    const { flash } = usePage().props;
    const [show, setShow] = useState(false);
    const [toastMessage, setToastMessage] = useState('');
    const [toastType, setToastType] = useState('success'); // 'success' | 'error'

    useEffect(() => {
        if (flash?.message) {
            setToastMessage(flash.message);
            setToastType('success');
            setShow(true);
        } else if (flash?.error) {
            setToastMessage(flash.error);
            setToastType('error');
            setShow(true);
        }
    }, [flash?.message, flash?.error]);

    useEffect(() => {
        if (show) {
            const timer = setTimeout(() => setShow(false), 4000);
            return () => clearTimeout(timer);
        }
    }, [show]);

    if (!show || !toastMessage) return null;

    const bgColor = toastType === 'success' 
        ? 'bg-emerald-500' 
        : 'bg-red-500';

    const icon = toastType === 'success' 
        ? (
            <svg className="w-5 h-5 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        )
        : (
            <svg className="w-5 h-5 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        );

    return (
        <div 
            className={`fixed top-6 right-6 z-[9999] max-w-sm w-full shadow-2xl rounded-xl overflow-hidden print:hidden transition-all duration-500 ${show ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0'}`}
        >
            <div className={`${bgColor} p-4 flex items-start gap-3`}>
                {icon}
                <p className="text-white text-sm font-medium flex-1">{toastMessage}</p>
                <button 
                    onClick={() => setShow(false)} 
                    className="text-white/70 hover:text-white transition-colors flex-shrink-0"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            {/* Progress bar */}
            <div className={`h-1 ${bgColor}`}>
                <div 
                    className="h-full bg-white/30 animate-shrink-width"
                    style={{ animation: 'shrinkWidth 4s linear forwards' }}
                ></div>
            </div>
            <style>{`
                @keyframes shrinkWidth {
                    from { width: 100%; }
                    to { width: 0%; }
                }
            `}</style>
        </div>
    );
}
