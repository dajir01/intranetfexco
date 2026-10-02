<?php

// Cargar la clase FPDF y crear alias para compatibilidad
if (!class_exists('FPDF')) {
    require_once __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php';
}

// Crear alias en namespace App\Http\Controllers para compatibilidad
class_alias('FPDF', 'App\Http\Controllers\FPDF');
