import InputError from '@/Components/InputError';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, useForm, Link } from '@inertiajs/react';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <div className="min-h-screen flex flex-col md:flex-row font-sans">
            <Head title="Reset Kata Sandi" />

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
                        Buat Sandi <br/> <span className="text-indigo-300">Baru</span>
                    </h1>
                    <p className="text-lg text-indigo-200 max-w-md leading-relaxed">
                        Silakan masukkan kata sandi baru Anda. Pastikan kata sandi tersebut kuat dan mudah Anda ingat.
                    </p>
                </div>

                <div className="relative z-10 text-sm text-indigo-400">
                    &copy; {new Date().getFullYear()} Yayasan Pendidikan. Hak Cipta Dilindungi.
                </div>
            </div>

            {/* Right Panel: Reset Password Form */}
            <div className="w-full md:w-1/2 bg-white flex flex-col justify-center items-center p-8 sm:p-12 lg:p-24 shadow-2xl z-10 rounded-t-3xl md:rounded-none -mt-6 md:mt-0 relative">
                
                {/* Mobile header (hidden on desktop) */}
                <div className="md:hidden flex items-center space-x-3 mb-10 w-full justify-center">
                    <div className="bg-indigo-600 p-2 rounded-xl shadow-lg">
                        <ApplicationLogo className="w-8 h-8 text-white" />
                    </div>
                    <span className="text-2xl font-extrabold tracking-wider text-indigo-900">
                        HRIS YAYASAN
                    </span>
                </div>

                <div className="w-full max-w-md">
                    <div className="mb-10 text-center md:text-left">
                        <h2 className="text-3xl font-extrabold text-gray-900 mb-3 tracking-tight">Buat Sandi Baru</h2>
                        <p className="text-gray-500 font-medium text-sm leading-relaxed">
                            Masukkan email dan kata sandi baru Anda di bawah ini.
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <label className="block text-sm font-bold text-gray-700 mb-2">Alamat Email</label>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                </div>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    className={`w-full pl-11 pr-4 py-3 bg-gray-50 border ${errors.email ? 'border-red-300 ring-1 ring-red-100 focus:border-red-500 focus:ring-red-200' : 'border-gray-200 focus:border-indigo-500 focus:ring-indigo-100'} rounded-xl transition-colors font-medium`}
                                    autoComplete="username"
                                    onChange={(e) => setData('email', e.target.value)}
                                    readOnly
                                />
                            </div>
                            <InputError message={errors.email} className="mt-2 text-red-500" />
                        </div>

                        <div>
                            <label className="block text-sm font-bold text-gray-700 mb-2">Kata Sandi Baru</label>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                </div>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    className={`w-full pl-11 pr-4 py-3 bg-gray-50 border ${errors.password ? 'border-red-300 ring-1 ring-red-100 focus:border-red-500 focus:ring-red-200' : 'border-gray-200 focus:border-indigo-500 focus:ring-indigo-100'} rounded-xl transition-colors font-medium`}
                                    autoComplete="new-password"
                                    autoFocus={true}
                                    onChange={(e) => setData('password', e.target.value)}
                                />
                            </div>
                            <InputError message={errors.password} className="mt-2 text-red-500" />
                        </div>

                        <div>
                            <label className="block text-sm font-bold text-gray-700 mb-2">Konfirmasi Kata Sandi Baru</label>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    value={data.password_confirmation}
                                    className={`w-full pl-11 pr-4 py-3 bg-gray-50 border ${errors.password_confirmation ? 'border-red-300 ring-1 ring-red-100 focus:border-red-500 focus:ring-red-200' : 'border-gray-200 focus:border-indigo-500 focus:ring-indigo-100'} rounded-xl transition-colors font-medium`}
                                    autoComplete="new-password"
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                />
                            </div>
                            <InputError message={errors.password_confirmation} className="mt-2 text-red-500" />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className={`w-full bg-gradient-to-r from-indigo-600 to-indigo-700 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg hover:shadow-indigo-500/30 transform hover:-translate-y-0.5 transition-all duration-200 flex justify-center items-center ${processing ? 'opacity-70 cursor-not-allowed' : ''}`}
                        >
                            {processing ? (
                                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            ) : null}
                            Simpan Kata Sandi
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}
