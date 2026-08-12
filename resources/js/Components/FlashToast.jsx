import { useState, useEffect } from 'react';
import { usePage } from '@inertiajs/react';

export default function FlashToast() {
    const { flash } = usePage().props;
    const [show, setShow] = useState(false);
    const [toastMessage, setToastMessage] = useState('');
    const [toastType, setToastType] = useState('success');
    const [showPassword, setShowPassword] = useState(false);

    useEffect(() => {
        if (flash?.generated_password) {
            setToastMessage(`Pegawai berhasil ditambahkan. Password: ${flash.generated_password}`);
            setToastType('success');
            setShowPassword(true);
            setShow(true);
        } else if (flash?.message) {
            setToastMessage(flash.message);
            setToastType('success');
            setShowPassword(false);
            setShow(true);
        } else if (flash?.error) {
            setToastMessage(flash.error);
            setToastType('error');
            setShowPassword(false);
            setShow(true);
        }
    }, [flash?.message, flash?.error, flash?.generated_password]);

    const copyPassword = () => {
        if (flash?.generated_password) {
            navigator.clipboard.writeText(flash.generated_password);
        }
    };

    useEffect(() => {
        if (show) {
            const timer = setTimeout(() => setShow(false), showPassword ? 15000 : 4000);
            return () => clearTimeout(timer);
        }
    }, [show, showPassword]);

    // Live region SELALU di DOM agar screen reader mengumumkan pesan saat
    // muncul. Error memakai role=alert (assertive), lainnya role=status
    // (polite). Konten visual conditional di dalamnya.
    return (
        <div
            role={toastType === 'error' ? 'alert' : 'status'}
            aria-live={toastType === 'error' ? 'assertive' : 'polite'}
            aria-atomic="true"
        >
            {show && toastMessage && (
            <div 
                className={`toast-flash-top fixed left-4 right-4 z-[9999] shadow-toast rounded-xl overflow-hidden print:hidden transition-all duration-500 sm:left-auto sm:right-6 sm:w-full sm:max-w-sm ${show ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0'}`}
            >
                <div className={`${toastType === 'success' ? 'bg-success' : 'bg-danger'} p-4`}>
                <div className="flex items-start gap-3">
                    <svg className="w-5 h-5 text-white flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {toastType === 'success' ? (
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        ) : (
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        )}
                    </svg>
                    <div className="flex-1 min-w-0">
                        <p className="text-white text-sm font-medium">{toastMessage}</p>
                        {showPassword && flash?.generated_password && (
                            <button onClick={copyPassword} className="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/30 transition-colors">
                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                Salin Password
                            </button>
                        )}
                    </div>
                    <button onClick={() => setShow(false)} className="text-white/70 hover:text-white transition-colors flex-shrink-0">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div className="mt-2 h-0.5 bg-white/20 rounded-full overflow-hidden">
                    <div className="h-full bg-white/40 rounded-full" style={{ animation: `shrinkWidth ${showPassword ? '15s' : '4s'} linear forwards` }}></div>
                </div>
            </div>
                <style>{`
                    @keyframes shrinkWidth {
                        from { width: 100%; }
                        to { width: 0%; }
                    }
                `}</style>
            </div>
            )}
        </div>
    );
}
