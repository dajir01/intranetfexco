<?php

namespace Tests\Feature;

use App\Http\Controllers\CredencialController;
use App\Mail\Credenciales\AccesosEmpresaMail;
use App\Services\CredentialPhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class CredencialControllerTest extends TestCase
{
    public function test_it_extracts_all_enabled_credential_types_for_the_selected_event(): void
    {
        $controller = new CredencialController;
        $reflected = new ReflectionClass($controller);
        $method = $reflected->getMethod('tiposCredenciales');
        $method->setAccessible(true);

        $tipos = $method->invoke($controller, 'Expositor;Prensa;Oficial;');

        $this->assertSame(['Expositor', 'Prensa', 'Oficial'], $tipos->all());
        $this->assertContains('Prensa', $tipos);
    }

    public function test_it_maps_all_supported_credential_types_to_their_codes(): void
    {
        $controller = new CredencialController;
        $reflected = new ReflectionClass($controller);
        $method = $reflected->getMethod('tipoCredencialCodigo');
        $method->setAccessible(true);

        $this->assertSame([
            'Expositor' => 1,
            'Prensa' => 2,
            'Oficial' => 3,
            'Servicios' => 4,
            'Negocios' => 5,
        ], collect(['Expositor', 'Prensa', 'Oficial', 'Servicios', 'Negocios'])
            ->mapWithKeys(fn (string $tipo) => [$tipo => $method->invoke($controller, $tipo)])
            ->all());
    }

    public function test_accredited_person_photos_are_stored_on_the_private_disk(): void
    {
        Storage::fake('credential_photos');

        $storedPath = (new CredentialPhotoStorage)->store(UploadedFile::fake()->image('persona.jpg'));

        $filename = basename($storedPath);
        Storage::disk('credential_photos')->assertExists($filename);
        $this->assertStringStartsWith('images/fotos/acreditado_', $storedPath);
        $this->assertFileExists((new CredentialPhotoStorage)->path($storedPath));
    }

    public function test_accredited_person_photo_route_requires_authentication(): void
    {
        $this->getJson('/credenciales/fotos/acreditado_123e4567-e89b-12d3-a456-426614174000.jpg')
            ->assertUnauthorized();
    }

    public function test_company_password_route_requires_authentication(): void
    {
        $this->postJson('/credenciales/eventos/36/empresas/20/credencial-acceso')
            ->assertUnauthorized();
    }

    public function test_company_whatsapp_route_requires_authentication(): void
    {
        $this->postJson('/credenciales/eventos/36/empresas/20/whatsapp')
            ->assertUnauthorized();

        $this->postJson('/credenciales/eventos/36/empresas/20/whatsapp/preparar-copia')
            ->assertUnauthorized();

        $this->postJson('/credenciales/eventos/36/empresas/20/whatsapp/copiar')
            ->assertUnauthorized();

    }

    public function test_company_email_routes_require_authentication(): void
    {
        $this->postJson('/credenciales/eventos/36/empresas/20/email')
            ->assertUnauthorized();

        $this->getJson('/credenciales/eventos/36/empresas/20/envios/historial')
            ->assertUnauthorized();
    }

    public function test_company_access_email_uses_the_expected_credentials_and_platform_link(): void
    {
        $html = (new AccesosEmpresaMail([
            'nombre_feria' => 'Expo Construcción 2026',
            'nombre_empresa' => 'Empresa de prueba',
            'usuario' => 'usuario-prueba',
            'password' => 'clave-prueba',
            'cantidad_credenciales' => 12,
        ]))->render();

        $this->assertStringContainsString('Expo Construcción 2026', $html);
        $this->assertStringContainsString('Empresa de prueba', $html);
        $this->assertStringContainsString('usuario-prueba', $html);
        $this->assertStringContainsString('clave-prueba', $html);
        $this->assertStringContainsString('12', $html);
        $this->assertStringContainsString('https://plataforma.fexco.com.bo/', $html);
    }

    public function test_credential_delivery_history_masks_email_and_phone_recipients(): void
    {
        $controller = new CredencialController;
        $method = (new ReflectionClass($controller))->getMethod('enmascararDestinatario');
        $method->setAccessible(true);

        $this->assertSame('s***@e***.com', $method->invoke($controller, 'email', 'secret@example.com'));
        $this->assertSame('+***1034', $method->invoke($controller, 'whatsapp', '59168481034'));
    }

    public function test_credential_email_rejects_non_delivery_mail_transports_and_log_fallbacks(): void
    {
        $controller = new CredencialController;
        $method = (new ReflectionClass($controller))->getMethod('usaTransporteCorreoNoEntregable');
        $method->setAccessible(true);
        config(['mail.mailers.audit-log-url' => [
            'transport' => 'smtp',
            'url' => 'log://',
        ]]);
        config(['mail.mailers.smtp.require_tls' => false]);

        $this->assertTrue($method->invoke($controller, 'log'));
        $this->assertTrue($method->invoke($controller, 'array'));
        $this->assertTrue($method->invoke($controller, 'failover'));
        $this->assertTrue($method->invoke($controller, 'audit-log-url'));
        $this->assertTrue($method->invoke($controller, 'smtp'));
        config(['mail.mailers.smtp.require_tls' => true]);
        $this->assertFalse($method->invoke($controller, 'smtp'));
    }

    public function test_it_normalizes_bolivian_and_international_company_phone_numbers_for_whatsapp(): void
    {
        $controller = new CredencialController;
        $method = (new ReflectionClass($controller))->getMethod('normalizarCelular');
        $method->setAccessible(true);

        $this->assertSame('+59168481034', $method->invoke($controller, '6848 1034'));
        $this->assertSame('+5491123456789', $method->invoke($controller, '+54 9 11 2345-6789'));
    }

    public function test_public_auto_accreditation_rejects_pins_that_are_not_six_characters(): void
    {
        foreach (['A7K2P', 'A7K2P9M4Q7TX'] as $pin) {
            $this->postJson('/api/autoacreditacion/validar-pin', ['pin' => $pin])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['pin']);

            $this->postJson('/api/autoacreditacion/registrar', [
                'pin' => $pin,
                'ci' => '123456',
                'tipo_documento' => 'Cédula de identidad',
                'nombre' => 'Persona de prueba',
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['pin']);
        }
    }

    public function test_it_generates_a_png_qr_for_digital_credentials_without_imagick(): void
    {
        $controller = new CredencialController;
        $reflected = new ReflectionClass($controller);
        $method = $reflected->getMethod('generarQrPng');
        $method->setAccessible(true);
        $path = storage_path('app/qr-credencial-test.png');

        try {
            $method->invoke($controller, base64_encode('123_456'), $path);

            $this->assertFileExists($path);
            $this->assertSame(IMAGETYPE_PNG, exif_imagetype($path));
        } finally {
            File::delete($path);
        }
    }
}
