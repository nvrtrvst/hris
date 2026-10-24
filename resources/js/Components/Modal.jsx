import { useEffect } from 'react';
import {
    Dialog,
    DialogPanel,
    Transition,
    TransitionChild,
} from '@headlessui/react';

const SHEET_MAX_WIDTHS = ['lg', 'xl', '2xl', '3xl', '4xl'];

export default function Modal({
    children,
    show = false,
    maxWidth = '2xl',
    sheet,
    closeable = true,
    onClose = () => {},
}) {
    const close = () => {
        if (closeable) {
            onClose();
        }
    };

    // Konten panjang jadi bottom sheet di mobile; dialog konfirmasi kecil tetap di tengah.
    const sheetOnMobile = sheet ?? SHEET_MAX_WIDTHS.includes(maxWidth);

    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
        '3xl': 'sm:max-w-3xl',
        '4xl': 'sm:max-w-4xl',
    }[maxWidth];

    useEffect(() => {
        if (!show) return undefined;
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, [show]);

    return (
        <Transition show={show} leave="duration-200">
            <Dialog
                as="div"
                id="modal"
                className={`fixed inset-0 z-50 flex transform justify-center overflow-y-auto transition-all ${
                    sheetOnMobile ? 'items-end px-0 py-0 sm:items-start sm:px-0 sm:py-[10vh]' : 'items-start px-4 py-[10vh] sm:px-0'
                }`}
                onClose={close}
            >
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" />
                </TransitionChild>

                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enterTo="opacity-100 translate-y-0 sm:scale-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                    leaveTo="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <DialogPanel
                        className={`transform bg-white shadow-elevated transition-all sm:mx-auto sm:w-full ${
                            sheetOnMobile
                                ? 'mb-0 max-h-[90dvh] w-full overflow-y-auto overscroll-contain rounded-t-2xl pb-[env(safe-area-inset-bottom)] sm:mb-6 sm:max-h-[85vh] sm:rounded-xl sm:pb-0'
                                : 'mb-6 max-h-[85vh] overflow-y-auto rounded-xl'
                        } ${maxWidthClass}`}
                    >
                        {children}
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
