import Modal from '@/Components/Modal';
import { Button, Card } from '@/Components/AdminUI';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, ChevronDown, Lock, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export default function DeleteUserForm() {
    const [open, setOpen] = useState(false);
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
        if (!confirming) return undefined;
        const t = setTimeout(() => passwordInput.current?.focus(), 100);
        return () => clearTimeout(t);
    }, [confirming]);

    return (
        <Card className="border-danger/30 p-5 sm:p-7">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="flex items-center gap-2 text-lg font-bold text-text-primary">
                        <AlertTriangle className="h-5 w-5 text-danger" aria-hidden />
                        Zona Berbahaya
                    </h3>
                    <p className="mt-0.5 text-sm text-text-secondary">
                        Menghapus akun akan menghapus data pegawai, jadwal, presensi, dan penggajian secara permanen.
                    </p>
                </div>
                <Button
                    variant="secondary"
                    onClick={() => setOpen((v) => !v)}
                    aria-expanded={open}
                    aria-controls="zona-berbahaya"
                    className="shrink-0"
                >
                    <ChevronDown className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} />
                    {open ? 'Tutup' : 'Buka'}
                </Button>
            </div>

            <div id="zona-berbahaya" hidden={!open} className="mt-4 space-y-4 border-t border-border pt-4">
                <div className="flex items-start gap-3 rounded-lg border border-danger/40 bg-danger/5 p-4">
                    <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-danger" aria-hidden />
                    <div className="text-sm">
                        <p className="font-semibold text-danger">Tindakan ini tidak dapat dibatalkan.</p>
                        <p className="mt-1 text-text-secondary">
                            Pastikan Anda sudah mengunduh data yang ingin dipertahankan sebelum melanjutkan.
                        </p>
                    </div>
                </div>

                <Button variant="danger" onClick={() => setConfirming(true)}>
                    <Trash2 className="h-4 w-4" />
                    Hapus Akun Saya
                </Button>
            </div>

            <Modal show={confirming} onClose={closeModal} maxWidth="md">
                <form onSubmit={deleteUser} className="p-6">
                    <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-danger/10">
                        <AlertTriangle className="h-6 w-6 text-danger" aria-hidden />
                    </div>
                    <h2 className="text-center text-lg font-bold text-text-primary">
                        Yakin ingin menghapus akun?
                    </h2>
                    <p className="mt-2 text-center text-sm text-text-secondary">
                        Masukkan password untuk konfirmasi. Data yang sudah dihapus tidak dapat dipulihkan.
                    </p>

                    <div className="mt-5">
                        <label
                            htmlFor="delete_password"
                            className="mb-1.5 block text-sm font-medium text-text-primary"
                        >
                            Password
                        </label>
                        <div className="relative">
                            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-text-muted">
                                <Lock className="h-4 w-4" />
                            </span>
                            <input
                                id="delete_password"
                                ref={passwordInput}
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                aria-invalid={errors.password ? true : undefined}
                                aria-describedby={errors.password ? 'delete_password-error' : undefined}
                                className="block w-full rounded-lg border border-border bg-white pl-10 pr-3 py-2.5 text-sm text-text-primary shadow-sm placeholder:text-text-muted focus:border-danger focus:outline-none focus:ring-2 focus:ring-danger/20"
                                placeholder="Password Anda"
                            />
                        </div>
                        {errors.password && (
                            <p id="delete_password-error" className="mt-1.5 text-xs font-semibold text-danger">
                                {errors.password}
                            </p>
                        )}
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <Button variant="secondary" onClick={closeModal} disabled={processing}>
                            Batal
                        </Button>
                        <Button type="submit" variant="danger" disabled={processing || !data.password}>
                            {processing ? 'Menghapus...' : 'Hapus Akun'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </Card>
    );
}
