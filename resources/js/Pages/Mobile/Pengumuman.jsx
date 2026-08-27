import React from 'react';
import { Head } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Empty } from '@/Components/MobileUI';
import { Megaphone, PinIcon, Clock3 } from 'lucide-react';
import { format, parseISO } from 'date-fns';
import { id as idLocale } from 'date-fns/locale';

export default function Pengumuman({ auth, pengumuman }) {
    return (
        <MobileLayout user={auth.user}>
            <Head title="Pengumuman" />
            <div className="mb-5">
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Pengumuman</h1>
                <p className="mt-0.5 text-sm text-slate-500">{pengumuman.length} pengumuman</p>
            </div>

            {pengumuman.length === 0 ? (
                <Empty icon={Megaphone} title="Belum ada pengumuman" subtitle="Pengumuman yayasan akan muncul di sini." />
            ) : (
                <div className="space-y-3">
                    {pengumuman.map((a) => (
                        <Card key={a.id} press={false} className={`p-4 ${a.is_pinned ? 'border-primary/30 bg-primary/[0.02]' : ''}`}>
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        {a.is_pinned && <PinIcon className="h-4 w-4 shrink-0 text-primary" />}
                                        <h2 className={`text-sm font-bold leading-snug ${a.is_pinned ? 'text-primary' : 'text-slate-900'}`}>
                                            {a.title}
                                        </h2>
                                    </div>
                                    {a.image_url && (
                                        <img src={a.image_url} alt={a.title} className="mt-2 w-full rounded-lg border border-slate-200 object-cover" />
                                    )}
                                    <p className="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-600">{a.body}</p>
                                    <div className="mt-3 flex items-center gap-4 text-[11px] text-slate-400">
                                        <span className="inline-flex items-center gap-1"><Clock3 className="h-3 w-3" />{a.published_at ? format(parseISO(a.published_at), 'd MMM yyyy, HH:mm', { locale: idLocale }) : '-'}</span>
                                        {a.creator && <span>{a.creator.name}</span>}
                                    </div>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}
        </MobileLayout>
    );
}
