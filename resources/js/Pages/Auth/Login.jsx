import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: true,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="min-h-screen flex flex-col md:flex-row font-sans">
            <Head title="Masuk ke Portal Manajemen" />

            {/* Left Panel: Branding */}
            <div className="hidden md:flex flex-col justify-between w-1/2 bg-indigo-900 text-white p-12 relative overflow-hidden">
                <div className="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 rounded-full bg-indigo-800 opacity-50 blur-3xl"></div>
                <div className="absolute bottom-0 left-0 -ml-16 -mb-16 w-80 h-80 rounded-full bg-indigo-700 opacity-40 blur-3xl"></div>
                
                <div className="relative z-10">
                    <div className="flex items-center space-x-3 mb-12">
                        <div className="bg-white p-2 rounded-xl shadow-lg">
                            <ApplicationLogo className="w-10 h-10 text-indigo-900" />
                        </div>
                        <span className="text-2xl font-extrabold tracking-wider bg-clip-text text-transparent bg-gradient-to-r from-white to-indigo-200">
                            HRIS YAYASAN
                        </span>
                    </div>

                    <h1 className="text-4xl lg:text-5xl font-bold leading-tight mb-6">
                        Portal Manajemen <br/> <span className="text-indigo-300">Terpadu</span>
                    </h1>
                    <p className="text-lg text-indigo-200 max-w-md leading-relaxed">
                        Kelola data pegawai, presensi, penggajian, dan administrasi akademik dalam satu platform yang aman dan efisien.
                    </p>
                </div>

                <div className="relative z-10 text-sm text-indigo-400">
                    &copy; {new Date().getFullYear()} Yayasan Pendidikan. Hak Cipta Dilindungi.
                </div>
            </div>

            {/* Right Panel: Login Form */}
            <div className="w-full md:w-1/2 bg-white flex flex-col justify-center items-center p-8 sm:p-12 lg:p-24 shadow-2xl z-10 rounded-t-3xl md:rounded-none -mt-6 md:mt-0 relative">
                
                {/* Mobile header (hidden on desktop) */}
                <div className="md:hidden flex items-center space-x-3 mb-10 w-full justify-center">
                    <div className="bg-indigo-600 p-2 rounded-xl shadow-lg">
                        <ApplicationLogo className="w-8 h-8 text-white" />
                    </div>
                    <span className="text-xl font-extrabold tracking-wider text-indigo-900">
                        HRIS YAYASAN
                    </span>
                </div>

                <div className="w-full max-w-md">
                    <div className="mb-10">
                        <h2 className="text-3xl font-extrabold text-gray-900 mb-2">Selamat Datang</h2>
                        <p className="text-gray-500 font-medium">Masuk untuk mengakses Portal Manajemen</p>
                    </div>

                    {status && (
                        <div className="mb-6 p-4 rounded-xl bg-emerald-50 text-sm font-semibold text-emerald-600 border border-emerald-100 flex items-center">
                            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg>
                            {status}
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <label className="block text-sm font-bold text-gray-700 mb-2">Alamat Email</label>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                </div>
                                <TextInput
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    className={`w-full pl-11 pr-4 py-3 bg-gray-50 border ${errors.email ? 'border-red-300 ring-1 ring-red-100 focus:border-red-500 focus:ring-red-200' : 'border-gray-200 focus:border-indigo-500 focus:ring-indigo-100'} rounded-xl transition-colors font-medium`}
                                    placeholder="nama@yayasan.com"
                                    autoComplete="username"
                                    isFocused={true}
                                    onChange={(e) => setData('email', e.target.value)}
                                />
                            </div>
                            <InputError message={errors.email} className="mt-2 text-red-500" />
                        </div>

                        <div>
                            <label className="block text-sm font-bold text-gray-700 mb-2">Kata Sandi</label>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                </div>
                                <TextInput
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    className={`w-full pl-11 pr-4 py-3 bg-gray-50 border ${errors.password ? 'border-red-300 ring-1 ring-red-100 focus:border-red-500 focus:ring-red-200' : 'border-gray-200 focus:border-indigo-500 focus:ring-indigo-100'} rounded-xl transition-colors font-medium`}
                                    placeholder="••••••••"
                                    autoComplete="current-password"
                                    onChange={(e) => setData('password', e.target.value)}
                                />
                            </div>
                            <InputError message={errors.password} className="mt-2 text-red-500" />
                        </div>

                        <div className="flex items-center justify-between mt-2">
                            {canResetPassword ? (
                                <Link
                                    href={route('password.request')}
                                    className="text-sm font-bold text-indigo-600 hover:text-indigo-500 transition-colors"
                                >
                                    Lupa Kata Sandi?
                                </Link>
                            ) : <div></div>}
                        </div>

                        <button 
                            disabled={processing}
                            className={`w-full flex justify-center items-center py-4 px-4 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white transition-all active:scale-[0.99] ${processing ? 'bg-indigo-400 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700 hover:shadow-indigo-500/30'}`}
                        >
                            {processing ? (
                                <>
                                    <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Memproses...
                                </>
                            ) : (
                                <>
                                    Masuk ke Dashboard
                                    <svg className="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </>
                            )}
                        </button>
                    </form>
                    
                    <div className="md:hidden mt-12 text-center text-xs text-gray-400 font-medium">
                        &copy; {new Date().getFullYear()} Yayasan Pendidikan.
                    </div>
                </div>
            </div>
        </div>
    );
}
