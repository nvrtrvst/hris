import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { Head, useForm } from '@inertiajs/react';

export default function MobileLogin({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: true,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('mobile.login.store'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="bg-gray-50 min-h-screen flex flex-col justify-center font-sans max-w-md mx-auto shadow-2xl relative overflow-hidden px-6">
            <Head title="Login Pegawai" />

            <div className="text-center mb-10">
                <div className="bg-indigo-600 p-4 rounded-3xl inline-block mb-4 shadow-lg transform rotate-3">
                    <svg className="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"></path></svg>
                </div>
                <h1 className="text-3xl font-extrabold tracking-tight text-gray-900">HRIS Mobile</h1>
                <p className="text-gray-500 mt-2 font-medium">Portal Absensi & Pegawai</p>
            </div>

            {status && <div className="mb-4 text-sm font-medium text-emerald-600 text-center">{status}</div>}

            <form onSubmit={submit} className="bg-white p-6 rounded-3xl shadow-xl border border-gray-100">
                <div className="mb-5">
                    <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Email Anda</label>
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="w-full bg-gray-50 border-gray-200 focus:ring-indigo-500 focus:border-indigo-500 rounded-xl"
                        placeholder="nama@yayasan.com"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mb-8">
                    <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Password</label>
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="w-full bg-gray-50 border-gray-200 focus:ring-indigo-500 focus:border-indigo-500 rounded-xl"
                        placeholder="••••••••"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <button 
                    disabled={processing}
                    className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 rounded-xl shadow-lg transition-all active:scale-[0.98] flex items-center justify-center"
                >
                    {processing ? 'Memproses...' : 'Masuk Sekarang'}
                    {!processing && <svg className="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>}
                </button>
            </form>
        </div>
    );
}
