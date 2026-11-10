<?php

namespace Tests\Unit;

use App\Models\Empresa;
use PHPUnit\Framework\TestCase;

class EmpresaSerializationTest extends TestCase
{
    public function test_company_password_is_hidden_from_model_serialization(): void
    {
        $empresa = new Empresa([
            'nombre_empresa' => 'Empresa de prueba',
            'pass' => 'secreto',
        ]);

        $this->assertArrayNotHasKey('pass', $empresa->toArray());
    }
}
