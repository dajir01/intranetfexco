<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name', 'Sistema') }}</title>
</head>
<body style="margin:0; padding:0; background:#f5f7fb; font-family:Arial, sans-serif; color:#1f2937;">
@php
    $logoUrl = rtrim(config('app.url'), '/') . '/images/fexco.png';
@endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" max-width="640" cellpadding="0" cellspacing="0" style="max-width:640px; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="padding:0; background:#0f172a;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:20px 24px; color:#ffffff; font-size:22px; font-weight:bold; letter-spacing:.2px;">
                                        Sistema Fexco
                                    </td>
                                    <td align="right" style="padding:12px 16px; width:86px;">
                                        <div style="padding:7px 10px; background:#ffffff; border-radius:7px; line-height:0;">
                                            <img src="{{ $logoUrl }}" alt="Fexco" width="66" style="display:block; width:66px; height:auto; border:0;">
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <div style="height:4px; background:#c21868; font-size:0; line-height:0;">&nbsp;</div>
                            <div style="height:2px; background:#35c6d0; font-size:0; line-height:0;">&nbsp;</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px 16px;">
                            <h2 style="margin:0 0 12px; font-size:24px; color:#111827;">{{ $title ?? 'Notificación del sistema' }}</h2>
                            <div style="line-height:1.7; font-size:15px; color:#374151;">
                                @yield('content')
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px 24px;">
                            @hasSection('action')
                                <div style="margin:12px 0 18px;">
                                    @yield('action')
                                </div>
                            @endif
                            <p style="margin:0; font-size:12px; color:#6b7280; line-height:1.6;">
                                Este es un correo automático generado por el sistema. No responda a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
