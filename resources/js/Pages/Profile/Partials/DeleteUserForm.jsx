import Modal from '@/Components/Modal';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, Lock, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export default function DeleteUserForm({ className = '' }) {
    const [confirming, setConfirming] = useState(false);
    const passwordInput = useRef();

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({ password: '' });

    const closeModal = () => {
        setConfirming(false);
        clearErrors();
        reset();
    };

    const deleteUser = (e) => {
        e.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    useEffect(() => {
        if (confirming) {
            const t = setTimeout(() => passwordInput.current?.focus(), 100);
            return () => clearTimeout(t);
        }
    }, [confirming]);

    return (
        <section className={`space-y-5 ${className}`}>
            <header className="border-b border-danger/30 pb-3">
                <h3 className="flex items-center gap-2 text-base font-bold text-danger">
                    <Trash2 className="h-4 w-4" />
                    Hapus Akun
                </h3>
                <p className="mt-1 text-xs text-text-secondary">
                    Setelah akun dihapus, semua data Anda akan dihapus permanen.
                    Pastikan sudah mengunduh data yang ingin dipertahankan.
                </p>
            </header>

            <div className="rounded-lg border border-danger/40 bg-danger/5 p-4">
                <div className="flex items-start gap-3">
                    <AlertTriangle className="mt-0.5 h-5 w-5 flex-shrink-0 text-danger" />
                    <div className="text-sm">
                        <p className="font-semibold text-danger">
                            Tindakan ini tidak dapat dibatalkan.
                        </p>
                        <p className="mt-1 text-text-secondary">
                            Semua data pegawai, jadwal, presensi, dan penggajian
                            Anda akan dihapus secara permanen dari server.
                        </p>
                    </div>
                </div>
            </div>

            <button
                type="button"
                onClick={() => setConfirming(true)}
                className="inline-flex items-center justify-center gap-2 rounded-lg border border-danger bg-white px-5 py-2.5 text-sm font-semibold text-danger shadow-sm transition hover:bg-danger hover:text-white focus:outline-none focus:ring-2 focus:ring-danger/30"
            >
                <Trash2 className="h-4 w-4" />
                Hapus Akun Saya
            </button>

            <Modal show={confirming} onClose={closeModal} maxWidth="md">
                <form onSubmit={deleteUser} className="p-6">
                    <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-danger/10">
                        <AlertTriangle className="h-6 w-6 text-danger" />
                    </div>
                    <h2 className="text-center text-lg font-bold text-text-primary">
                        Yakin ingin menghapus akun?
                    </h2>
                    <p className="mt-2 text-center text-sm text-text-secondary">
                        Masukkan password untuk konfirmasi. Data yang sudah
                        dihapus tidak dapat dipulihkan.
                    </p>

                    <div className="mt-5">
                        <label
                            htmlFor="delete_password"
                            className="mb-1.5 block text-sm font-medium text-text-primary"
                        >
                            Password
                        </label>
                        <div className="relative">
                            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <Lock className="h-4 w-4" />
                            </span>
                            <input
                                id="delete_password"
                                ref={passwordInput}
                                type="password"
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                                className="block w-full rounded-lg border border-border bg-white pl-10 pr-3 py-2.5 text-sm text-text-primary shadow-sm placeholder:text-gray-400 focus:border-danger focus:outline-none focus:ring-2 focus:ring-danger/20"
                                placeholder="Password Anda"
                            />
                        </div>
                        {errors.password && (
                            <p className="mt-1.5 text-xs text-danger">
                                {errors.password}
                            </p>
                        )}
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={closeModal}
                            disabled={processing}
                            className="inline-flex items-center justify-center rounded-lg border border-border bg-white px-4 py-2.5 text-sm font-semibold text-text-primary shadow-sm transition hover:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={processing || !data.password}
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-danger px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-danger/90 focus:outline-none focus:ring-2 focus:ring-danger/30 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing ? 'Menghapus...' : 'Hapus Akun'}
                        </button>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
