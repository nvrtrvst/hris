<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atur Ulang Kata Sandi — {{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f7f5;font-family:'Segoe UI',Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f7f5;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(15,61,62,0.08);">

                    {{-- Header teal --}}
                    <tr>
                        <td style="background-color:#0F3D3E;padding:28px 36px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="color:#ffffff;font-size:17px;font-weight:700;letter-spacing:0.2px;">
                                        Yayasan Nuurul Muttaqiin
                                    </td>
                                    <td align="right" style="color:#80B8B1;font-size:10px;letter-spacing:2.5px;text-transform:uppercase;font-weight:700;">
                                        HRIS Portal
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:36px;">
                            <h1 style="margin:0 0 18px;font-size:20px;font-weight:700;color:#0f172a;">
                                Atur ulang kata sandi Anda
                            </h1>

                            <p style="margin:0 0 16px;font-size:14px;color:#334155;line-height:1.7;">
                                Halo <strong>{{ $name }}</strong>,
                            </p>
                            <p style="margin:0 0 16px;font-size:14px;color:#334155;line-height:1.7;">
                                Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda di portal HRIS
                                Yayasan. Klik tombol di bawah untuk membuat kata sandi baru.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:26px 0 28px;">
                                <tr>
                                    <td>
                                        <a href="{{ $url }}" style="display:inline-block;background-color:#0F3D3E;color:#ffffff;padding:14px 34px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none;letter-spacing:0.2px;">
                                            Atur Ulang Kata Sandi
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F0F7F6;border:1px solid #D9EAE8;border-radius:10px;padding:16px 18px;">
                                <tr>
                                    <td style="font-size:12.5px;color:#1A5A57;line-height:1.8;">
                                        <strong style="font-size:13px;">Perhatian</strong><br>
                                        • Tautan ini berlaku <strong>60 menit</strong> dan hanya bisa digunakan <strong>sekali</strong>.<br>
                                        • Jika Anda tidak meminta reset ini, abaikan email ini — kata sandi Anda tidak akan berubah.
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:22px 0 0;font-size:12.5px;color:#64748b;line-height:1.7;">
                                Mengalami kendala? Hubungi admin unit Anda.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:22px 36px;border-top:1px solid #e2e8f0;background-color:#f8fafc;">
                            <p style="margin:0;font-size:11px;color:#94a3b8;line-height:1.7;">
                                Email ini dikirim otomatis oleh sistem HRIS untuk <strong>{{ $email }}</strong>. Mohon tidak membalas email ini.
                            </p>
                            <p style="margin:8px 0 0;font-size:11px;color:#94a3b8;">
                                &copy; {{ date('Y') }} Yayasan Nuurul Muttaqiin
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
