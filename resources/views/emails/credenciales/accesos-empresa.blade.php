@extends('emails.layouts.master')

@section('content')
    <div style="margin:0 0 22px; padding:18px 20px; background:#eff6ff; border-left:4px solid #2563eb; border-radius:8px;">
        <p style="margin:0 0 5px; color:#1d4ed8; font-size:13px; font-weight:bold; text-transform:uppercase; letter-spacing:.6px;">Acceso a la acreditación</p>
        <p style="margin:0; color:#374151; font-size:15px; line-height:1.6;">
            Hola, te estamos contactando para que puedas ingresar al Sistema de Acreditación del evento
            <strong>{{ data_get($data, 'nombre_feria', 'N/A') }}</strong>.
        </p>
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; border-collapse:separate;">
        <tr>
            <td colspan="2" style="padding:14px 16px; background:#f8fafc; color:#111827; font-size:15px; font-weight:bold;">Datos de acceso</td>
        </tr>
        <tr>
            <td style="width:42%; padding:11px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f3;">Empresa</td>
            <td style="padding:11px 16px; color:#111827; font-size:14px; border-top:1px solid #eef0f3;">{{ data_get($data, 'nombre_empresa', 'N/A') }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f3;">{{ data_get($data, 'tipo_usuario', 'Usuario') }}</td>
            <td style="padding:11px 16px; color:#111827; font-size:14px; border-top:1px solid #eef0f3;">{{ data_get($data, 'usuario', 'N/A') }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f3;">Contraseña</td>
            <td style="padding:11px 16px; color:#111827; font-size:14px; border-top:1px solid #eef0f3;">{{ data_get($data, 'password', 'N/A') }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f3;">Cantidad de credenciales</td>
            <td style="padding:11px 16px; color:#111827; font-size:14px; border-top:1px solid #eef0f3;">{{ data_get($data, 'cantidad_credenciales', 0) }}</td>
        </tr>
    </table>

    <div style="margin-top:20px; padding:13px 16px; background:#ecfdf5; border-radius:8px; color:#166534; font-size:13px; line-height:1.5;">
        Ingresa a la plataforma desde el siguiente enlace:
        <a href="https://acreditacion.fexco.com.bo/" style="color:#166534; font-weight:bold;">https://acreditacion.fexco.com.bo/</a>
    </div>

    <p style="margin:20px 0 0; color:#374151; font-size:14px; line-height:1.6;">
        Si tienes alguna duda, no dudes en escribirnos al
        <a href="https://wa.me/59167599668" style="color:#166534; font-weight:bold;">67599668</a>
    </p>
@endsection
