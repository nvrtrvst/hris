import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { Send, MessageCircle, Lock } from 'lucide-react';

export default function ThreadChat({ pengajuanId, isLocked, currentUserId, commentsRoute, replyRoute }) {
    const [comments, setComments] = useState([]);
    const [message, setMessage] = useState('');
    const [loading, setLoading] = useState(true);
    const [sending, setSending] = useState(false);
    const messagesEndRef = useRef(null);
    const inputRef = useRef(null);

    const fetchComments = async () => {
        try {
            const res = await axios.get(route(commentsRoute, pengajuanId));
            setComments(res.data.comments || []);
        } catch (e) {
            console.warn('Gagal memuat thread:', e);
        }
        setLoading(false);
    };

    useEffect(() => {
        fetchComments();
    }, [pengajuanId]);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [comments]);

    const handleSend = async (e) => {
        e.preventDefault();
        const text = message.trim();
        if (!text || sending) return;

        // Optimistic: tambah temporary comment langsung
        const tempId = `temp-${Date.now()}`;
        const optimisticComment = {
            id: tempId,
            user_id: currentUserId,
            user: { id: currentUserId, name: 'Anda' },
            message: text,
            created_at: new Date().toISOString(),
            _pending: true,
        };

        setComments(prev => [...prev, optimisticComment]);
        setMessage('');
        setSending(true);

        try {
            const res = await axios.post(route(replyRoute, pengajuanId), {
                message: text,
            });
            // Replace temp dengan real comment
            setComments(prev => prev.map(c => c.id === tempId ? { ...res.data.comment, _pending: false } : c));
        } catch (e) {
            // Rollback: hapus temp comment, restore message
            setComments(prev => prev.filter(c => c.id !== tempId));
            setMessage(text);
            alert(e.response?.data?.message || 'Gagal mengirim balasan.');
        }
        setSending(false);
        inputRef.current?.focus();
    };

    const formatTime = (dateStr) => {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) +
            ' ' + d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    };

    return (
        <div className="border-t border-border pt-4 mt-4">
            <div className="flex items-center gap-2 mb-3">
                <MessageCircle className="w-4 h-4 text-primary" />
                <span className="text-sm font-bold text-text-primary">
                    Thread Percakapan
                </span>
                {comments.length > 0 && (
                    <span className="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full font-semibold">
                        {comments.length}
                    </span>
                )}
                {isLocked && (
                    <span className="text-xs bg-gray-100 text-text-muted px-2 py-0.5 rounded-full flex items-center gap-1">
                        <Lock className="w-3 h-3" /> Terkunci
                    </span>
                )}
            </div>

            {loading ? (
                <div className="text-center py-4 text-sm text-text-muted">Memuat...</div>
            ) : (
                <div className="max-h-[200px] sm:max-h-[300px] overflow-y-auto space-y-3 mb-3 bg-surface/50 rounded-card p-3 border border-border">
                    {comments.length === 0 && (
                        <div className="text-center py-4 text-sm text-text-muted">
                            Belum ada percakapan. Mulai diskusi jika perlu klarifikasi.
                        </div>
                    )}
                    {comments.map((c) => {
                        const isMe = c.user_id === currentUserId;
                        return (
                            <div key={c.id} className={`flex ${isMe ? 'justify-end' : 'justify-start'}`}>
                                <div className={`max-w-[85%] sm:max-w-[75%] rounded-lg px-3 py-2 ${
                                    isMe
                                        ? 'bg-primary text-white'
                                        : 'bg-white border border-border text-text-primary'
                                } ${c._pending ? 'opacity-60' : ''}`}>
                                    {!isMe && (
                                        <p className="text-[10px] font-bold mb-0.5 opacity-70">
                                            {c.user?.name}
                                        </p>
                                    )}
                                    <p className="text-sm leading-relaxed whitespace-pre-wrap">{c.message}</p>
                                    <p className={`text-[10px] mt-1 ${isMe ? 'text-white/60' : 'text-text-muted'}`}>
                                        {formatTime(c.created_at)}
                                    </p>
                                </div>
                            </div>
                        );
                    })}
                    <div ref={messagesEndRef} />
                </div>
            )}

            {!isLocked && (
                <form onSubmit={handleSend} className="flex gap-2">
                    <input
                        ref={inputRef}
                        type="text"
                        value={message}
                        onChange={(e) => setMessage(e.target.value)}
                        placeholder="Ketik balasan..."
                        className="input-field flex-1 text-sm"
                        maxLength={1000}
                        disabled={sending}
                    />
                    <button
                        type="submit"
                        disabled={!message.trim() || sending}
                        className="btn-primary px-4 py-2 disabled:opacity-50 flex items-center justify-center min-w-[40px]"
                    >
                        {sending ? (
                            <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                        ) : (
                            <Send className="w-4 h-4" />
                        )}
                    </button>
                </form>
            )}
        </div>
    );
}
