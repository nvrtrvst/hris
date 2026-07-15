import { useState } from 'react';
import { AlertTriangle } from 'lucide-react';

/**
 * Custom Confirm Dialog Component
 * Replacement untuk native confirm() dengan UX yang lebih konsisten
 *
 * @param {boolean} open - Dialog visibility state
 * @param {Function} onConfirm - Callback saat user konfirmasi
 * @param {Function} onCancel - Callback saat user batal
 * @param {string} title - Title dialog
 * @param {string} message - Confirmation message
 * @param {string} confirmText - Text tombol konfirmasi (default: "Konfirmasi")
 * @param {string} cancelText - Text tombol batal (default: "Batal")
 * @param {string} variant - Button style (default: "danger") | "warning" | "info"
 */
export function ConfirmDialog({
    open,
    onConfirm,
    onCancel,
    title,
    message,
    confirmText = "Konfirmasi",
    cancelText = "Batal",
    variant = "danger"
}) {
    if (!open) return null;

    const variantStyles = {
        danger: "bg-red-600 hover:bg-red-700 focus:ring-red-500",
        warning: "bg-amber-500 hover:bg-amber-600 focus:ring-amber-500",
        info: "bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500"
    };

    const iconColor = {
        danger: "text-red-600 bg-red-100",
        warning: "text-amber-600 bg-amber-100",
        info: "text-indigo-600 bg-indigo-100"
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" aria-hidden="true" />

            <div className="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 overflow-hidden">
                <div className="p-6">
                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full mb-4">
                        <AlertTriangle className={`h-6 w-6 ${iconColor[variant]?.split(' ')[0]}`} />
                    </div>

                    <h3 className="text-lg font-bold text-gray-900 text-center mb-2">
                        {title}
                    </h3>

                    <p className="text-sm text-gray-500 text-center mb-6">
                        {message}
                    </p>

                    <div className="flex space-x-3">
                        <button
                            type="button"
                            onClick={onCancel}
                            className="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            {cancelText}
                        </button>

                        <button
                            type="button"
                            onClick={onConfirm}
                            className={`flex-1 px-4 py-2.5 border border-transparent rounded-lg text-sm font-medium text-white ${variantStyles[variant]} focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500`}
                        >
                            {confirmText}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

/**
 * Custom Prompt Dialog Component
 * Replacement untuk native prompt() dengan UX yang lebih konsisten
 *
 * @param {boolean} open - Dialog visibility state
 * @param {Function} onConfirm - Callback saat user submit input
 * @param {Function} onCancel - Callback saat user batal
 * @param {string} title - Title dialog
 * @param {string} message - Prompt message
 * @param {string} placeholder - Input placeholder text
 * @param {string} defaultValue - Default input value
 */
export function PromptDialog({
    open,
    onConfirm,
    onCancel,
    title,
    message,
    placeholder = "",
    defaultValue = ""
}) {
    const [value, setValue] = useState(defaultValue);

    if (!open) return null;

    const handleSubmit = () => {
        onConfirm(value);
        setValue(defaultValue);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" aria-hidden="true" />

            <div className="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 overflow-hidden">
                <div className="p-6">
                    <h3 className="text-lg font-bold text-gray-900 text-center mb-2">
                        {title}
                    </h3>

                    <p className="text-sm text-gray-500 text-center mb-4">
                        {message}
                    </p>

                    <input
                        type="text"
                        value={value}
                        onChange={(e) => setValue(e.target.value)}
                        placeholder={placeholder}
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 mb-4"
                        autoFocus
                    />

                    <div className="flex space-x-3">
                        <button
                            type="button"
                            onClick={onCancel}
                            className="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Batal
                        </button>

                        <button
                            type="button"
                            onClick={handleSubmit}
                            className="flex-1 px-4 py-2.5 border border-transparent rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
