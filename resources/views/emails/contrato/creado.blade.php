@extends('emails.layouts.master')

@php
    $empresa = data_get($data, 'empresa', 'Empresa no especificada');
    $title = 'Nuevo contrato generado - Empresa ' . $empresa;
@endphp

@section('content')
    <div style="margin:0 0 22px; padding:18px 20px; background:#eff6ff; border-left:4px solid #2563eb; border-radius:8px;">
        <p style="margin:0 0 5px; color:#1d4ed8; font-size:13px; font-weight:bold; text-transform:uppercase; letter-spacing:.6px;">Nuevo contrato generado</p>
        <p style="margin:0; color:#374151; font-size:15px; line-height:1.6;">Se ha registrado un nuevo contrato en el sistema para la empresa <strong>{{ $empresa }}</strong>.</p>
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; border-collapse:separate;">
        <tr>
            <td colspan="2" style="padding:14px 16px; background:#f8fafc; color:#111827; font-size:15px; font-weight:bold;">Detalle del contrato</td>
        </tr>
        <tr>
            <td style="width:42%; padding:11px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f3;">Empresa</td>
            <td style="padding:11px 16px; color:#111827; font-size:14px; border-top:1px solid #eef0f3;">{{ $empresa }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f3;">Pabellón</td>
            <td style="padding:11px 16px; color:#111827; font-size:14px; border-top:1px solid #eef0f3;">{{ data_get($data, 'pabellon', 'N/A') }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f3;">Stand(s)</td>
            <td style="padding:11px 16px; color:#111827; font-size:14px; border-top:1px solid #eef0f3;">{{ data_get($data, 'stand', 'N/A') }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f3;">Precio total</td>
            <td style="padding:11px 16px; color:#111827; font-size:14px; border-top:1px solid #eef0f3;">Bs. {{ number_format((float) data_get($data, 'precio_total', 0), 2, '.', ',') }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f3;">Fecha</td>
            <td style="padding:11px 16px; color:#111827; font-size:14px; border-top:1px solid #eef0f3;">{{ data_get($data, 'fecha', now()->format('d/m/Y')) }}</td>
        </tr>
    </table>

    <div style="margin-top:20px; padding:13px 16px; background:#ecfdf5; border-radius:8px; color:#166534; font-size:13px; line-height:1.5;">
        <strong>Estado:</strong> {{ data_get($data, 'estado', 'Generado') }}
        <span style="color:#6b7280;"> · Registrado por {{ data_get($data, 'usuario', 'el sistema') }}</span>
    </div>
@endsection

@section('action')
    @if(!empty(data_get($data, 'url')))
        <a href="{{ data_get($data, 'url') }}" style="display:inline-block; background:#0f766e; color:#ffffff; text-decoration:none; padding:12px 18px; border-radius:8px; font-weight:bold;">VER CONTRATO</a>
    @endif
@endsection
