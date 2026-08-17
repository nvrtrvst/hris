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

                    {{-- Gold accent strip --}}
                    <tr>
                        <td style="background-color:#C9A227;height:4px;line-height:0;font-size:0;">&nbsp;</td>
                    </tr>

                    {{-- Header utama --}}
                    <tr>
                        <td style="background-color:#0F3D3E;padding:26px 36px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td valign="top">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="border-left:3px solid #C9A227;padding-left:14px;">
                                                    <p style="margin:0;color:#ffffff;font-size:18px;font-weight:700;letter-spacing:0.2px;line-height:1.3;">
                                                        Yayasan Nuurul Muttaqiin
                                                    </p>
                                                    <p style="margin:5px 0 0;color:#9CC9C3;font-size:11px;letter-spacing:0.4px;font-weight:600;">
                                                        Sistem Informasi Sumber Daya Manusia
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td align="right" valign="top">
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin-bottom:10px;">
                                            <tr>
                                                <td align="center" valign="middle" style="width:118px;height:118px;border-radius:59px;background-color:rgba(201,162,39,0.08);border:1px solid rgba(201,162,39,0.25);">
                                                    <p style="margin:0;color:#C9A227;font-size:36px;font-weight:800;letter-spacing:3px;line-height:1;">NM</p>
                                                    <p style="margin:4px 0 0;color:#9CC9C3;font-size:8px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;">Est. Yayasan</p>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="margin:0;color:#C9A227;font-size:10px;letter-spacing:3px;text-transform:uppercase;font-weight:700;">
                                            HRIS Portal
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Band unit (sedikit lebih gelap untuk kesan kedalaman) --}}
                    <tr>
                        <td style="background-color:#0B3334;padding:6px 36px 26px;">
                            {{-- Divider berlian emas --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
                                <tr>
                                    <td style="border-bottom:1px solid #1C5455;line-height:0;font-size:0;">&nbsp;</td>
                                    <td width="36" align="center" style="color:#C9A227;font-size:9px;line-height:9px;padding:0 6px;">&#9670;</td>
                                    <td style="border-bottom:1px solid #1C5455;line-height:0;font-size:0;">&nbsp;</td>
                                </tr>
                            </table>

                            {{-- Logo unit --}}
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    @foreach ($unitLogos as $unit)
                                        <td align="center" style="padding:0 6px;">
                                            <table role="presentation" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td align="center" style="background-color:#ffffff;border-radius:10px;border:1px solid #DCEAE7;padding:6px 8px;box-shadow:0 3px 10px rgba(0,0,0,0.18);">
                                                        <img src="{{ $unit['logo'] }}" alt="{{ $unit['nama'] }}" width="72" height="23" style="display:block;width:72px;height:23px;">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td align="center" style="padding-top:7px;color:#9CC9C3;font-size:9.5px;font-weight:700;letter-spacing:1.5px;">
                                                        {{ $unit['nama'] }}
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    @endforeach
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
                                        <a href="{{ $url }}" style="display:inline-block;background-color:#C9A227;color:#0F3D3E;padding:14px 34px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none;letter-spacing:0.2px;box-shadow:0 4px 14px rgba(201,162,39,0.35);">
                                            Atur Ulang Kata Sandi
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F0F7F6;border:1px solid #D9EAE8;border-radius:10px;padding:16px 18px;">
                                <tr>
                                    <td style="font-size:12.5px;color:#1A5A57;line-height:1.8;">
                                        <strong style="font-size:13px;">Perhatian</strong><br>
                                        &bull; Tautan ini berlaku <strong>60 menit</strong> dan hanya bisa digunakan <strong>sekali</strong>.<br>
                                        &bull; Jika Anda tidak meminta reset ini, abaikan email ini &mdash; kata sandi Anda tidak akan berubah.
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
