<?php

namespace App\Support;

class PinAcreditacionCorreo
{
    public static function html(string $nombreFeria, string $nombreEmpresa, array $pines): string
    {
        $logoUrl = rtrim((string) config('app.url'), '/').'/images/fexco.png';
        $feria = e($nombreFeria);
        $empresa = e($nombreEmpresa);
        $cantidad = count($pines);
        $plural = $cantidad === 1 ? '' : 's';
        $filasPines = collect($pines)->map(function (array $pin): string {
            $codigo = e((string) ($pin['pin'] ?? ''));
            $tipo = e((string) ($pin['tipo'] ?? ''));

            return <<<HTML
<tr>
    <td style="padding:12px 14px;border-top:1px solid #e5e7eb;color:#111827;font-size:17px;font-weight:bold;letter-spacing:1px;">{$codigo}</td>
    <td align="right" style="padding:12px 14px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:13px;">{$tipo}</td>
</tr>
HTML;
        })->implode('');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>PINs de autoacreditación</title></head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;padding:32px 0;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
<tr><td style="padding:0;background:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
<td style="padding:20px 24px;color:#ffffff;font-size:22px;font-weight:bold;letter-spacing:.2px;">Sistema Fexco</td>
<td align="right" style="padding:12px 16px;width:86px;"><div style="padding:7px 10px;background:#ffffff;border-radius:7px;line-height:0;"><img src="{$logoUrl}" alt="Fexco" width="66" style="display:block;width:66px;height:auto;border:0;"></div></td>
</tr></table>
<div style="height:4px;background:#c21868;font-size:0;line-height:0;">&nbsp;</div><div style="height:2px;background:#35c6d0;font-size:0;line-height:0;">&nbsp;</div>
</td></tr>
<tr><td style="padding:28px 24px 16px;">
<h2 style="margin:0 0 12px;font-size:24px;color:#111827;">PINs de autoacreditación</h2>
<div style="line-height:1.7;font-size:15px;color:#374151;">
<div style="margin:0 0 22px;padding:18px 20px;background:#eff6ff;border-left:4px solid #2563eb;border-radius:8px;">
<p style="margin:0 0 5px;color:#1d4ed8;font-size:13px;font-weight:bold;text-transform:uppercase;letter-spacing:.6px;">Asignación de PIN{$plural}</p>
<p style="margin:0;color:#374151;font-size:15px;line-height:1.6;">Estimado/a <strong>{$empresa}</strong>, le hacemos llegar {$cantidad} PIN{$plural} para la generación de Credenciales Digitales de acceso a <strong>{$feria}</strong>.</p>
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;border-collapse:separate;">
<tr><td colspan="2" style="padding:14px 16px;background:#f8fafc;color:#111827;font-size:15px;font-weight:bold;">Detalle de PIN{$plural} asignado{$plural}</td></tr>
{$filasPines}
</table>
<div style="margin-top:20px;padding:16px 18px;background:#fff7ed;border-left:4px solid #f97316;border-radius:8px;">
<p style="margin:0 0 7px;color:#9a3412;font-weight:bold;">Asignación y uso del PIN</p>
<ul style="margin:0;padding-left:20px;color:#4b5563;">
<li>Si usted recibió este correo, es responsable de distribuir internamente los PINs a su personal destinado a asistir a la feria.</li>
<li>Cada PIN es de uso personal e intransferible, no puede ser compartido ni utilizado por más de una persona.</li>
<li>La información relacionada con el PIN es confidencial y debe manejarse con la debida precaución.</li>
<li>Cualquier incumplimiento de las Políticas y Condiciones de Uso del Credencial Digital puede anular el credencial.</li>
</ul></div>
<div style="margin-top:20px;padding:16px 18px;background:#ecfdf5;border-radius:8px;color:#166534;">
<p style="margin:0 0 7px;font-weight:bold;">Acreditación en la Web</p>
<p style="margin:0;line-height:1.6;">Ingrese a <a href="https://acreditacion.fexco.com.bo/" style="color:#0f766e;font-weight:bold;">acreditacion.fexco.com.bo</a> y utilice uno de los PINs asignados para completar su acreditación.</p>
</div>
<p style="margin:20px 0 0;">El Credencial Digital tiene validez durante todo el periodo de duración del evento. Nos vemos en <strong>{$feria}</strong>.</p>
</div></td></tr>
<tr><td style="padding:16px 24px 24px;">
<div style="margin:12px 0 18px;"><a href="https://acreditacion.fexco.com.bo/" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold;">INGRESAR A LA ACREDITACIÓN</a></div>
<p style="margin:0;font-size:12px;color:#6b7280;line-height:1.6;">Este es un correo automático generado por el sistema. No responda a este mensaje.</p>
<p style="margin:18px 0 0;font-size:11px;color:#6b7280;line-height:1.55;">Este correo electrónico y sus anexos son confidenciales y están dirigidos únicamente al destinatario. La copia electrónica o física y posterior distribución no está permitida.</p>
</td></tr>
</table></td></tr></table>
</body></html>
HTML;
    }
}
