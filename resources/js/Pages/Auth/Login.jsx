import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Eye, EyeOff, ArrowRight, Check, Moon, Sun, Sparkles } from 'lucide-react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '', password: '', remember: true,
    });

    const [showPassword, setShowPassword] = useState(false);
    const [shake, setShake] = useState(false);
    const [dark, setDark] = useState(false);

    useEffect(() => {
        if (localStorage.getItem('theme') === 'dark') {
            setDark(true);
            document.documentElement.classList.add('dark');
        }
    }, []);

    useEffect(() => {
        localStorage.setItem('theme', dark ? 'dark' : 'light');
        document.documentElement.classList.toggle('dark', dark);
    }, [dark]);

    useEffect(() => {
        if (errors.login || errors.password) {
            setShake(true);
            setTimeout(() => setShake(false), 500);
        }
    }, [errors]);

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    const ripple = (e) => {
        const btn = e.currentTarget;
        const span = document.createElement('span');
        const s = Math.max(btn.offsetWidth, btn.offsetHeight);
        span.style.cssText = `
            position: absolute; border-radius: 50%;
            background: rgba(255,255,255,0.25);
            width:${s}px; height:${s}px;
            left:${e.clientX - btn.getBoundingClientRect().left - s/2}px;
            top:${e.clientY - btn.getBoundingClientRect().top - s/2}px;
            animation: ripple 0.5s ease-out forwards;
            pointer-events: none;
        `;
        btn.appendChild(span);
        setTimeout(() => span.remove(), 500);
    };

    return (
        <div className={`relative min-h-screen flex items-center justify-center overflow-hidden p-4 transition-colors duration-500 ${
            dark ? 'bg-slate-950' : 'bg-gradient-to-br from-slate-50 via-white to-slate-100'
        }`}>
            <Head title="Masuk ke Portal" />

            {/* SVG gradient definitions */}
            <svg className="absolute w-0 h-0" viewBox="0 0 0 0">
                <defs>
                    <linearGradient id="g1" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#1A5A57" stopOpacity="0.35"/>
                        <stop offset="100%" stopColor="#0F3D3E" stopOpacity="0.15"/>
                    </linearGradient>
                    <linearGradient id="g1-dark" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#C9A227" stopOpacity="0.3"/>
                        <stop offset="100%" stopColor="#DAA520" stopOpacity="0.12"/>
                    </linearGradient>
                    <linearGradient id="g2" x1="0%" y1="100%" x2="100%" y2="0%">
                        <stop offset="0%" stopColor="#0F3D3E" stopOpacity="0.3"/>
                        <stop offset="100%" stopColor="#1A5A57" stopOpacity="0.12"/>
                    </linearGradient>
                    <linearGradient id="g2-dark" x1="0%" y1="100%" x2="100%" y2="0%">
                        <stop offset="0%" stopColor="#DAA520" stopOpacity="0.25"/>
                        <stop offset="100%" stopColor="#C9A227" stopOpacity="0.1"/>
                    </linearGradient>
                </defs>
            </svg>

            {/* HR Ornaments - floating gradient SVG icons */}
            <svg className={`absolute w-24 h-24 left-[8%] top-[12%] animate-float-slow`} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs>
                    <linearGradient id="u1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#1A5A57"/><stop offset="100%" stopColor="#C9A227"/></linearGradient>
                </defs>
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke="url(#u1)" strokeWidth="1.5" fill="url(#u1)" fillOpacity="0.08"/>
                <circle cx="9" cy="7" r="4" stroke="url(#u1)" strokeWidth="1.5" fill="url(#u1)" fillOpacity="0.08"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" stroke="url(#u1)" strokeWidth="1.5"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75" stroke="url(#u1)" strokeWidth="1.5"/>
            </svg>
            <svg className={`absolute w-20 h-20 right-[10%] bottom-[18%] animate-float-slow`} style={{ animationDelay: '-4s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs>
                    <linearGradient id="b1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#C9A227"/><stop offset="100%" stopColor="#1A5A57"/></linearGradient>
                </defs>
                <rect x="2" y="3" width="20" height="14" rx="2" stroke="url(#b1)" strokeWidth="1.5" fill="url(#b1)" fillOpacity="0.08"/>
                <path d="M8 21h8" stroke="url(#b1)" strokeWidth="1.5"/>
                <path d="M12 17v4" stroke="url(#b1)" strokeWidth="1.5"/>
                <path d="M2 7h20" stroke="url(#b1)" strokeWidth="1.5"/>
            </svg>
            <svg className={`absolute w-16 h-16 right-[18%] top-[25%] animate-float-slow`} style={{ animationDelay: '-2s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs>
                    <linearGradient id="gp1" x1="0" y1="2" x2="24" y2="22"><stop offset="0%" stopColor="#0F3D3E"/><stop offset="100%" stopColor="#C9A227"/></linearGradient>
                </defs>
                <path d="M12 2L2 7l10 5 10-5-10-5z" stroke="url(#gp1)" strokeWidth="1.5" fill="url(#gp1)" fillOpacity="0.08"/>
                <path d="M2 17l10 5 10-5" stroke="url(#gp1)" strokeWidth="1.5"/>
                <path d="M2 12l10 5 10-5" stroke="url(#gp1)" strokeWidth="1.5"/>
            </svg>
            <svg className={`absolute w-14 h-14 left-[12%] bottom-[28%] animate-float-slow`} style={{ animationDelay: '-6s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs>
                    <linearGradient id="cl1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#1A5A57"/><stop offset="100%" stopColor="#C9A227"/></linearGradient>
                </defs>
                <circle cx="12" cy="12" r="10" stroke="url(#cl1)" strokeWidth="1.5" fill="url(#cl1)" fillOpacity="0.08"/>
                <path d="M12 6v6l4 2" stroke="url(#cl1)" strokeWidth="1.5"/>
            </svg>
            <svg className={`absolute w-18 h-18 left-[40%] top-[8%] animate-float-slow`} style={{ animationDelay: '-3s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs>
                    <linearGradient id="ps1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#C9A227"/><stop offset="100%" stopColor="#0F3D3E"/></linearGradient>
                </defs>
                <circle cx="12" cy="8" r="5" stroke="url(#ps1)" strokeWidth="1.5" fill="url(#ps1)" fillOpacity="0.08"/>
                <path d="M20 21a8 8 0 1 0-16 0" stroke="url(#ps1)" strokeWidth="1.5"/>
            </svg>
            <svg className={`absolute w-12 h-12 right-[35%] bottom-[10%] animate-float-slow`} style={{ animationDelay: '-5s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs>
                    <linearGradient id="ca1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#0F3D3E"/><stop offset="100%" stopColor="#1A5A57"/></linearGradient>
                </defs>
                <rect x="3" y="4" width="18" height="18" rx="2" stroke="url(#ca1)" strokeWidth="1.5" fill="url(#ca1)" fillOpacity="0.08"/>
                <path d="M16 2v4" stroke="url(#ca1)" strokeWidth="1.5"/>
                <path d="M8 2v4" stroke="url(#ca1)" strokeWidth="1.5"/>
                <path d="M3 10h18" stroke="url(#ca1)" strokeWidth="1.5"/>
                <path d="M8 14h.01" stroke="url(#ca1)" strokeWidth="1.5"/>
                <path d="M12 14h.01" stroke="url(#ca1)" strokeWidth="1.5"/>
                <path d="M16 14h.01" stroke="url(#ca1)" strokeWidth="1.5"/>
                <path d="M8 18h.01" stroke="url(#ca1)" strokeWidth="1.5"/>
                <path d="M12 18h.01" stroke="url(#ca1)" strokeWidth="1.5"/>
                <path d="M16 18h.01" stroke="url(#ca1)" strokeWidth="1.5"/>
            </svg>

            {/* More HR ornaments */}
            <svg className={`absolute w-10 h-10 left-[22%] bottom-[8%] animate-float-slow`} style={{ animationDelay: '-1.5s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="loc1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#C9A227"/><stop offset="100%" stopColor="#0F3D3E"/></linearGradient></defs>
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" stroke="url(#loc1)" strokeWidth="1.5" fill="url(#loc1)" fillOpacity="0.08"/>
                <circle cx="12" cy="10" r="3" stroke="url(#loc1)" strokeWidth="1.5"/>
            </svg>
            <svg className={`absolute w-11 h-11 right-[5%] top-[40%] animate-float-slow`} style={{ animationDelay: '-0.5s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="doc1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#1A5A57"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="url(#doc1)" strokeWidth="1.5" fill="url(#doc1)" fillOpacity="0.08"/>
                <polyline points="14 2 14 8 20 8" stroke="url(#doc1)" strokeWidth="1.5"/>
                <line x1="8" y1="13" x2="16" y2="13" stroke="url(#doc1)" strokeWidth="1.5"/>
                <line x1="8" y1="17" x2="12" y2="17" stroke="url(#doc1)" strokeWidth="1.5"/>
            </svg>
            <svg className={`absolute w-9 h-9 left-[5%] top-[45%] animate-float-slow`} style={{ animationDelay: '-3.5s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="clip1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#0F3D3E"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" stroke="url(#clip1)" strokeWidth="1.5"/>
                <rect x="8" y="2" width="8" height="4" rx="1" stroke="url(#clip1)" strokeWidth="1.5" fill="url(#clip1)" fillOpacity="0.08"/>
                <line x1="9" y1="12" x2="15" y2="12" stroke="url(#clip1)" strokeWidth="1.5"/>
                <line x1="9" y1="16" x2="13" y2="16" stroke="url(#clip1)" strokeWidth="1.5"/>
            </svg>
            <svg className={`absolute w-10 h-10 right-[22%] top-[12%] animate-float-slow`} style={{ animationDelay: '-2.5s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="hrt1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#C9A227"/><stop offset="100%" stopColor="#1A5A57"/></linearGradient></defs>
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7 7-7z" stroke="url(#hrt1)" strokeWidth="1.5" fill="url(#hrt1)" fillOpacity="0.08"/>
            </svg>
            <svg className={`absolute w-8 h-8 left-[30%] top-[5%] animate-float-slow`} style={{ animationDelay: '-4.5s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="awd1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#1A5A57"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <circle cx="12" cy="8" r="6" stroke="url(#awd1)" strokeWidth="1.5" fill="url(#awd1)" fillOpacity="0.08"/>
                <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11" stroke="url(#awd1)" strokeWidth="1.5"/>
            </svg>
            <svg className={`absolute w-11 h-11 left-[60%] bottom-[5%] animate-float-slow`} style={{ animationDelay: '-1s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="book1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#0F3D3E"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" stroke="url(#book1)" strokeWidth="1.5"/>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" stroke="url(#book1)" strokeWidth="1.5" fill="url(#book1)" fillOpacity="0.08"/>
            </svg>
            <svg className={`absolute w-9 h-9 right-[28%] bottom-[45%] animate-float-slow`} style={{ animationDelay: '-5.5s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="chart1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#1A5A57"/><stop offset="100%" stopColor="#0F3D3E"/></linearGradient></defs>
                <line x1="18" y1="20" x2="18" y2="10" stroke="url(#chart1)" strokeWidth="1.5"/>
                <line x1="12" y1="20" x2="12" y2="4" stroke="url(#chart1)" strokeWidth="1.5"/>
                <line x1="6" y1="20" x2="6" y2="14" stroke="url(#chart1)" strokeWidth="1.5"/>
            </svg>
            <svg className={`absolute w-10 h-10 left-[70%] top-[35%] animate-float-slow`} style={{ animationDelay: '-2s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="shld1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#C9A227"/><stop offset="100%" stopColor="#1A5A57"/></linearGradient></defs>
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="url(#shld1)" strokeWidth="1.5" fill="url(#shld1)" fillOpacity="0.08"/>
            </svg>
            <svg className={`absolute w-8 h-8 right-[45%] top-[50%] animate-float-slow`} style={{ animationDelay: '-0.8s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="phone1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#0F3D3E"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <rect x="5" y="2" width="14" height="20" rx="2" stroke="url(#phone1)" strokeWidth="1.5" fill="url(#phone1)" fillOpacity="0.08"/>
                <line x1="12" y1="18" x2="12.01" y2="18" stroke="url(#phone1)" strokeWidth="1.5"/>
            </svg>

            {/* Extra scattered ornaments - sekolah / administrasi / siswa themed */}
            {/* Books (sekolah) */}
            <svg className={`absolute w-7 h-7 left-[18%] top-[55%] animate-float-slow`} style={{ animationDelay: '-0.3s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="ex1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#C9A227"/><stop offset="100%" stopColor="#1A5A57"/></linearGradient></defs>
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" stroke="url(#ex1)" strokeWidth="1.5"/>
                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" stroke="url(#ex1)" strokeWidth="1.5"/>
            </svg>
            {/* Graduation Cap (siswa) */}
            <svg className={`absolute w-9 h-9 right-[15%] top-[8%] animate-float-slow`} style={{ animationDelay: '-2.2s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="ex2" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#0F3D3E"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <path d="M12 2L2 7l10 5 10-5-10-5z" stroke="url(#ex2)" strokeWidth="1.5"/>
                <path d="M2 7v5l10 5 10-5V7" stroke="url(#ex2)" strokeWidth="1.5"/>
                <path d="M6 10v5a3 3 0 0 0 3 3h6a3 3 0 0 0 3-3v-5" stroke="url(#ex2)" strokeWidth="1.5"/>
            </svg>
            {/* Clock (presensi) */}
            <svg className={`absolute w-8 h-8 left-[45%] top-[3%] animate-float-slow`} style={{ animationDelay: '-3.8s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="ex3" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#1A5A57"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <circle cx="12" cy="12" r="10" stroke="url(#ex3)" strokeWidth="1.5" fill="url(#ex3)" fillOpacity="0.08"/>
                <path d="M12 6v6l4 2" stroke="url(#ex3)" strokeWidth="1.5"/>
            </svg>
            {/* Ruler & Pencil (sekolah - alat tulis) */}
            <svg className={`absolute w-7 h-7 right-[8%] top-[55%] animate-float-slow`} style={{ animationDelay: '-1.8s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="ex4" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#C9A227"/><stop offset="100%" stopColor="#0F3D3E"/></linearGradient></defs>
                <path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z" stroke="url(#ex4)" strokeWidth="1.5"/>
                <line x1="15" y1="5" x2="19" y2="9" stroke="url(#ex4)" strokeWidth="1.5"/>
            </svg>
            {/* Document with ID (kepegawaian / administrasi) */}
            <svg className={`absolute w-10 h-10 left-[3%] top-[62%] animate-float-slow`} style={{ animationDelay: '-4.2s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="ex5" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#0F3D3E"/><stop offset="100%" stopColor="#1A5A57"/></linearGradient></defs>
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="url(#ex5)" strokeWidth="1.5" fill="url(#ex5)" fillOpacity="0.08"/>
                <polyline points="14 2 14 8 20 8" stroke="url(#ex5)" strokeWidth="1.5"/>
                <circle cx="12" cy="15" r="2" stroke="url(#ex5)" strokeWidth="1.5"/>
                <path d="M12 17v3" stroke="url(#ex5)" strokeWidth="1.5"/>
            </svg>
            {/* School Bell (sekolah) */}
            <svg className={`absolute w-6 h-6 right-[42%] top-[6%] animate-float-slow`} style={{ animationDelay: '-0.7s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="ex6" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#1A5A57"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <path d="M18 12a6 6 0 0 0-12 0v4l-2 2h16l-2-2v-4z" stroke="url(#ex6)" strokeWidth="1.5"/>
                <line x1="6" y1="18" x2="18" y2="18" stroke="url(#ex6)" strokeWidth="1.5"/>
                <path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2z" stroke="url(#ex6)" strokeWidth="1.5"/>
            </svg>
            {/* Backpack (siswa) */}
            <svg className={`absolute w-9 h-9 left-[75%] top-[8%] animate-float-slow`} style={{ animationDelay: '-3s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="ex7" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#C9A227"/><stop offset="100%" stopColor="#0F3D3E"/></linearGradient></defs>
                <path d="M6 22V9a6 6 0 0 1 12 0v13" stroke="url(#ex7)" strokeWidth="1.5" fill="url(#ex7)" fillOpacity="0.08"/>
                <path d="M9 9V7a3 3 0 0 1 6 0v2" stroke="url(#ex7)" strokeWidth="1.5"/>
                <line x1="12" y1="13" x2="12" y2="17" stroke="url(#ex7)" strokeWidth="1.5"/>
            </svg>
            {/* People (kepegawaian) */}
            <svg className={`absolute w-8 h-8 right-[20%] top-[60%] animate-float-slow`} style={{ animationDelay: '-1.3s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="ex8" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#0F3D3E"/><stop offset="100%" stopColor="#1A5A57"/></linearGradient></defs>
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke="url(#ex8)" strokeWidth="1.5"/>
                <circle cx="9" cy="7" r="4" stroke="url(#ex8)" strokeWidth="1.5" fill="url(#ex8)" fillOpacity="0.08"/>
            </svg>

            {/* Large building illustration - left side */}
            <svg className={`absolute w-48 h-48 left-[2%] top-[8%] animate-float-slow pointer-events-none`} style={{ animationDelay: '-1s' }} viewBox="0 0 100 100" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs>
                    <linearGradient id="big1" x1="0" y1="0" x2="100" y2="100"><stop offset="0%" stopColor="#1A5A57" stopOpacity="0.25"/><stop offset="100%" stopColor="#C9A227" stopOpacity="0.10"/></linearGradient>
                    <linearGradient id="big2" x1="100" y1="0" x2="0" y2="100"><stop offset="0%" stopColor="#C9A227" stopOpacity="0.20"/><stop offset="100%" stopColor="#0F3D3E" stopOpacity="0.10"/></linearGradient>
                </defs>
                {/* Building body */}
                <rect x="20" y="25" width="60" height="70" rx="3" stroke="url(#big1)" strokeWidth="1.2" fill="url(#big1)"/>
                {/* Roof */}
                <polygon points="10,28 50,5 90,28" stroke="url(#big2)" strokeWidth="1.2" fill="url(#big2)"/>
                {/* Windows row 1 */}
                <rect x="28" y="35" width="10" height="12" rx="1" stroke="url(#big1)" strokeWidth="0.8" fill="url(#big1)"/>
                <rect x="45" y="35" width="10" height="12" rx="1" stroke="url(#big1)" strokeWidth="0.8" fill="url(#big1)"/>
                <rect x="62" y="35" width="10" height="12" rx="1" stroke="url(#big1)" strokeWidth="0.8" fill="url(#big1)"/>
                {/* Windows row 2 */}
                <rect x="28" y="55" width="10" height="12" rx="1" stroke="url(#big1)" strokeWidth="0.8" fill="url(#big1)"/>
                <rect x="45" y="55" width="10" height="12" rx="1" stroke="url(#big1)" strokeWidth="0.8" fill="url(#big1)"/>
                <rect x="62" y="55" width="10" height="12" rx="1" stroke="url(#big1)" strokeWidth="0.8" fill="url(#big1)"/>
                {/* Door */}
                <rect x="40" y="75" width="20" height="20" rx="2" stroke="url(#big2)" strokeWidth="0.8" fill="url(#big2)"/>
                {/* Door handle */}
                <circle cx="53" cy="86" r="1.5" stroke="url(#big1)" strokeWidth="0.8" fill="url(#big1)"/>
                {/* Flag on roof */}
                <line x1="50" y1="5" x2="50" y2="-5" stroke="url(#big2)" strokeWidth="1.2"/>
                <polygon points="50,-5 65,-1 50,3" stroke="url(#big2)" strokeWidth="0.8" fill="url(#big2)"/>
                {/* Ground line */}
                <line x1="10" y1="95" x2="90" y2="95" stroke="url(#big1)" strokeWidth="0.8" strokeDasharray="3,3"/>
                {/* People silhouettes in front */}
                <circle cx="18" cy="92" r="2" stroke="url(#big2)" strokeWidth="0.6" fill="url(#big2)"/>
                <path d="M18 94v6" stroke="url(#big2)" strokeWidth="0.8"/>
                <circle cx="82" cy="90" r="2" stroke="url(#big2)" strokeWidth="0.6" fill="url(#big2)"/>
                <path d="M82 92v8" stroke="url(#big2)" strokeWidth="0.8"/>
            </svg>

            {/* Large people illustration - left bottom */}
            <svg className="absolute w-40 h-40 left-[4%] bottom-[5%] animate-float-slow pointer-events-none" style={{ animationDelay: '-2.5s' }} viewBox="0 0 100 100" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs>
                    <linearGradient id="big3" x1="0" y1="0" x2="100" y2="100"><stop offset="0%" stopColor="#C9A227" stopOpacity="0.20"/><stop offset="100%" stopColor="#0F3D3E" stopOpacity="0.10"/></linearGradient>
                </defs>
                {/* Person 1 */}
                <circle cx="35" cy="25" r="10" stroke="url(#big3)" strokeWidth="1" fill="url(#big3)"/>
                <path d="M35 35v25" stroke="url(#big3)" strokeWidth="1"/>
                <path d="M20 50l15-10 15 10" stroke="url(#big3)" strokeWidth="1"/>
                <path d="M35 60L20 80" stroke="url(#big3)" strokeWidth="1"/>
                <path d="M35 60l15 20" stroke="url(#big3)" strokeWidth="1"/>
                {/* Person 2 - right */}
                <circle cx="65" cy="30" r="9" stroke="url(#big3)" strokeWidth="1" fill="url(#big3)"/>
                <path d="M65 39v20" stroke="url(#big3)" strokeWidth="1"/>
                <path d="M50 52l15-10 15 10" stroke="url(#big3)" strokeWidth="1"/>
                <path d="M65 59L52 78" stroke="url(#big3)" strokeWidth="1"/>
                <path d="M65 59l13 19" stroke="url(#big3)" strokeWidth="1"/>
                {/* Ground */}
                <line x1="10" y1="85" x2="90" y2="85" stroke="url(#big3)" strokeWidth="0.8" strokeDasharray="4,3"/>
            </svg>

            {/* Large hands/shake illustration */}
            <svg className="absolute w-36 h-36 left-[6%] bottom-[40%] animate-float-slow pointer-events-none" style={{ animationDelay: '-4s' }} viewBox="0 0 100 100" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs>
                    <linearGradient id="big4" x1="0" y1="100" x2="100" y2="0"><stop offset="0%" stopColor="#0F3D3E" stopOpacity="0.18"/><stop offset="100%" stopColor="#C9A227" stopOpacity="0.10"/></linearGradient>
                </defs>
                {/* Left hand */}
                <path d="M15 60c5-10 15-15 20-18l5-3" stroke="url(#big4)" strokeWidth="1.2"/>
                <path d="M40 39l-3-8" stroke="url(#big4)" strokeWidth="0.8"/>
                {/* Right hand */}
                <path d="M85 58c-5-12-18-18-25-20l-5-2" stroke="url(#big4)" strokeWidth="1.2"/>
                <path d="M55 36l4-7" stroke="url(#big4)" strokeWidth="0.8"/>
                {/* Handshake intersection */}
                <path d="M35 42c8-4 22-4 30 2" stroke="url(#big4)" strokeWidth="1.5"/>
                {/* Sparkles around */}
                <circle cx="28" cy="38" r="1.5" stroke="url(#big4)" strokeWidth="0.6" fill="url(#big4)"/>
                <circle cx="72" cy="36" r="1.5" stroke="url(#big4)" strokeWidth="0.6" fill="url(#big4)"/>
                <circle cx="50" cy="30" r="1" stroke="url(#big4)" strokeWidth="0.6" fill="url(#big4)"/>
            </svg>

            {/* Large clock/schedule illustration */}
            <svg className="absolute w-32 h-32 left-[2%] top-[45%] animate-float-slow pointer-events-none" style={{ animationDelay: '-1.8s' }} viewBox="0 0 100 100" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs>
                    <linearGradient id="big5" x1="0" y1="0" x2="100" y2="100"><stop offset="0%" stopColor="#1A5A57" stopOpacity="0.20"/><stop offset="100%" stopColor="#C9A227" stopOpacity="0.08"/></linearGradient>
                </defs>
                <circle cx="50" cy="50" r="40" stroke="url(#big5)" strokeWidth="1.2" fill="url(#big5)"/>
                <line x1="50" y1="50" x2="50" y2="30" stroke="url(#big5)" strokeWidth="1.5"/>
                <line x1="50" y1="50" x2="65" y2="55" stroke="url(#big5)" strokeWidth="1.2"/>
                <circle cx="50" cy="50" r="3" stroke="url(#big5)" strokeWidth="0.8" fill="url(#big5)"/>
                {/* Tick marks */}
                <line x1="50" y1="12" x2="50" y2="18" stroke="url(#big5)" strokeWidth="0.8"/>
                <line x1="50" y1="82" x2="50" y2="88" stroke="url(#big5)" strokeWidth="0.8"/>
                <line x1="12" y1="50" x2="18" y2="50" stroke="url(#big5)" strokeWidth="0.8"/>
                <line x1="82" y1="50" x2="88" y2="50" stroke="url(#big5)" strokeWidth="0.8"/>
            </svg>

            {/* Presensi & Kepegawaian themed ornaments */}
            {/* Fingerprint */}
            <svg className={`absolute w-7 h-7 left-[40%] top-[12%] animate-float-slow`} style={{ animationDelay: '-1.2s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p1" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#1A5A57"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4" stroke="url(#p1)" strokeWidth="1.5"/>
                <path d="M14 13.12c0 2.38 0 6.38-1 8.88" stroke="url(#p1)" strokeWidth="1.5"/>
                <path d="M17.29 21.02c.12-.63.71-3.44.71-5.02" stroke="url(#p1)" strokeWidth="1.5"/>
                <path d="M2 12a10 10 0 0 0 19.5-3.02" stroke="url(#p1)" strokeWidth="1.5"/>
            </svg>
            {/* Camera */}
            <svg className={`absolute w-7 h-7 right-[38%] top-[15%] animate-float-slow`} style={{ animationDelay: '-2.8s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p2" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#C9A227"/><stop offset="100%" stopColor="#0F3D3E"/></linearGradient></defs>
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" stroke="url(#p2)" strokeWidth="1.5" fill="url(#p2)" fillOpacity="0.08"/>
                <circle cx="12" cy="13" r="4" stroke="url(#p2)" strokeWidth="1.5"/>
            </svg>
            {/* GPS / Map Pin */}
            <svg className={`absolute w-8 h-8 right-[40%] top-[58%] animate-float-slow`} style={{ animationDelay: '-0.9s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p3" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#0F3D3E"/><stop offset="100%" stopColor="#1A5A57"/></linearGradient></defs>
                <circle cx="12" cy="10" r="3" stroke="url(#p3)" strokeWidth="1.5"/>
                <path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z" stroke="url(#p3)" strokeWidth="1.5" fill="url(#p3)" fillOpacity="0.08"/>
            </svg>
            {/* QR Code */}
            <svg className={`absolute w-6 h-6 left-[55%] top-[58%] animate-float-slow`} style={{ animationDelay: '-3.3s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p4" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#1A5A57"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <rect x="3" y="3" width="7" height="7" stroke="url(#p4)" strokeWidth="1.5" fill="url(#p4)" fillOpacity="0.08"/>
                <rect x="14" y="3" width="7" height="7" stroke="url(#p4)" strokeWidth="1.5" fill="url(#p4)" fillOpacity="0.08"/>
                <rect x="3" y="14" width="7" height="7" stroke="url(#p4)" strokeWidth="1.5" fill="url(#p4)" fillOpacity="0.08"/>
                <rect x="14" y="14" width="4" height="4" stroke="url(#p4)" strokeWidth="1.5"/>
                <line x1="14" y1="21" x2="21" y2="21" stroke="url(#p4)" strokeWidth="1.5"/>
                <line x1="21" y1="14" x2="21" y2="18" stroke="url(#p4)" strokeWidth="1.5"/>
            </svg>
            {/* ID Card / Badge */}
            <svg className={`absolute w-7 h-7 left-[15%] top-[70%] animate-float-slow`} style={{ animationDelay: '-0.6s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p5" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#C9A227"/><stop offset="100%" stopColor="#0F3D3E"/></linearGradient></defs>
                <rect x="2" y="4" width="20" height="16" rx="2" stroke="url(#p5)" strokeWidth="1.5" fill="url(#p5)" fillOpacity="0.08"/>
                <circle cx="9" cy="10" r="2" stroke="url(#p5)" strokeWidth="1.5"/>
                <path d="M17 14H7" stroke="url(#p5)" strokeWidth="1.5"/>
                <path d="M17 17H7" stroke="url(#p5)" strokeWidth="1.5"/>
                <circle cx="18" cy="10" r="1.5" stroke="url(#p5)" strokeWidth="1.5"/>
            </svg>
            {/* Clipboard Check */}
            <svg className={`absolute w-8 h-8 right-[10%] top-[65%] animate-float-slow`} style={{ animationDelay: '-1.6s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p6" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#0F3D3E"/><stop offset="100%" stopColor="#1A5A57"/></linearGradient></defs>
                <rect x="6" y="4" width="12" height="16" rx="2" stroke="url(#p6)" strokeWidth="1.5" fill="url(#p6)" fillOpacity="0.08"/>
                <line x1="8" y1="2" x2="8" y2="6" stroke="url(#p6)" strokeWidth="1.5"/>
                <line x1="16" y1="2" x2="16" y2="6" stroke="url(#p6)" strokeWidth="1.5"/>
                <polyline points="9 13 11 15 15 11" stroke="url(#p6)" strokeWidth="1.5"/>
            </svg>
            {/* Money / Payroll */}
            <svg className={`absolute w-7 h-7 left-[8%] top-[30%] animate-float-slow`} style={{ animationDelay: '-4.1s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p7" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#1A5A57"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <line x1="12" y1="2" x2="12" y2="22" stroke="url(#p7)" strokeWidth="1.5"/>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" stroke="url(#p7)" strokeWidth="1.5"/>
            </svg>
            {/* Bank / Building */}
            <svg className={`absolute w-8 h-8 right-[32%] top-[45%] animate-float-slow`} style={{ animationDelay: '-2.4s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p8" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#C9A227"/><stop offset="100%" stopColor="#0F3D3E"/></linearGradient></defs>
                <polyline points="3 21 21 21 21 10 3 10" stroke="url(#p8)" strokeWidth="1.5" fill="url(#p8)" fillOpacity="0.08"/>
                <polyline points="9 10 12 3 15 10" stroke="url(#p8)" strokeWidth="1.5"/>
                <line x1="7" y1="14" x2="10" y2="14" stroke="url(#p8)" strokeWidth="1.5"/>
                <line x1="14" y1="14" x2="17" y2="14" stroke="url(#p8)" strokeWidth="1.5"/>
                <line x1="7" y1="17" x2="17" y2="17" stroke="url(#p8)" strokeWidth="1.5"/>
            </svg>
            {/* Archive / File Cabinet (administrasi) */}
            <svg className={`absolute w-7 h-7 left-[48%] top-[45%] animate-float-slow`} style={{ animationDelay: '-0.1s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p9" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#0F3D3E"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <path d="M22 20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2v12z" stroke="url(#p9)" strokeWidth="1.5" fill="url(#p9)" fillOpacity="0.08"/>
                <line x1="12" y1="11" x2="12" y2="17" stroke="url(#p9)" strokeWidth="1.5"/>
                <line x1="9" y1="14" x2="15" y2="14" stroke="url(#p9)" strokeWidth="1.5"/>
            </svg>
            {/* Medal / Award */}
            <svg className={`absolute w-6 h-6 right-[25%] top-[70%] animate-float-slow`} style={{ animationDelay: '-3.7s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p10" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#1A5A57"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <circle cx="12" cy="8" r="5" stroke="url(#p10)" strokeWidth="1.5" fill="url(#p10)" fillOpacity="0.08"/>
                <line x1="12" y1="13" x2="12" y2="21" stroke="url(#p10)" strokeWidth="1.5"/>
                <line x1="8" y1="18" x2="16" y2="18" stroke="url(#p10)" strokeWidth="1.5"/>
            </svg>
            {/* Diploma / Certificate */}
            <svg className={`absolute w-7 h-7 left-[35%] top-[65%] animate-float-slow`} style={{ animationDelay: '-1.9s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p11" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#C9A227"/><stop offset="100%" stopColor="#0F3D3E"/></linearGradient></defs>
                <circle cx="12" cy="8" r="4" stroke="url(#p11)" strokeWidth="1.5" fill="url(#p11)" fillOpacity="0.08"/>
                <path d="M12 12v10l-3-1.5-3 1.5V12" stroke="url(#p11)" strokeWidth="1.5"/>
            </svg>
            {/* Network/Team */}
            <svg className={`absolute w-8 h-8 left-[65%] top-[18%] animate-float-slow`} style={{ animationDelay: '-2.1s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p12" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#0F3D3E"/><stop offset="100%" stopColor="#1A5A57"/></linearGradient></defs>
                <circle cx="18" cy="5" r="3" stroke="url(#p12)" strokeWidth="1.5" fill="url(#p12)" fillOpacity="0.08"/>
                <circle cx="6" cy="12" r="3" stroke="url(#p12)" strokeWidth="1.5" fill="url(#p12)" fillOpacity="0.08"/>
                <circle cx="18" cy="19" r="3" stroke="url(#p12)" strokeWidth="1.5" fill="url(#p12)" fillOpacity="0.08"/>
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49" stroke="url(#p12)" strokeWidth="1.5"/>
                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49" stroke="url(#p12)" strokeWidth="1.5"/>
            </svg>
            {/* Calculator (administrasi) */}
            <svg className={`absolute w-6 h-6 left-[78%] top-[50%] animate-float-slow`} style={{ animationDelay: '-3.5s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p13" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#1A5A57"/><stop offset="100%" stopColor="#C9A227"/></linearGradient></defs>
                <rect x="4" y="2" width="16" height="20" rx="2" stroke="url(#p13)" strokeWidth="1.5" fill="url(#p13)" fillOpacity="0.08"/>
                <line x1="8" y1="6" x2="16" y2="6" stroke="url(#p13)" strokeWidth="1.5"/>
                <rect x="6" y="10" width="4" height="4" rx="0.5" stroke="url(#p13)" strokeWidth="1.5"/>
                <rect x="14" y="10" width="4" height="4" rx="0.5" stroke="url(#p13)" strokeWidth="1.5"/>
                <rect x="6" y="16" width="4" height="4" rx="0.5" stroke="url(#p13)" strokeWidth="1.5"/>
                <rect x="14" y="16" width="4" height="4" rx="0.5" stroke="url(#p13)" strokeWidth="1.5"/>
            </svg>
            {/* Document / Report (administrasi) */}
            <svg className={`absolute w-6 h-6 right-[15%] top-[35%] animate-float-slow`} style={{ animationDelay: '-0.4s' }} viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
                <defs><linearGradient id="p14" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stopColor="#C9A227"/><stop offset="100%" stopColor="#0F3D3E"/></linearGradient></defs>
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="url(#p14)" strokeWidth="1.5" fill="url(#p14)" fillOpacity="0.08"/>
                <line x1="8" y1="13" x2="16" y2="13" stroke="url(#p14)" strokeWidth="1.5"/>
                <line x1="8" y1="17" x2="16" y2="17" stroke="url(#p14)" strokeWidth="1.5"/>
                <line x1="8" y1="9" x2="12" y2="9" stroke="url(#p14)" strokeWidth="1.5"/>
            </svg>

            {/* Legacy orbs */}
            <div className={`orb w-80 h-80 ${dark ? 'bg-accent/10' : 'bg-primary/8'} -top-40 -right-20`} />
            <div className={`orb w-96 h-96 ${dark ? 'bg-accent/8' : 'bg-primary/6'} -bottom-48 -left-24`} style={{ animationDelay: '-3s' }} />
            <div className={`orb w-64 h-64 ${dark ? 'bg-accent/6' : 'bg-primary/5'} top-1/3 -right-32`} style={{ animationDelay: '-6s' }} />

            {/* Main Card */}
            <div
                style={shake ? { animation: 'shake 0.4s ease' } : undefined}
                className={`relative w-full max-w-md glass-card rounded-3xl p-6 sm:p-8 shadow-2xl ring-1 transition-all duration-500 ${
                    dark
                        ? 'bg-slate-900/70 ring-slate-800/50'
                        : 'bg-white/70 ring-black/5'
                }`}
            >
                {/* Top gradient accent */}
                <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-primary via-accent to-primary rounded-t-3xl" />

                {/* Dark mode toggle */}
                <button
                    type="button"
                    onClick={() => setDark(!dark)}
                    className={`absolute top-4 right-4 transition-all ${
                        dark ? 'theme-toggle-dark' : 'theme-toggle-light'
                    }`}
                    aria-label="Toggle theme"
                >
                    {dark ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
                </button>

                {/* Header */}
                <div className="flex flex-col items-center mb-4">
                    <ApplicationLogo className="w-full max-w-[320px] h-auto mb-8 transform scale-150 text-accent pointer-events-none" />
                    <div className="flex items-center gap-2 mb-1">
                        <Sparkles className={`w-4 h-4 ${dark ? 'text-accent' : 'text-primary'}`} />
                        <span className={`text-sm font-bold tracking-[0.2em] ${
                            dark ? 'text-accent' : 'text-primary'
                        }`}>
                            HRIS
                        </span>
                    </div>
                    <h1 className={`text-xl font-bold text-center ${
                        dark ? 'text-white' : 'text-gray-900'
                    }`}>
                        Yayasan Nuurul Muttaqiin
                    </h1>
                </div>

                {/* Form Section */}
                <div className="mb-4">
                    <h2 className={`text-lg font-semibold mb-1 ${
                        dark ? 'text-white' : 'text-gray-900'
                    }`}>
                        Selamat datang kembali
                    </h2>
                    <p className={`text-sm ${dark ? 'text-gray-400' : 'text-gray-500'}`}>
                        Masuk untuk melanjutkan
                    </p>
                </div>

                {status && (
                    <div className={`mb-6 p-3 rounded-xl flex items-center gap-3 text-sm font-medium ${
                        dark
                            ? 'bg-success/10 text-success border border-success/20'
                            : 'bg-success/5 text-success border border-success/15'
                    }`}>
                        <Check className="w-4 h-4 shrink-0" />
                        {status}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4" noValidate>
                    {/* Email */}
                    <div className={`input-group ${dark ? 'dark' : 'light'} animate-fade-in-up`} style={{ animationDelay: '0.05s' }}>
                        <input
                            id="login"
                            type="text"
                            value={data.login}
                            placeholder=" "
                            autoComplete="username"
                            autoFocus
                            className={errors.login ? 'error' : ''}
                            onChange={(e) => setData('login', e.target.value)}
                        />
                        <label htmlFor="login">Email atau NIP</label>
                        {errors.login && (
                            <p className="text-xs text-danger mt-2 font-medium">{errors.login}</p>
                        )}
                    </div>

                    {/* Password */}
                    <div className={`input-group ${dark ? 'dark' : 'light'} animate-fade-in-up`} style={{ animationDelay: '0.1s' }}>
                        <input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            value={data.password}
                            placeholder=" "
                            autoComplete="current-password"
                            className={`pr-12 ${errors.password ? 'error' : ''}`}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <label htmlFor="password">Kata sandi</label>
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className={`absolute right-3 top-1/2 -translate-y-1/2 p-1.5 rounded-lg transition-all ${
                                dark ? 'text-gray-500 hover:text-accent hover:bg-slate-800' : 'text-gray-400 hover:text-primary hover:bg-gray-100'
                            }`}
                        >
                            {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                        </button>
                        {errors.password && (
                            <p className="text-xs text-danger mt-2 font-medium">{errors.password}</p>
                        )}
                    </div>

                    {/* Options */}
                    <div className="flex items-center justify-between animate-fade-in-up" style={{ animationDelay: '0.15s' }}>
                        <label className={`flex items-center gap-2.5 cursor-pointer select-none group ${
                            dark ? 'text-gray-400' : 'text-gray-500'
                        }`}>
                            <div className="relative">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="peer sr-only"
                                />
                                <div className={`w-4.5 h-4.5 rounded-md border-2 transition-all duration-200 ${
                                    data.remember
                                        ? dark ? 'bg-accent border-accent' : 'bg-primary border-primary'
                                        : dark ? 'border-slate-600' : 'border-gray-300'
                                }`}>
                                    {data.remember && (
                                        <Check className="w-3 h-3 text-white absolute inset-0 m-auto" />
                                    )}
                                </div>
                            </div>
                            <span className="text-sm group-hover:text-primary dark:group-hover:text-accent transition-colors">
                                Ingat saya
                            </span>
                        </label>

                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className={`text-sm font-medium transition-all ${
                                    dark
                                        ? 'text-accent hover:text-accent-light'
                                        : 'text-primary hover:text-primary-light'
                                }`}
                            >
                                Lupa password?
                            </Link>
                        )}
                    </div>

                    {/* Submit */}
                    <button
                        type="submit"
                        disabled={processing}
                        onClick={!processing ? ripple : undefined}
                        className={`w-full py-3.5 rounded-xl font-semibold text-white transition-all duration-300 flex items-center justify-center gap-2 animate-fade-in-up ${
                            dark ? 'btn-primary-dark' : 'btn-primary-light'
                        } ${processing ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'}`}
                        style={{ animationDelay: '0.2s' }}
                    >
                        {processing ? (
                            <>
                                <svg className="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                Memproses...
                            </>
                        ) : (
                            <>
                                Masuk
                                <ArrowRight className="w-4 h-4" />
                            </>
                        )}
                    </button>
                </form>

                {/* Footer */}
                <div className={`mt-8 pt-6 border-t text-center text-xs ${
                    dark ? 'border-slate-800' : 'border-gray-200'
                }`}>
                    <p className={dark ? 'text-gray-500' : 'text-gray-400'}>
                        &copy; {new Date().getFullYear()} Yayasan Pendidikan Nuurul Muttaqiin
                    </p>
                    <div className="flex items-center justify-center gap-4 mt-2">
                        <Link href="#" className={`transition-colors ${
                            dark ? 'text-gray-600 hover:text-accent' : 'text-gray-400 hover:text-primary'
                        }`}>
                            Kebijakan
                        </Link>
                        <span className={dark ? 'text-gray-700' : 'text-gray-300'}>•</span>
                        <Link href="#" className={`transition-colors ${
                            dark ? 'text-gray-600 hover:text-accent' : 'text-gray-400 hover:text-primary'
                        }`}>
                            Syarat Layanan
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
