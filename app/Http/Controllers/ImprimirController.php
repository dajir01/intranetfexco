<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use FPDF;
use PhpOffice\PhpWord\TemplateProcessor;

class ImprimirController extends Controller
{
    private const MODEL_CONTRACTS_DIR = 'images/modelocontratos';

    private function resolveModelDocumentPath(?string $documentPath): ?string
    {
        $documentPath = str_replace('\\', '/', ltrim(trim((string) $documentPath), '/'));
        if (
            $documentPath === '' ||
            !str_starts_with($documentPath, self::MODEL_CONTRACTS_DIR . '/') ||
            in_array('..', explode('/', $documentPath), true)
        ) {
            return null;
        }

        $publicPath = public_path($documentPath);
        if (File::exists($publicPath)) {
            return $publicPath;
        }

        $privatePath = Storage::disk('private')->path($documentPath);

        return File::exists($privatePath) ? $privatePath : null;
    }

    /**
     * Punto de entrada para imprimir contrato.
     * Decide entre FPDF o plantilla Word según id_modelocontrato.
     */
    public function imprimirContrato($id)
    {
        try {
            // 1. Obtener datos básicos del contrato y evento
            $evento = DB::table('contrato as c')
                ->join('eventos as ev', 'ev.id_feria', '=', 'c.id_feria')
                ->select('ev.id_modelocontrato')
                ->where('c.id_contrato', '=', $id)
                ->first();

            // 2. DECISIÓN CLAVE: ¿Existe modelo Word?
            if ($evento && $evento->id_modelocontrato != null && $evento->id_modelocontrato > 0) {
                // ✅ USAR PLANTILLA WORD
                return $this->imprimirContratoWord($id);
            } else {
                // ✅ USAR FPDF (COMPORTAMIENTO ACTUAL)
                return $this->imprimirContratoFPDF($id);
            }
        } catch (\Throwable $e) {
            Log::error('Error en punto de entrada de impresión: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Imprimir Contrato con FPDF (FUNCIÓN ACTUAL - SIN CAMBIOS).
     * Replicando el formato legacy (imprimirfv.php).
     * Bloquea la impresión si el contrato no tiene código generado (codigo_contrato == 0).
     */
    private function imprimirContratoFPDF($id)
    {
        try {
            $c = DB::table('contrato as c')
                ->join('empresas as e', 'c.id_empresa', '=', 'e.id_empresa')
                ->join('eventos as ev', 'ev.id_feria', '=', 'c.id_feria')
                ->join('localidades as l', 'l.id', '=', 'e.ciudad')
                ->join('paises as p', 'p.id', '=', 'e.pais')
                ->join('stands as st', 'st.id_stand', '=', 'c.stand_1')
                ->join('pabellones as pab', 'pab.id_pabellon', '=', 'st.id_pabellon')
                ->select('c.id_feria', 'c.codigo_contrato', 'ev.codigo_contrato as prefijo', 'ev.nombre_feria', 'ev.fecha_inicio', 'ev.fecha_fin', 'e.nombre_empresa', 'e.nit', 'e.direccion', 'ev.id_feria', 'e.email', 'e.telefono', 'e.web', 'p.nombre as pais', 'l.nombre as ciudad', 'e.fax', 'e.nombre_gerente', 'e.ci_gerente', 'e.exp_ci_gerente', 'e.fono_gerente', 'c.productos', 'c.observaciones', 'c.stand_1', 'c.stand_2', 'c.stand_3', 'c.stand_4', 'c.stand_5', 'c.stand_6', 'c.stand_7', 'c.stand_8', 'c.stand_9', 'c.stand_10', 'c.stand_11', 'c.stand_12', 'pab.nombre_pabellon', 'c.metraje_total', 'c.nro_credenciales', 'c.potencia', 'c.precio_unit', 'c.descuento', 'c.tipo_desc', 'c.precio_total_desc', 'c.porcentaje_inicial', 'c.porcentaje_final', 'c.total_stands', 'st.numero_stand', 'c.precio_total', 'c.porcentaje_inicial', 'c.porcentaje_final', 'c.monto_inicial', 'c.monto_final', 'c.fecha_inicial', 'c.fecha_final', 'c.fecha_realizacion', 'e.nr_escritura', 'e.fecha_nr_escritura', 'e.matricula', 'e.nr_poder', 'e.nr_notaria', 'e.fecha_nr_poder', 'e.distrito')
                ->where('c.id_contrato', '=', $id)
                ->first();
            setlocale(LC_ALL, 'es_ES');
            $meses = array("", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
            $fecha_imprimir = substr($c->fecha_realizacion, 8, 2) . " de " . $meses[ceil(substr($c->fecha_realizacion, 5, 2))] . " del " . substr($c->fecha_realizacion, 0, 4);
            $c->precio_total = round($c->precio_total, 2);
            $c->porcentaje_inicial = round($c->porcentaje_inicial, 2);
            $c->porcentaje_final = round($c->porcentaje_final, 2);
            if ($c->precio_total > 0) {
                $monto_literal = mb_strtoupper($this->convertir((int)$c->precio_total));
            } else {
                $monto_literal = "Cero";
            }
            if ($c->precio_total_desc > 0) {
                $monto_literal_desc = mb_strtoupper($this->convertir((int)$c->precio_total_desc));
            } else {
                $monto_literal_desc = "Cero";
            }
            if (strpos($c->precio_total, ".") === false) {
                $cent = "00/100";
            } else {
                if (strlen(substr($c->precio_total, strpos($c->precio_total, "."), 3)) == 2) {
                    $cent = substr(substr($c->precio_total, strpos($c->precio_total, ".")), 1, 1) . "0/100";
                } else {
                    $cent = substr(substr($c->precio_total, strpos($c->precio_total, ".")), 1, 2) . "/100";
                }
            }
            if (strpos($c->precio_total_desc, ".") === false) {
                $cent = "00/100";
            } else {
                if (strlen(substr($c->precio_total_desc, strpos($c->precio_total_desc, "."), 3)) == 2) {
                    $cent_desc = substr(substr($c->precio_total_desc, strpos($c->precio_total_desc, ".")), 1, 1) . "0/100";
                } else {
                    $cent_desc = substr(substr($c->precio_total_desc, strpos($c->precio_total_desc, ".")), 1, 2) . "/100";
                }
            }
            $inicial_porcentaje = $c->porcentaje_inicial;
            if (strpos($c->monto_inicial, ".") === false) {
                $inicial_cantidad = $c->monto_inicial . ".00";
            } else {
                $inicial_cantidad = $c->monto_inicial;
                if (strlen(substr(substr($c->monto_inicial, strpos($c->monto_inicial, "."), 3), 1, 2)) == 1)
                    $inicial_cantidad = $inicial_cantidad . "0";
            }
            $resto_porcentaje = $c->porcentaje_final;
            if (strpos($c->monto_final, ".") === false)
                $resto_cantidad = $c->monto_final . ".00";
            else {
                $resto_cantidad = $c->monto_final;
                if (strlen(substr(substr($c->monto_final, strpos($c->monto_final, "."), 3), 1, 2)) == 1)
                    $resto_cantidad = $resto_cantidad . "0";
            }
            if (strpos($c->precio_total, ".") === false)
                $monto1 = $c->precio_total . ".00"; //$c1["porcentaje_inicial"].".00";
            else {
                $monto1 = $c->precio_total;
                if (strlen(substr(substr($c->precio_total, strpos($c->precio_total, "."), 3), 1, 2)) == 1)
                    $monto1 = $monto1 . "0";
            }
            $sector = substr($c->numero_stand, 0, 2);
            $lote = substr($c->numero_stand, 2, 4);
            $stands = $c->numero_stand;
            $st = array($c->stand_1, $c->stand_2, $c->stand_3, $c->stand_4, $c->stand_5, $c->stand_6, $c->stand_7, $c->stand_8, $c->stand_9, $c->stand_10, $c->stand_11, $c->stand_12);
            for ($i = 1; $i < $c->total_stands; $i++) {
                $st1 = DB::table('stands')->select('numero_stand')->where('id_stand', '=', $st[$i])->first();
                $stands .= ', ' . $st1->numero_stand;
                $lote .= ', ' . substr($st1->numero_stand, 2, 4);
                unset($st1);
            }
            $nom_ma_em = strtoupper($c->nombre_empresa);
            $nom_desc = strtoupper($c->tipo_desc);
            if ($c->id_feria == 13) {
                $pdf = new FPDF();
                $pdf->AliasNbPages();
                $pdf->AddPage('P', 'legal');
                $pdf->Image(public_path('images/fexco.png'), 10, 6, 20);
                $pdf->Image(public_path('images/logo.jpg'), 194, 2, 20);
                $pdf->SetFont('Arial', 'B', 14);
                $pdf->Cell(0, 5, 'CONTRATO DE PARTICIPACION', 0, 0, 'C');
                $pdf->Ln();
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(170, 2);
                $pdf->Cell(30, 4.5, "No. " . $c->prefijo . $this->agr_ceros($c->codigo_contrato), 1);
                $pdf->Ln();
                $pdf->Cell(200, 4.5, 'Evento: ' . iconv('UTF-8', 'windows-1252', $c->nombre_feria), 1);
                $pdf->Ln();
                $pdf->Cell(60, 4.5, "Fecha de Inicio: " . $c->fecha_inicio, 'LTB');
                $pdf->Cell(140, 4.5, iconv('UTF-8', 'windows-1252', "Fecha de Finalización: ") . $c->fecha_fin, 'RTB');
                $pdf->Ln();
                $pdf->SetFont('Arial', '', 8);
                $pdf->MultiCell(200, 4, iconv('UTF-8', 'windows-1252', "Conste por el presente Contrato de Participación que suscriben -por un lado- FEICOBOL y -por otro- el EXPOSITOR, que con sólo Reconocimiento de firmas y rúbricas ante autoridad competente surtirá todos los efectos legales de un documento privado."));
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Ln(1);
                $pdf->Cell(200, 4, "PRIMERA: DE LAS PARTES");
                $pdf->Ln(4);
                $pdf->SetFont('Arial', '', 8);
                $pdf->MultiCell(200, 4, iconv('UTF-8', 'windows-1252', '1.1.	La "FUNDACION PARA LA FERIA INTERNACIONAL COCHABAMBA" (FEICOBOL), con NIT 1021689022, legalmente constituida mediante la RESOLUCION SUPREMA No.213312 otorgada por la Presidencia de la República de Bolivia, con domicilio señalado en la Av. Pando, Nº1185, Edif FEPC - 3er Piso de esta ciudad, Legalmente representada por la Lic. Eunice Acha Ferrel, con C.I. No. 2865473CB, mayor de edad hábil por derecho, en mérito al testimonio de poder No. 166/2020 que en adelante se denominará FEICOBOL.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(165, 3.5, iconv('UTF-8', 'windows-1252', "1.2. Nombre o Razón Social:"), 'LTR');
                $pdf->Cell(35, 4, iconv('UTF-8', 'windows-1252', "NIT:"), 'RTL');
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $string2 = preg_replace('/\s+/', '', iconv('UTF-8', 'windows-1252', $c->nombre_empresa));
                if (ctype_upper($string2))
                    $pdf->Cell(165, 5, iconv('UTF-8', 'windows-1252', $c->nombre_empresa), 'LBR');
                else
                    $pdf->Cell(165, 5, iconv('UTF-8', 'windows-1252', $c->nombre_empresa), 'LBR');
                $pdf->Cell(35, 5, iconv('UTF-8', 'windows-1252', $c->nit), 'RBL');
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(165, 7, iconv('UTF-8', 'windows-1252', "Dirección:"), 'LTR');
                $pdf->Cell(35, 7, iconv('UTF-8', 'windows-1252', "Telefono:"), 'RTL');
                $pdf->Ln(6);
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(165, 5, iconv('UTF-8', 'windows-1252', $c->direccion), 'LBR');
                $pdf->Cell(35, 5, iconv('UTF-8', 'windows-1252', $c->telefono), 'LBR');
                $pdf->Ln(5);
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(20, 6, iconv('UTF-8', 'windows-1252', "E-mail:"), 'LT');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(80, 6, iconv('UTF-8', 'windows-1252', $c->email), 'TR');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(20, 6, iconv('UTF-8', 'windows-1252', "Pagina Web:"), 'LT');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(80, 6, $c->web, 'RT');
                $pdf->Ln(6);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(20, 5, iconv('UTF-8', 'windows-1252', "Ciudad:"), 'LBT');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(130, 5, iconv('UTF-8', 'windows-1252', $c->ciudad), 'TBR');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(12, 5, iconv('UTF-8', 'windows-1252', "País:"), 'LBT');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(38, 5, $c->pais, 'TBR');
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(140, 7, iconv('UTF-8', 'windows-1252', "Nombre del Representante Legal y/o Responsable de Participación:"), 'LTR');
                $pdf->Cell(30, 7, iconv('UTF-8', 'windows-1252', "CI:"), 'LTR');
                $pdf->Cell(30, 7, iconv('UTF-8', 'windows-1252', "Teléfono:"), 'LTR');
                $pdf->Ln(6);
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(140, 5, iconv('UTF-8', 'windows-1252', $c->nombre_gerente), 'LBR');
                $pdf->Cell(30, 5, iconv('UTF-8', 'windows-1252', $c->ci_gerente . " " . $c->exp_ci_gerente), 'LBR');
                $pdf->Cell(30, 5, iconv('UTF-8', 'windows-1252', $c->fono_gerente), 'LBR');
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(200, 7, iconv('UTF-8', 'windows-1252', "Productos a Exponer:"), 'LTR');
                $pdf->Ln(6);
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(200, 5, iconv('UTF-8', 'windows-1252', $c->productos), 'LR');
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(200, 7, iconv('UTF-8', 'windows-1252', "Observaciones:"), 'LR');
                $pdf->Ln(6);
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(200, 5, $c->observaciones, 'LBR');
                $pdf->Ln();
                $pdf->SetFont('Arial', '', 8);
                $pdf->MultiCell(200, 4, iconv('UTF-8', 'windows-1252', "En adelante y para fines del presente contrato se denomina el EXPOSITOR."));
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Ln(0.2);
                $pdf->Cell(200, 4, "SEGUNDA: OBJETO");
                $pdf->Ln(4);
                $pdf->SetFont('Arial', '', 8);
                $pdf->MultiCell(200, 4, iconv('UTF-8', 'windows-1252', 'FEICOBOL tiene como objetivo organizar e impulsar el desarrollo de ferias internacionales, nacionales, monográficas, especializadas y otros eventos en formato presencial, virtual e hibrido, motivo por el cual suscribe este documento de participación por el tiempo que dure el presente evento "' . $c->nombre_feria . '", para que realice única y exclusivamente la exposición de los productos y mercaderías indicadas en la Cláusula Primera que antecede.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(200, 4, "TERCERA: UBICACION, COSTO TOTAL Y FORMA DE PAGO");
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(100, 7, iconv('UTF-8', 'windows-1252', "UBICACION"), 'LTR', 0, 'C');
                $pdf->Cell(100, 7, iconv('UTF-8', 'windows-1252', "ESPECIFICACIONES"), 'LTR', 0, 'C');
                $pdf->Ln(6);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(30, 5, iconv('UTF-8', 'windows-1252', "Nombre Pabellón:"), 'L', 0, 'R');
                $pdf->SetFont('Arial', '', 8);
                // $pdf->Cell(70, 5, $c->nombre_pabellon, 'R');
                $pdf->Cell(70, 5, iconv('UTF-8', 'windows-1252', $c->nombre_pabellon), 'R');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(30, 5, iconv('UTF-8', 'windows-1252', "Superficie:"), 'L', 0, 'R');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(20, 5, $c->metraje_total . " m2", '');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(30, 5, iconv('UTF-8', 'windows-1252', "Credenciales:"), '', 0, 'R');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(20, 5, $c->nro_credenciales, 'R');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(30, 5, iconv('UTF-8', 'windows-1252', "Stand:"), 'LB', 0, 'R');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(70, 5, $stands, 'B');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(30, 5, iconv('UTF-8', 'windows-1252', "Costo m2:"), 'LB', 0, 'R');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(20, 5, "Bs. " . $c->precio_unit, 'B');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(30, 5, iconv('UTF-8', 'windows-1252', "Potencia Contratada:"), 'B', 0, 'R');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(20, 5, $c->potencia . "W", 'RB');
                $pdf->Ln(5);
                $pdf->SetFont('Arial', '', 8);
                if ($c->descuento > 0) {
                    $pdf->MultiCell(200, 4, iconv('UTF-8', 'windows-1252', 'La superficie detallada anteriormente tiene un costo total de Bs. ' . ($monto1) . ' (' . $monto_literal . ' ' . $cent . ' Bolivianos). Se ha aplicado un descuento ' . $nom_desc . ' del ' . $c->descuento . '%, resultando un total de Bs. ' . $c->precio_total_desc . ' (' . $monto_literal_desc . ' ' . $cent_desc . ' Bolivianos), que serán cancelados de la siguiente manera:'));
                } else {
                    $pdf->MultiCell(200, 4, iconv('UTF-8', 'windows-1252', 'La superficie detallada anteriormente tiene un costo total de Bs. ' . ($monto1) . ' (' . $monto_literal . ' ' . $cent . ' Bolivianos), que serán cancelados de la siguiente manera'));
                }

                $pdf->Ln(0.1);
                if ($inicial_cantidad > 0) {
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell(15, 7, iconv('UTF-8', 'windows-1252', $inicial_porcentaje), 'LT', 0, 'R');
                    $pdf->SetFont('Arial', '', 8);
                    $pdf->Cell(35, 7, iconv('UTF-8', 'windows-1252', "% equivalente a"), 'T');
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell(15, 7, iconv('UTF-8', 'windows-1252', $inicial_cantidad), 'T', 0, 'R');
                    $pdf->SetFont('Arial', '', 8);
                    $pdf->Cell(100, 7, iconv('UTF-8', 'windows-1252', "/100 Bolivianos a la firma del presente documento"), 'T');
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell(35, 7, iconv('UTF-8', 'windows-1252', substr($c->fecha_inicial, 8, 2) . "/" . substr($c->fecha_inicial, 5, 2) . "/" . substr($c->fecha_inicial, 0, 4)), 'TR');
                    $pdf->Ln(6);
                }
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(15, 7, iconv('UTF-8', 'windows-1252', $resto_porcentaje), 'LT', 0, 'R');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(35, 7, iconv('UTF-8', 'windows-1252', "% equivalente a"), 'T');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(15, 7, iconv('UTF-8', 'windows-1252', $resto_cantidad), 'T', 0, 'R');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(100, 7, iconv('UTF-8', 'windows-1252', "/100 Bolivianos, impostergablemente hasta el"), 'T');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(35, 7, iconv('UTF-8', 'windows-1252', substr($c->fecha_final, 8, 2) . "/" . substr($c->fecha_final, 5, 2) . "/" . substr($c->fecha_final, 0, 4)), 'TR');
                $pdf->Ln(6);
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->MultiCell(200, 4, iconv('UTF-8', 'windows-1252', 'En caso de que el EXPOSITOR no cumpla con este pago;  FEICOBOL -a través de FEXCO-  podrá disponer del espacio, sin derecho a reclamo alguno por parte del EXPOSITOR, reconociendo el total comprometido en pago como suma liquida y exigible con plazo vencido, incurriendo en mora automática en caso de incumplimiento de pago en  el plazo establecido, habilitando la via judicial ejecutiva a los efectos de garantizar el cumplimiento de las obligaciones de pago asumidas, el cual es reconocido por ambas partes como resarcimiento de daños y perjuicios por el daño ocasionado ante el incumplimiento del contrato. Consolidando a su vez el anticipo cancelado en favor de FEICOBOL.'), 'LBR');
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Ln(1);
                $pdf->Cell(200, 4, "CUARTA: ACLARACION");
                $pdf->Ln(3);
                if ($inicial_cantidad > 0) {
                    if ($c->descuento > 0) {
                        $pdf->Image(dirname(base_path()) . '/intranet/public/qr_expositor2025.png', 188, 212, 18, 18);
                    } else {
                        $pdf->Image(dirname(base_path()) . '/intranet/public/qr_expositor2025.png', 188, 204, 18, 18);
                    }
                } else {
                    if ($c->descuento > 0) {
                        $pdf->Image(dirname(base_path()) . '/intranet/public/qr_expositor2025.png', 188, 206, 18, 18);
                    } else {
                        $pdf->Image(dirname(base_path()) . '/intranet/public/qr_expositor2025.png', 188, 198, 18, 18);
                    }
                }

                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(10, 4, iconv('UTF-8', 'windows-1252', 'Se aclara de manera expresa que el ADEMDUM # 1 y el REGLAMENTO GENERAL DE PARTICIPACION DE LA FEXCO 2025, forman parte indivisible del'));
                $pdf->Ln(3);
                $pdf->Cell(10, 4, iconv('UTF-8', 'windows-1252', 'presente contrato  y estarán disponibles para el EXPOSITOR en un link o código QR que se le entregará en el momento de suscribir el presente '));
                $pdf->Ln(3);
                $pdf->Cell(10, 4, iconv('UTF-8', 'windows-1252', 'contrato, motivo por el cual se obliga al estricto cumplimiento de los mismos, no pudiendo alegar desconocimiento en ningún caso, bajo ninguna '));
                $pdf->Ln(3);
                $pdf->Cell(10, 4, iconv('UTF-8', 'windows-1252', 'circunstancia, haciéndose pasible –dado el caso- a las sanciones indicadas en los mismos, y renunciando a cualquier reclamo posterior.'));
                // $pdf->Ln(3);
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Ln(3);
                $pdf->Cell(200, 4, "QUINTA: DEL ESPACIO ARRENDADO");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 4, iconv('UTF-8', 'windows-1252', 'El contrato de participación es individual e intransferible, por lo tanto, ningún espacio podrá ser cedido, subarrendado o vendido por el EXPOSITOR sin autorización expresa de FEICOBOL a través de FEXCO. El incumplimiento a la presente cláusula dará lugar a resolución inmediata del presente contrato.'));
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Cell(200, 4, "SEXTA: MEJORAS");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 4, iconv('UTF-8', 'windows-1252', 'Toda construcción, instalación y mejoras que el Expositor introduzca al espacio arrendado deberá ser previamente aprobada expresamente por la Dirección de Gestión y Desarrollo de Proyectos del GAMC. Una vez concluido el evento ferial, todas las mejoras realizadas en la infraestructura quedan en beneficio del Recinto Ferial pudiendo esta última disponer libremente de todos los terrenos y edificaciones para los fines que considere, sin lugar a resarcimiento de costo alguno.'));
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Cell(200, 4, "SEPTIMA: SOLUCION DE CONTROVERSIAS");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 4, iconv('UTF-8', 'windows-1252', 'Las partes intervinientes acuerdan resolver, en forma definitiva, todas las controversias o diferencias relacionadas con la interpretación, aplicación, cumplimiento o ejecución del presente contrato; mediante Conciliación ante los Conciliadores del Tribunal Departamental de Justicia de Cochabamba, en caso de no arribar a una conciliación, FEICOBOL podrá iniciar la acción judicial ejecutiva, a los efectos de demandar el cumplimiento de las obligaciones de hacer o pagar que se encuentren en mora.'));
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Cell(200, 4, "OCTAVA: ACEPTACION");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 4, iconv('UTF-8', 'windows-1252', 'El EXPOSITOR manifiesta su entera conformidad con todas y cada una de las cláusulas detalladas en el presente contrato, y al mismo tiempo acusa recibo, conocimiento y sometimiento a todas las normas y regulaciones establecidas en el ADEMDUM # 1, REGLAMENTO GENERAL DE PARTICIPACION DE LA FEXCO 2025 los cuales declara estar recibiendo y conocer, por lo que en señal de conformidad firman al pie del presente documento.'));
                // $pdf->Ln(0.5);
                // $pdf->SetFont('Arial', 'B', 9);
                // $pdf->Cell(200, 4, "Cochabamba, " . $fecha_imprimir, '', 0, 'R');
                // $pdf->Ln(8);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(40, 5, iconv('UTF-8', 'windows-1252', "FORMA DE PAGO"), 'LT');
                $pdf->Cell(40, 5, iconv('UTF-8', 'windows-1252', "Depósito en Cuenta Corriente"), 'TR', 0, 'C');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(60, 5, iconv('UTF-8', 'windows-1252', "BANCO GANADERO"), 'LT', 0, 'C');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(60, 5, iconv('UTF-8', 'windows-1252', ""), 'LTR', 0, 'C');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(55, 5, iconv('UTF-8', 'windows-1252', "Nombre de la Cuenta:"), 'L');
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Cell(25, 5, iconv('UTF-8', 'windows-1252', ""), '', 0, 'R');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(20, 5, iconv('UTF-8', 'windows-1252', "Cuenta Nº"), 'L');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 5, iconv('UTF-8', 'windows-1252', "1311565696 (Bolivianos)"), 'R');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(20, 5, iconv('UTF-8', 'windows-1252', ""), 'L');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 5, iconv('UTF-8', 'windows-1252', ""), 'R');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(55, 5, iconv('UTF-8', 'windows-1252', "FUNDACIÓN PARA LA FERIA"), 'L');
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Cell(25, 5, iconv('UTF-8', 'windows-1252', ""), '', 0, 'R');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(20, 5, iconv('UTF-8', 'windows-1252', ""), 'L');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 5, iconv('UTF-8', 'windows-1252', ""), 'R');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(20, 5, iconv('UTF-8', 'windows-1252', ""), 'L');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 5, iconv('UTF-8', 'windows-1252', ""), 'R');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(80, 5, iconv('UTF-8', 'windows-1252', "INTERNACIONAL COCHABAMBA - FEICOBOL"), 'LB');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(60, 5, "", 'LB', 0, 'C');
                // $pdf->Ln(40);
                if ($inicial_cantidad > 0) {
                    if ($c->descuento > 0) {
                        $pdf->Image(dirname(base_path()) . '/intranet/public/qr_pago.png', 170, 282, 20, 20);
                    } else {
                        $pdf->Image(dirname(base_path()) . '/intranet/public/qr_pago.png', 170, 274, 20, 20);
                    }
                } else {
                    if ($c->descuento > 0) {
                        $pdf->Image(dirname(base_path()) . '/intranet/public/qr_pago.png', 170, 276, 20, 20);
                    } else {
                        $pdf->Image(dirname(base_path()) . '/intranet/public/qr_pago.png', 170, 268, 20, 20);
                    }
                }


                $pdf->Cell(60, 5, "", 'LRB', 0, 'C');
                $pdf->Ln(7);
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(200, 4, "Cochabamba, " . $fecha_imprimir, '', 0, 'R');
                $pdf->Ln(8);
                $pdf->Ln(11);
                if ($id != 0) {
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "FIRMA REPRESENTANTE FEICOBOL"), 'T', 0, 'C');
                    $pdf->SetFont('Arial', '', 8);
                    $pdf->Cell(40, 7, iconv('UTF-8', 'windows-1252', ""));
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "FIRMA EXPOSITOR"), 'T', 0, 'C');
                    $pdf->SetFont('Arial', '', 8);
                    $pdf->Ln(3);
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "Lic. Eunice Acha"), 0, 0, 'C');
                    $pdf->SetFont('Arial', '', 8);
                    $pdf->Cell(40, 7, iconv('UTF-8', 'windows-1252', ""));
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', $c->nombre_gerente), 0, 0, 'C');
                    $pdf->Ln(4);
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "CI: 2865473 CB."), 0, 0, 'C');
                    $pdf->SetFont('Arial', '', 8);
                    $pdf->Cell(40, 7, iconv('UTF-8', 'windows-1252', ""));
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252',  "CI: " . $c->ci_gerente . " " . $c->exp_ci_gerente), 0, 0, 'C');
                }
                //------------PAGINA 2
                $pdf->SetAutoPageBreak(false);
                $pdf->SetMargins(10, 30);
                $pdf->AddPage('P', 'legal');
                $pdf->Image("images/fexco.png", 12, 25, 20);
                $pdf->Image("images/logo.jpg", 192, 22, 20);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(0, 10, 'ADEMDUM #1', 0, 0, 'C');
                $pdf->Ln(5);
                $pdf->Cell(0, 10, iconv('UTF-8', 'windows-1252', "REGLAMENTO GENERAL DE CONTRATO DE PARTICIPACIÓN"), 0, 0, 'C');
                $pdf->Ln(8);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "1. 1. INSCRIPCIÓN"));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El EXPOSITOR deberá llenar el formulario de participación, ajustándose a las formas de pago especificadas en el mismo. En caso de no hacerse efectivo el pago en los plazos establecidos, el EXPOSITOR perderá todos los derechos sobre el espacio reservado, pasando dicho espacio a disposición de FEXCO. No se permitirá la ocupación del espacio sin haber efectuado el pago total por el mismo.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "2. FORMA DE PAGO");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El pago del espacio asignado se realizará mediante cheque a favor de FEICOBOL o depósito bancario a la cuenta de FEICOBOL indicada en el contrato de participación. El expositor deberá cancelar la totalidad del monto económico acordado correspondiente al espacio asignado hasta la fecha estipulada en el contrato. En caso de incumplimiento, FEICOBOL a través de FEXCO bloqueará las credenciales al día siguiente de la inauguración de la feria y no se permitirá el ingreso al recinto ferial hasta que el EXPOSITOR realice la cancelación total de su deuda.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "3. PARTICIPANTES");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'FEICOBOL a través de FEXCO se reserva el derecho de admisión de las empresas participantes, las cuales deberán estar acorde al evento a realizarse.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "4. RENUNCIA DEL EXPOSITOR");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Si por algún motivo, el EXPOSITOR no pudiese participar en el evento ferial o desiste del mismo, deberá notificar por escrito. FEICOBOL -a través de FEXCO- le aplicará una penalización del 20% sobre el total de la factura emitida. Si la notificación de no participación se realiza dentro de los 30 días previos a la inauguración del evento, el expositor no podrá solicitar la devolución del dinero abonado y FEICOBOL -a través de FEXCO- podrá exigir el pago total del espacio no ocupado por el EXPOSITOR para disponer del mismo de la manera que se considere conveniente.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "5. RESPONSABILIDAD");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "5.1 SEGUROS");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El EXPOSITOR será responsable de los riesgos relacionados con las mercancías exhibidas, así como de la responsabilidad civil por su personal contratado. El expositor deberá contratar de manera obligatoria los seguros pertinentes. FEICOBOL -a través de FEXCO- declinan toda responsabilidad sobre las pérdidas o daños de los productos exhibidos u otras propiedades del expositor.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "5.2 REGISTRO");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El EXPOSITOR tiene la obligación de registrar todos sus productos, artículos, mercaderías, máquinas y/o equipos de exposición, así como muebles y enseres para decoración de los espacios asignados.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "5.3 DAÑOS Y PERJUICIOS"));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Los daños o perjuicios causados a FEXCO, sean materiales, morales, económicos o contra la imagen pública de FEXCO, sus eventos, miembros del Directorio Interinstitucional y/o personal administrativo, serán de exclusiva responsabilidad del expositor.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "5.4 USO DE PATENTES, MARCAS Y PRODUCTOS");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El expositor tendrá absoluta responsabilidad por problemas legales derivados de patentes y/o el uso no autorizado de marcas y productos exhibidos.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "5.5 CONTRATACIÓN DE SERVICIOS EXTERNOS"));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El EXPOSITOR deberá contar con la autorización de FEICOBOL -a través de FEXCO- para la contratación por cuenta propia de empresas que suministren servicios externos (seguridad, limpieza, catering, agua en botellones y/u otros insumos en general).'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "5.6 USO DE LOS ESPACIOS ALQUILADOS.");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El EXPOSITOR entregará el espacio asignado y equipamiento provisto en las mismas condiciones en las que fueron otorgados. Todos los deterioros causados por el EXPOSITOR o sus dependientes, correrán por cuenta del expositor. FEICOBOL y FEXCO no serán responsables de las pérdidas, daños o gastos ocasionados por casos fortuitos, accidentes o cualquier cosa fuera de su control.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "5.7 CAMBIO DE FECHAS Y LUGAR DE EXPOSICIÓN.");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'FEICOBOL -a través de FEXCO- se reserva el derecho de modificar el lugar y la fecha del evento por causas justificadas, en cuyo caso el acuerdo de participación seguirá siendo válido quedando FEICOBOL y FEXCO eximidas de toda responsabilidad civil o penal.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "5.8 REUBICACIÓN DE ESPACIOS ASIGNADOS."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'FEICOBOL -a través de FEXCO- se reserva el derecho de reubicar al EXPOSITOR por causa justificada, previa notificación al mismo, así como realizar cualquier otro cambio estructural que considere necesario, sin derecho a indemnización o compensación.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "5.9 SUSPENSIÓN DEL EVENTO."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Si por cualquier causa, no pudiera celebrarse el evento, los expositores sólo tendrán derecho a la devolución del dinero abonado hasta la fecha sin ninguna otra indemnización o recargo alguno.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "5.10 SUSPENCIÓN DEL PARTICIPANTE."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'FEICOBOL -a través de FEXCO- al tener derecho de admisión a las empresas participantes podrá revocar el presente contrato en caso de verificar que los datos otorgados por la empresa para el evento son inexactos o si las condiciones de admisión y participación no son cumplidas. FEICOBOL -a través de FEXCO- podrá establecer nuevas disposiciones impuestas por las circunstancias para la buena marcha de la exposición, dándolas a conocer a los expositores.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "6. LIQUIDACIÓN DE IMPORTES ADICIONALES."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'La liquidación total de los importes adicionales producidos por el expositor durante el evento, deberán ser canceladas en el Área de Administración y Finanzas de FEICOBOL y    FEXCO y, será condición indispensable para retirar el mobiliario, objetos e instalaciones utilizadas durante el evento en el Recinto Ferial.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "7. GENERALIDADES.");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El EXPOSITOR acepta el paso de canalizaciones de agua y líneas eléctricas por sus espacios, necesarias para el acondicionamiento del recinto. Asimismo, permitirá el libre acceso a su stand, cuando sea necesario para la realización de los trabajos indispensables o urgentes. FEICOBOL -a través de FEXCO- se compromete a realizar los trabajos sin que se perjudique al expositor en su exposición o normal desempeño y funcionamiento de su espacio.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "8. MANEJO DE MERCANCÍA."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'La mercancía a exhibir será la detallada en el contrato de participación por el EXPOSITOR y haya sido admitida por FEICOBOL -a través de FEXCO- mediante la entrega de un inventario por escrito de la mercadería. La entrada de mercancía durante el evento, así como la limpieza del área de exposición, deberán realizarse dentro del horario asignado para tal efecto por la Administración.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'No se permitirá la entrada ni salida de mercancías durante el horario que la exposición esté abierta al público visitante.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "9. DE LOS PRODUCTOS EXPUESTOS."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Se prohíbe terminantemente depositar o exponer en los stands e instalaciones del recinto, material o sustancias peligrosas, inflamables, explosivas o insalubres, que desprendan malos olores o que puedan molestar a los demás expositores y público visitante. La custodia de las instalaciones y productos correrá y estará a cargo del EXPOSITOR. La exhibición de maquinaria en funcionamiento deberá contar con previa autorización de FEICOBOL -a través de FEXCO-, misma que sólo será considerada cuando no constituya un peligro o genere molestia al resto de expositores y al público en general.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "10. VIGILANCIA Y SEGURIDAD."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'FEICOBOL -a través de FEXCO- proveerá dentro del recinto, un servicio de orden y vigilancia general. Sin embargo, declina toda responsabilidad que pudieran ocasionarse por accidentes meteorológicos, humos, robos, hurtos o cualquier hecho de la naturaleza que sea. Los expositores serán responsables de los daños que, por acción propia, la de su personal o sus instalaciones, puedan causar a terceros. Los expositores están obligados a contar con los seguros respectivos y aquellos que vean conveniente.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "11. APERTURA Y CIERRE DE STAND."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Los expositores deberán mantener abiertos al público sus stands durante todo el periodo de la feria, dentro de los horarios fijados por FEICOBOL -a través de FEXCO-.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "12. PROHIBICIÓN."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Se hace constar expresamente las siguientes prohibiciones:'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		Queda terminantemente prohibido fumar dentro los pabellones por motivos de seguridad.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		Queda prohibido el consumo de bebidas alcohólicas dentro del Stand por parte del expositor o colaboradores. '));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		Picar pisos, agujerar, serruchar, deteriorar paredes, columnas, techos, paneles y otras estructuras existentes.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		Venta de bebidas alcohólicas a menores de edad.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		Exhibición de precio en los productos de exposición.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		Retirar material antes de la clausura del evento.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		La venta de muestra de algunos productos como ser: cigarrillos, alimentos, pastas, revistas, refrigerios será posible solo con la autorización de FEICOBOL -a través de FEXCO-.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-	    Compartir espacios y realizar subarriendos a terceros.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'En caso de realizar alguna de las actividades detalladas anteriormente, FEICOBOL -a través de FEXCO- procederá al cierre temporal o parcial del stand que ocupa, sanción que será cumplida sin reclamo alguno.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "13. FACTURACIÓN."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'La facturación por toda venta y/o adquisición de bienes o servicios que se realice dentro del Recinto Ferial, es de carácter OBLIGATORIO. Para ello, las empresas deberán solicitar a la Administración Tributaria la dosificación correspondiente. Se recomienda tomar en cuenta esta disposición para evitar problemas de orden impositivo.'));
                $pdf->Ln(1);
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "14. VOLUMEN DE EQUIPOS DE SONIDO DENTRO DE LOS PABELLONES Y AREAS EXTERNAS."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Los expositores que desean ambientar su stand con música no deberán exceder los niveles de volumen de 50 decibeles como máximo en pabellones. El volumen en área externa será hasta 70 decibeles como máximo. Está prohibido utilizar altoparlantes, megáfonos y dispositivos de amplificación que causen molestias al resto de los expositores y visitantes.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "15. EXCLUSIVIDAD."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'FEICOBOL -a través de FEXCO- se reserva el derecho de clausurar el stand del expositor, ya sea dentro un pabellón o en área externa, cuando se incite al consumo excesivo de bebidas alcohólicas u otra que afecten el normal desarrollo del evento ferial. De producirse ésta, el EXPOSITOR renuncia expresamente a formular reclamo alguno.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "16. ACEPTACIÓN EXPRESA."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Yo, como representante de la empresa a la cual pertenezco y con la atribución de poder realizar el presente contrato declaro haber leído el texto del mismo en su totalidad y estoy de acuerdo con todas y cada una de las cláusulas del presente contrato para tal efecto firmó al reverso del presente como muestra de conformidad.'));
                $pdf->Ln(20);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "FIRMA REPRESENTANTE FEICOBOL"), 'T', 0, 'C');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 7, iconv('UTF-8', 'windows-1252', ""));
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "FIRMA EXPOSITOR"), 'T', 0, 'C');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Ln(3);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "Lic. Eunice Acha"), 0, 0, 'C');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 7, iconv('UTF-8', 'windows-1252', ""));
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', $c->nombre_gerente), 0, 0, 'C');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "CI: 2865473 CB."), 0, 0, 'C');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 7, iconv('UTF-8', 'windows-1252', ""));
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "CI: " . $c->ci_gerente . " " . $c->exp_ci_gerente), 0, 0, 'C');
                // Nombre de archivo limpio para la pestaña del navegador
                $nombreArchivo = "Contrato-" . $c->prefijo . $this->agr_ceros($c->codigo_contrato) . ".pdf";
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                $pdf->Output($nombreArchivo, "I");
            } else {
                $pdf = new FPDF();
                $pdf->AliasNbPages();
                $pdf->AddPage('P', 'legal');
                $pdf->Image("images/fexco.png", 10, 8, 20);
                $pdf->Image("images/logo.jpg", 194, 5, 20);
                $pdf->SetFont('Arial', 'B', 14);
                $pdf->Cell(0, 8, 'CONTRATO DE PARTICIPACION', 0, 0, 'C');
                $pdf->Ln();
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(170, 2);
                $pdf->Cell(30, 4.5, "No. " . $c->prefijo . $this->agr_ceros($c->codigo_contrato), 1);
                $pdf->Ln();
                $pdf->Cell(200, 4.5, 'Evento: ' . iconv('UTF-8', 'windows-1252', $c->nombre_feria), 1);
                $pdf->Ln();
                $pdf->Cell(60, 4.5, "Fecha de Inicio: " . $c->fecha_inicio, 'LTB');
                $pdf->Cell(140, 4.5, iconv('UTF-8', 'windows-1252', "Fecha de Finalización: ") . $c->fecha_fin, 'RTB');
                $pdf->Ln();
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', "Conste por el presente Contrato de Participación que suscriben -por un lado- FEICOBOL y -por otro- el EXPOSITOR, que con sólo Reconocimiento de firmas y rúbricas ante autoridad competente surtirá todos los efectos legales de un documento privado."));
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Ln(1);
                $pdf->Cell(200, 4, "PRIMERA: DE LAS PARTES");
                $pdf->Ln(4);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '1.1.	La “FUNDACIÓN PARA LA FERIA INTERNACIONAL DE COCHABAMBA- BOLIVIA” (FEICOBOL), con Número de Identificación Tributaria No. 1021689022 y domicilio en la Avenida Pando No. 1185 Zona Norte de esta ciudad;  representada por la Lic. EDITH EUNICE ACHÁ FERREL mayor de edad, hábil por derecho, con Cédula de Identidad No. 2865473 expedida en Cbba., en calidad Gerente General; quien en adelante y para fines del presente documento se denominará FEICOBOL.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(200, 3.5, iconv('UTF-8', 'windows-1252', "1.2. Nombre o Razón Social:"), 'LTR');
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(200, 5, iconv('UTF-8', 'windows-1252', $c->nombre_empresa), 'LBR');
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(165, 3.5, iconv('UTF-8', 'windows-1252', "Dirección:"), 'LTR');
                $pdf->Cell(35, 3.5, iconv('UTF-8', 'windows-1252', "Telefono:"), 'RTL');
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(165, 5, iconv('UTF-8', 'windows-1252', $c->direccion), 'LBR');
                $pdf->Cell(35, 5, iconv('UTF-8', 'windows-1252', $c->telefono), 'LBR');
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(20, 4, iconv('UTF-8', 'windows-1252', "E-mail:"), 'LT');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(80, 4, iconv('UTF-8', 'windows-1252', $c->email), 'TR');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(20, 4, iconv('UTF-8', 'windows-1252', "Pagina Web:"), 'LT');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(80, 4, $c->web, 'RT');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(20, 5, iconv('UTF-8', 'windows-1252', "Ciudad:"), 'LBT');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(130, 5, iconv('UTF-8', 'windows-1252', $c->ciudad), 'TBR');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(12, 5, iconv('UTF-8', 'windows-1252', "País:"), 'LBT');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(38, 5, $c->pais, 'TBR');
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(140, 4, iconv('UTF-8', 'windows-1252', "Nombre del Representante Legal:"), 'LTR');
                $pdf->Cell(30, 4, iconv('UTF-8', 'windows-1252', "CI:"), 'LTR');
                $pdf->Cell(30, 4, iconv('UTF-8', 'windows-1252', "Teléfono:"), 'LTR');
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(140, 4, iconv('UTF-8', 'windows-1252', $c->nombre_gerente), 'LBR');
                $pdf->Cell(30, 4, iconv('UTF-8', 'windows-1252', $c->ci_gerente . " " . $c->exp_ci_gerente), 'LBR');
                $pdf->Cell(30, 4, iconv('UTF-8', 'windows-1252', $c->fono_gerente), 'LBR');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(200, 4, iconv('UTF-8', 'windows-1252', "Productos a Exponer:"), 'LTR');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', $c->productos), 'LR');
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(165, 3.5, iconv('UTF-8', 'windows-1252', "Nro. de Escritura de Constitución o Documento de Personalidad Jurídica:"), 'LTR');
                $pdf->Cell(35, 3.5, iconv('UTF-8', 'windows-1252', "Fecha:"), 'RTL');
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(165, 4, iconv('UTF-8', 'windows-1252', $c->nr_escritura), 'LR');
                $pdf->Cell(35, 4, iconv('UTF-8', 'windows-1252', $c->fecha_nr_escritura), 'LR');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(165, 3.5, iconv('UTF-8', 'windows-1252', "Matrícula de Comercio (S/A):"), 'LTR');
                $pdf->Cell(35, 3.5, iconv('UTF-8', 'windows-1252', "NIT:"), 'RTL');
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(165, 4, iconv('UTF-8', 'windows-1252', $c->matricula), 'LBR');
                $pdf->Cell(35, 4, iconv('UTF-8', 'windows-1252', $c->nit), 'LBR');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(50, 3.5, iconv('UTF-8', 'windows-1252', "Nro. de Poder:"), 'LTR');
                $pdf->Cell(50, 3.5, iconv('UTF-8', 'windows-1252', "Nro. Notaría:"), 'RTL');
                $pdf->Cell(50, 3.5, iconv('UTF-8', 'windows-1252', "Fecha:"), 'RTL');
                $pdf->Cell(50, 3.5, iconv('UTF-8', 'windows-1252', "Distrito:"), 'RTL');
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(50, 4, iconv('UTF-8', 'windows-1252', $c->nr_poder), 'LBR');
                $pdf->Cell(50, 4, iconv('UTF-8', 'windows-1252', $c->nr_notaria), 'LBR');
                $pdf->Cell(50, 4, iconv('UTF-8', 'windows-1252', $c->fecha_nr_poder), 'LBR');
                $pdf->Cell(50, 4, iconv('UTF-8', 'windows-1252', $c->distrito), 'LBR');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', "En adelante y para fines del presente contrato se denomina el EXPOSITOR."));
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Ln(0.2);
                $pdf->Cell(200, 4, "SEGUNDA: OBJETO");
                $pdf->Ln(4);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'FEICOBOL tiene como objetivo organizar e impulsar el desarrollo de ferias internacionales, nacionales, monográficas, especializadas y otros eventos en formato presencial, virtual e hibrido, motivo por el cual suscribe este documento de participación por el tiempo que dure el presente evento, para que realice única y exclusivamente la exposición de los productos y mercaderías indicadas en la Cláusula Primera que antecede.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(200, 4, iconv('UTF-8', 'windows-1252', "Condiciones Especiales:"), 'LTR');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', $c->observaciones), 'LBR');
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(200, 4, "TERCERA: UBICACION, COSTO TOTAL Y FORMA DE PAGO");
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(100, 3.5, iconv('UTF-8', 'windows-1252', "UBICACION"), 'LTR', 0, 'C');
                $pdf->Cell(100, 3.5, iconv('UTF-8', 'windows-1252', "ESPECIFICACIONES"), 'LTR', 0, 'C');
                $pdf->Ln(3);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(30, 3.5, iconv('UTF-8', 'windows-1252', "Nombre Pabellón:"), 'L', 0, 'R');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(70, 4, iconv('UTF-8', 'windows-1252', $c->nombre_pabellon), 'R');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(30, 3.5, iconv('UTF-8', 'windows-1252', "Superficie:"), 'L', 0, 'R');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(20, 4, $c->metraje_total . " m2", '');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(30, 3.5, iconv('UTF-8', 'windows-1252', "Credenciales:"), '', 0, 'R');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(20, 4, $c->nro_credenciales, 'R');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(30, 3.5, iconv('UTF-8', 'windows-1252', "Stand:"), 'LB', 0, 'R');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(70, 3.5, $stands, 'B');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(30, 3.5, iconv('UTF-8', 'windows-1252', "Costo m2:"), 'LB', 0, 'R');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(20, 3.5, "Bs. " . $c->precio_unit, 'B');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(30, 3.5, iconv('UTF-8', 'windows-1252', "Potencia Contratada:"), 'B', 0, 'R');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(20, 3.5, $c->potencia . "W", 'RB');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', '', 7);
                if ($c->descuento > 0) {
                    $pdf->MultiCell(200, 3.5, iconv('UTF-8', 'windows-1252', 'La superficie detallada anteriormente tiene un costo total de Bs. ' . ($monto1) . ' (' . $monto_literal . ' ' . $cent . ' Bolivianos). Se ha aplicado un descuento ' . $nom_desc . ' del ' . $c->descuento . '%, resultando un total de Bs. ' . $c->precio_total_desc . ' (' . $monto_literal_desc . ' ' . $cent_desc . ' Bolivianos), que serán cancelados de la siguiente manera:'));
                } else {
                    $pdf->MultiCell(200, 3.5, iconv('UTF-8', 'windows-1252', 'La superficie detallada anteriormente tiene un costo total de Bs. ' . ($monto1) . ' (' . $monto_literal . ' ' . $cent . ' Bolivianos), que serán cancelados de la siguiente manera'));
                }

                $pdf->Ln(0.1);
                if ($inicial_cantidad > 0) {
                    $pdf->SetFont('Arial', 'B', 7);
                    $pdf->Cell(15, 4, iconv('UTF-8', 'windows-1252', $inicial_porcentaje), 'LT', 0, 'R');
                    $pdf->SetFont('Arial', '', 7);
                    $pdf->Cell(35, 4, iconv('UTF-8', 'windows-1252', "% equivalente a"), 'T');
                    $pdf->SetFont('Arial', 'B', 7);
                    $pdf->Cell(15, 4, iconv('UTF-8', 'windows-1252', $inicial_cantidad), 'T', 0, 'R');
                    $pdf->SetFont('Arial', '', 7);
                    $pdf->Cell(100, 4, iconv('UTF-8', 'windows-1252', "/100 Bolivianos a la firma del presente documento"), 'T');
                    $pdf->SetFont('Arial', 'B', 7);
                    $pdf->Cell(35, 4, iconv('UTF-8', 'windows-1252', substr($c->fecha_inicial, 8, 2) . "/" . substr($c->fecha_inicial, 5, 2) . "/" . substr($c->fecha_inicial, 0, 4)), 'TR');
                    $pdf->Ln(5);
                }
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Cell(15, 4, iconv('UTF-8', 'windows-1252', $resto_porcentaje), 'LT', 0, 'R');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(35, 4, iconv('UTF-8', 'windows-1252', "% equivalente a"), 'T');
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Cell(15, 4, iconv('UTF-8', 'windows-1252', $resto_cantidad), 'T', 0, 'R');
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(100, 4, iconv('UTF-8', 'windows-1252', "/100 Bolivianos, impostergablemente hasta el"), 'T');
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Cell(35, 4, iconv('UTF-8', 'windows-1252', substr($c->fecha_final, 8, 2) . "/" . substr($c->fecha_final, 5, 2) . "/" . substr($c->fecha_final, 0, 4)), 'TR');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'En caso de que el EXPOSITOR no cumpla con este pago;  FEICOBOL -a través de FEXCO-  podrá disponer del espacio, sin derecho a reclamo alguno por parte del EXPOSITOR, reconociendo el total comprometido en pago como suma liquida y exigible con plazo vencido, incurriendo en mora automática en caso de incumplimiento de pago en  el plazo establecido, habilitando la via judicial ejecutiva a los efectos de garantizar el cumplimiento de las obligaciones de pago asumidas, el cual es reconocido por ambas partes como resarcimiento de daños y perjuicios por el daño ocasionado ante el incumplimiento del contrato. Consolidando a su vez el anticipo cancelado en favor de FEICOBOL.'), 'LBR');
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Ln(1);
                $pdf->Cell(200, 4, "CUARTA: REGLAMENTOS");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Se aclara de manera expresa que el REGLAMENTO GENERAL DE CONTRATO DE PARTICIPACION y el REGLAMENTO GENERAL DE PARTICIPACION, forman parte indivisible del presente contrato y estarán disponibles para el EXPOSITOR en un link o código QR que se le entregará en el momento de suscribir el presente contrato, motivo por el cual se obliga al estricto cumplimiento de los mismos, no pudiendo alegar desconocimiento en ningún caso, bajo ninguna circunstancia, haciéndose pasible –dado el caso- a las sanciones indicadas en los mismos, y renunciando a cualquier reclamo posterior.'));
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Ln(1);
                $pdf->Cell(200, 4, "QUINTA: PROHIBICIONES");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El contrato de participación es individual e intransferible, por lo tanto, ningún espacio podrá ser cedido, subarrendado, subrogado o transferido por el EXPOSITOR sin autorización expresa de FEICOBOL a través de FEXCO. El incumplimiento a la presente cláusula dará lugar a resolución inmediata del presente contrato.'));
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Ln(1);
                $pdf->Cell(200, 4, "SEXTA: MEJORAS");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Toda construcción, instalación y mejoras que el Expositor introduzca al espacio arrendado deberá ser previamente aprobada expresamente por la Dirección de Gestión y Desarrollo de Proyectos del GAMC. Una vez concluido el evento ferial, todas las mejoras realizadas en la infraestructura quedan consolidadas en beneficio del Recinto Ferial pudiendo GAMC disponer libremente de todos los terrenos y edificaciones para los fines que considere, sin lugar a resarcimiento de costo alguno al inversionista expositor.'));
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Ln(1);
                $pdf->Cell(200, 4, "SEPTIMA: RETIRO DE PERTENENCIAS");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                if ($c->id_feria == 14) {
                    $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El EXPOSITOR deberá realizar el desmontaje y retiro de todas sus pertenencias en el plazo máximo de 7 días calendario de concluido el evento, caso contrario FEICOBOL se encontrará facultada para desmontar y retirar a un depósito sus pertenencias, realizando el cobro respectivo de este trabajo y de los días que se encuentre en el depósito según cantidad de metros cuadrados ocupados en el mismo o en su caso realizar el deshecho de las mismas, sin reclamo posterior por el expositor.'));
                } else {
                    $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El EXPOSITOR deberá realizar el desmontaje y retiro de todas sus pertenencias en el plazo máximo de 2 días calendario de concluido el evento, caso contrario FEICOBOL se encontrará facultada para desmontar y retirar a un depósito sus pertenencias, realizando el cobro respectivo de este trabajo y de los días que se encuentre en el depósito según cantidad de metros cuadrados ocupados en el mismo o en su caso realizar el deshecho de las mismas, sin reclamo posterior por el expositor.'));
                }

                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Ln(1);
                $pdf->Cell(200, 4, "OCTAVA: SOLUCION DE CONTROVERSIAS");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Las partes intervinientes acuerdan resolver, en forma definitiva, todas las controversias o diferencias relacionadas con la interpretación, aplicación, cumplimiento o ejecución del presente contrato; mediante Conciliación ante los Conciliadores del Tribunal Departamental de Justicia de Cochabamba, en caso de no arribar a una conciliación, FEICOBOL podrá iniciar la acción judicial ejecutiva, a los efectos de demandar el cumplimiento de las obligaciones de hacer o pagar que se encuentren en mora.'));
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Ln(1);
                $pdf->Cell(200, 4, utf8_decode("NOVENA: DECLARACION EXPRESA DE PERSONERÍA Y AUTORIZACIONES"));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El EXPOSITOR declara la veracidad de los documentos e información descrita de personería, que acreditan en forma completa, vigente y fidedigna la suscripción del presente contrato, además declara que cuenta con las Licencias, autorizaciones y permisos necesarios para su actividad, la venta de sus productos y otros de conformidad a la naturaleza de su objeto. deslindando de responsabilidad ulterior a FEICOBOL.'));
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Ln(1);
                $pdf->Cell(200, 4, utf8_decode("DÉCIMA: ACEPTACIÓN"));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El EXPOSITOR manifiesta su entera conformidad con todas y cada una de las cláusulas detalladas en el presente contrato, y al mismo tiempo acusa recibo, conocimiento y sometimiento a todas las normas y regulaciones establecidas en el REGLAMENTO GENERAL DE CONTRATO DE PARTICIPACION, REGLAMENTO GENERAL DE PARTICIPACION los cuales declara estar recibiendo y conocer, por lo que en señal de conformidad firman al pie del presente documento.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(200, 4, "Cochabamba, " . $fecha_imprimir, '', 0, 'C');
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(50, 5, iconv('UTF-8', 'windows-1252', "FORMA DE PAGO"), 'LT');
                $pdf->Cell(50, 5, iconv('UTF-8', 'windows-1252', "Depósito en Cuenta Corriente"), 'TR', 0, 'C');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(100, 5, iconv('UTF-8', 'windows-1252', "BANCO GANADERO"), 'LTR', 0, 'C');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(100, 5, iconv('UTF-8', 'windows-1252', "Nombre de la Cuenta:"), 'L');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(50, 5, iconv('UTF-8', 'windows-1252', "Cuenta Nº"), 'L');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(50, 5, iconv('UTF-8', 'windows-1252', "1311565696 (Bolivianos)"), 'R');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(100, 5, iconv('UTF-8', 'windows-1252', "FUNDACIÓN PARA LA FERIA INTERNACIONAL COCHABAMBA - FEICOBOL"), 'LRB');
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Cell(100, 5, iconv('UTF-8', 'windows-1252', ""), 'RB');
                $pdf->Ln(16);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "FIRMA REPRESENTANTE FEICOBOL"), 'T', 0, 'C');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 7, iconv('UTF-8', 'windows-1252', ""));
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "FIRMA EXPOSITOR"), 'T', 0, 'C');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Ln(3);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "Lic. Eunice Acha"), 0, 0, 'C');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 7, iconv('UTF-8', 'windows-1252', ""));
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', $c->nombre_gerente), 0, 0, 'C');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "CI: 2865473 CB."), 0, 0, 'C');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 7, iconv('UTF-8', 'windows-1252', ""));
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252',  "CI: " . $c->ci_gerente . " " . $c->exp_ci_gerente), 0, 0, 'C');
                //------------PAGINA 2
                $pdf->SetAutoPageBreak(false);
                $pdf->SetMargins(10, 20);
                $pdf->AddPage('P', 'legal');
                $pdf->Image("images/fexco.png", 12, 15, 20);
                $pdf->Image("images/logo.jpg", 192, 12, 20);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(0, 10, iconv('UTF-8', 'windows-1252', "REGLAMENTO GENERAL DE CONTRATO DE PARTICIPACIÓN"), 0, 0, 'C');
                $pdf->Ln(8);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "1. 1. INSCRIPCIÓN"));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Deberá llenarse el formulario de participación, ajustándose a las formas de pago que aparecen en el mismo. En caso de no hacerse efectivo el pago en los plazos establecidos, el expositor perderá todos los derechos sobre el espacio reservado, pasando dicho espacio a disposición de FEICOBOL. No se autorizará la ocupación del espacio que no haya sido cancelado.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "2. FORMA DE PAGO");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El pago del stand se realizará mediante cheque a favor de FEICOBOL, depósito bancario a la cuenta de FEICOBOL. En cualquier caso, el expositor debe cancelar la totalidad del monto económico acordado correspondiente al stand asignado hasta la fecha estipulada en el contrato.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "3. PARTICIPANTES");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'FEICOBOL -a través de FEXCO se reserva el derecho de admisión de las empresas participantes, las cuales deberán estar acorde al evento a realizarse.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "4. RENUNCIA DEL EXPOSITOR");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Si el participante por algún motivo no pudiese participar en el evento deberá comunicar en forma escrita tal hecho a FEICOBOL -a través de FEXCO. En tal caso el expositor no podrá pedir la devolución del dinero abonado a la fecha del comunicado. Si la comunicación de la no participación se efectúa dentro de los 30 días anteriores a la inauguración del evento, FEICOBOL tiene el derecho de exigir el pago total del espacio no ocupado por el expositor. Dicha situación de igual manera facilita a FEICOBOL -a través de FEXCO a disponer del espacio de la manera que vea conveniente.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "5. RESPONSABILIDAD");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "5.1 SEGUROS");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El expositor será responsable de los riesgos relacionados con las mercancías que exhiba, de los riesgos y responsabilidad civil del personal o empleados que haya contratado. Por tanto, el expositor deberá contratar LOS SEGUROS PERTINENTES. FEICOBOL -a través de FEXCO- declina toda responsabilidad sobre las pérdidas o daños de los productos exhibidos u otras propiedades del expositor.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "5.2 REGISTRO");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El expositor tiene la obligación de registrar todos sus productos, artículos, mercaderías, máquinas y/o equipos de exposición, así como muebles y enseres para decoración de los Stands.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "5.3 DAÑOS Y PERJUICIOS"));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Los daños o perjuicios causados a FEXCO, sean materiales, morales, económicos o contra la imagen pública de Feicobol, de los eventos feriales, miembros del Directorio, Directiva y/o personal administrativo, serán de exclusiva responsabilidad del expositor.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "5.4 USO DE PATENTES, MARCAS Y PRODUCTOS");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El expositor tendrá absoluta responsabilidad por problemas legales derivados de patentes y/o el uso no autorizado de marcas y productos exhibidos.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "5.5 CONTRATACIÓN DE SERVICIOS EXTERNOS"));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'El expositor deberá contar con la autorización de FEICOBOL -a través de FEXCO- para la contratación por cuenta propia de empresas que suministren servicios externos (seguridad, limpieza, catering, agua en botellones y otros insumos en general).'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "5.6 USO DE LOS ESPACIOS ALQUILADOS.");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Los expositores entregarán los stands y equipamiento provisto en las condiciones en que los recibieron. Todos los deterioros causados por el expositor o sus dependientes, correrán por cuenta del expositor. FEICOBOL y FEXCO no será responsable de las pérdidas, daños o gastos ocasionados por casos fortuitos, huelgas, accidentes, inclemencias del tiempo o cualquier cosa fuera de su control.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "5.7 CAMBIO DE FECHAS Y LUGAR DE EXPOSICIÓN.");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'FEICOBOL -A TRAVÉS DE FEXCO- se reserva el derecho de cambiar si fuera necesario por causa justificada el lugar y la fecha del evento, en cuyo caso el acuerdo de participación seguirá siendo válido quedando FEICOBOL y FEXCO eximida de toda responsabilidad civil o penal.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "5.8 REUBICACIÓN DE ESPACIOS ASIGNADOS."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'FEICOBOL -A TRAVÉS DE FEXCO- por causa justificada se reserva el derecho de reubicar al expositor previa notificación al expositor, así como realizar cualquier otro cambio estructural necesario, sin derecho a indemnización o compensación por parte del expositor.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "5.9 SUSPENSIÓN DEL EVENTO."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Si por cualquier causa, no pudiera celebrarse el evento, los expositores sólo tendrán derecho a la devolución del dinero abonado hasta la fecha sin ninguna otra indemnización o recargo alguno.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "5.10 SUSPENCIÓN DEL PARTICIPANTE."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'FEICOBOL -A TRAVÉS DE FEXCO- al tener derecho de admisión a las empresas participantes podrá revocar el presente contrato en caso de verificar que los datos otorgados por la empresa para el evento son inexactos o si las condiciones de admisión y participación no son cumplidas. FEICOBOL -A TRAVÉS DE FEXCO- podrá establecer nuevas disposiciones impuestas por las circunstancias para la buena marcha de la exposición, dándolas a conocer a los expositores.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "6. LIQUIDACIÓN DE IMPORTES ADICIONALES."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'La liquidación total de los importes adicionales producidos por el expositor durante el evento, deberán ser canceladas en el Departamento de Administración y Finanzas y será condición indispensable para retirar el material (mobiliario, objetos e instalaciones) del Recinto Ferial.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, "7. GENERALIDADES.");
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Los expositores aceptan el paso a través de sus espacios, de las canalizaciones de agua y tendido de líneas eléctricas necesarias para el arreglo y acondicionamiento general del recinto. Los expositores permitirán el libre acceso a su stand, cuando sea necesario para la realización de los trabajos indispensables o urgentes. FEICOBOL -A TRAVÉS DE FEXCO- se compromete a realizar los trabajos sin que se perjudique al expositor en su exposición o normal desempeño y funcionamiento de su espacio.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "8. MANEJO DE MERCANCÍA."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'La mercancía a exhibir será la que se haya detallado en el contrato de participación por el expositor y haya sido admitida por FEICOBOL -A TRAVÉS DE FEXCO- a través de la entrega de un inventario por escrito de la mercadería. La entrada de mercancía durante el evento deberá realizarse dentro del horario asignado para tal efecto por la Administración, así como también la limpieza del área de exposición.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'No se permitirá la entrada ni salida de mercancías durante el horario que la exposición esté abierta al público visitante.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "9. DE LOS PRODUCTOS EXPUESTOS."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Se prohíbe terminantemente depositar o exponer en los stands e instalaciones del recinto, material o sustancias peligrosas, inflamables, explosivas o insalubres, que desprendan malos olores y en general, aquellas que puedan molestar a los demás expositores y público visitante. La custodia de las instalaciones y productos correrá y estará a cargo del expositor. La exhibición de maquinaria en funcionamiento deberá contar con la previa autorización de FEICOBOL -A TRAVÉS DE FEXCO-, autorización que sólo será considerada cuando no constituya un peligro o genere molestia al resto de expositores y al público en general.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "10. VIGILANCIA Y SEGURIDAD."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'La Feria proveerá dentro del recinto, un servicio de orden y vigilancia general, pero declina toda responsabilidad que, por accidentes meteorológicos, humos, robos, hurtos o cualquier hecho de la naturaleza que sea, pudieran ocasionarse a las instalaciones y bienes de cuantos particulares, entidades y organismos participen en aquella. Los expositores serán responsables de los daños que, por acción propia, la de su personal o sus instalaciones puedan causar a terceros. Los expositores están obligados a contar con los seguros respectivos y aquellos que vean por convenientemente.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "11. APERTURA Y CIERRE DE STAND."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Los expositores deberán mantener abiertos al público sus stands durante todo el periodo de la feria, dentro de los horarios fijados por FEICOBOL -A TRAVÉS DE FEXCO-.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "12. PROHIBICIÓN."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Se hace constar expresamente las siguientes prohibiciones:'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		Queda terminantemente prohibido fumar dentro los pabellones por motivos de seguridad.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		Queda prohibido el consumo de bebidas alcohólicas dentro del Stand por parte del expositor o colaboradores. '));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		Picar pisos, agujerar, serruchar, deteriorar paredes, columnas, techos, paneles y otras estructuras existentes.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		Queda terminantemente prohibida la venta de bebidas alcohólicas a menores de edad.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		Exhibición de precio en los productos de exposición.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		Retirar material antes de la clausura del evento.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-		La venta de muestra de algunos productos como ser: cigarrillos, alimentos, pastas, revistas, refrigerios será posible solo con la autorización de FEICOBOL -a través de FEXCO-.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', '-	    Compartir espacios y realizar subarriendos a terceros.'));
                $pdf->Ln(0.1);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'En caso de realizar alguna de las actividades detalladas anteriormente, FEICOBOL -a través de FEXCO- procederá al cierre temporal o parcial del stand que ocupa, sanción que será cumplida sin reclamo alguno.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "13. FACTURACIÓN."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'La facturación por toda venta y/o adquisición de bienes o servicios que se realice dentro del Recinto Ferial, es de carácter obligatorio. Para ello, las empresas deberán solicitar a la administración tributaria la dosificación correspondiente. Se recomienda tomar en cuenta esta disposición para evitar problemas de orden impositivo.'));
                $pdf->Ln(1);
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "14. VOLUMEN DE EQUIPOS DE SONIDO DENTRO DE LOS PABELLONES Y AREAS EXTERNAS."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Los expositores que desean ambientar su stand con música no deberán exceder los niveles de volumen de 50 decibeles como máximo en pabellones. El volumen en área externa será hasta 70 decibeles como máximo. Está prohibido utilizar altoparlantes, megáfonos y dispositivos de amplificación que causen molestias al resto de los expositores.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "15. EXCLUSIVIDAD."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'FEICOBOL -A TRAVÉS DE FEXCO- se reserva el derecho de clausurar el stand del expositor sea dentro de cualquier pabellón o área externa, cuando se incite al consumo excesivo de bebidas alcohólicas u otra que afecten el normal desarrollo de la actividad Ferial. De producirse ésta, el expositor renuncia expresamente a formular reclamo alguno.'));
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->Cell(200, 3, iconv('UTF-8', 'windows-1252', "16. ACEPTACIÓN EXPRESA."));
                $pdf->Ln(3);
                $pdf->SetFont('Arial', '', 6);
                $pdf->MultiCell(200, 3, iconv('UTF-8', 'windows-1252', 'Yo, como representante de la empresa a la cual pertenezco y con la atribución de poder realizar el presente contrato declaro haber leído el texto del mismo en su totalidad y estoy de acuerdo con todas y cada una de las cláusulas del presente contrato para tal efecto firmo al reverso del presente como muestra de conformidad.'));
                $pdf->Ln(20);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "FIRMA REPRESENTANTE FEICOBOL"), 'T', 0, 'C');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 7, iconv('UTF-8', 'windows-1252', ""));
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "FIRMA EXPOSITOR"), 'T', 0, 'C');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Ln(3);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "Lic. Eunice Acha"), 0, 0, 'C');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 7, iconv('UTF-8', 'windows-1252', ""));
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', $c->nombre_gerente), 0, 0, 'C');
                $pdf->Ln(4);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "CI: 2865473 CB."), 0, 0, 'C');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 7, iconv('UTF-8', 'windows-1252', ""));
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(75, 7, iconv('UTF-8', 'windows-1252', "CI: " . $c->ci_gerente . " " . $c->exp_ci_gerente), 0, 0, 'C');
                // Nombre de archivo limpio para la pestaña del navegador
                $nombreArchivo = "Contrato-" . $c->prefijo . $this->agr_ceros($c->codigo_contrato) . ".pdf";
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                $pdf->Output($nombreArchivo, "I");
            }
        } catch (\Exception $e) {
            Log::error('Error imprimiendo contrato: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el PDF del contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Punto de entrada para imprimir Adendum #2.
     * Decide entre plantilla Word o FPDF según id_modeloademda.
     */
    public function adendum($id_contrato)
    {
        try {
            // 1. Obtener datos básicos del contrato y evento
            $evento = DB::table('contrato as c')
                ->join('eventos as ev', 'ev.id_feria', '=', 'c.id_feria')
                ->select('ev.id_modeloademda')
                ->where('c.id_contrato', '=', $id_contrato)
                ->first();

            // 2. DECISIÓN CLAVE: ¿Existe modelo Word para Adendum?
            if ($evento && $evento->id_modeloademda != null && $evento->id_modeloademda > 0) {
                // ✅ USAR PLANTILLA WORD
                return $this->imprimirAdendumWord($id_contrato);
            } else {
                // ✅ USAR FPDF (COMPORTAMIENTO ACTUAL)
                return $this->adendumFPDF($id_contrato);
            }
        } catch (\Exception $e) {
            Log::error('Error en punto de entrada de adendum: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el adendum: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Imprimir Adendum #2 con FPDF (FUNCIÓN ACTUAL - SIN CAMBIOS).
     * Replicando el formato legacy.
     */
    private function adendumFPDF($id_contrato)
    {
        try {
            $c = DB::table('contrato as c')
                ->join('empresas as e', 'c.id_empresa', '=', 'e.id_empresa')
                ->join('eventos as ev', 'ev.id_feria', '=', 'c.id_feria')
                ->select('ev.codigo_contrato as prefijo', 'c.codigo_contrato', 'ev.nombre_feria', 'e.nombre_empresa', 'e.usuario', 'e.pass', 'e.nombre_gerente', 'e.ci_gerente', 'e.exp_ci_gerente', 'e.nit')
                ->where('c.id_contrato', '=', $id_contrato)
                ->first();
            
            $pdf = new FPDF();
            $pdf->SetMargins(15, 15);
            $pdf->AliasNbPages();
            $pdf->AddPage("P", "legal");
            $pdf->Image(public_path('images/fexco.png'), 5, 25, 30);
            $pdf->Image(public_path('images/logo.jpg'), 180, 22, 30);
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Ln(10);
            $pdf->Cell(0, 10, 'ADEMDUM #2', 0, 0, 'C');
            $pdf->Ln(5);
            $pdf->Cell(0, 10, 'ACCESO A ZONA DE EXPOSITORES', 0, 0, 'C');
            $pdf->Ln(5);
            $pdf->Cell(0, 10, 'ON-LINE DE FEXCO', 0, 0, 'C');
            $pdf->Ln(38);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(184, 5, "1. INFORMACION ASIGNADA");
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 8);
            $pdf->MultiCell(184, 4, iconv('UTF-8', 'windows-1252', 'FEXCO proporcionará, al momento de la firma de contrato, un usuario y password únicos para cada EXPOSITOR para el acceso a la zona de expositores de FEXCO vía internet:'));
            $pdf->Ln();
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(45, 5, "FERIA:", 1);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(139, 5, iconv('UTF-8', 'windows-1252', $c->nombre_feria), 1);
            $pdf->Ln();
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(45, 5, "NOMBRE DE LA EMPRESA:", 1);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(139, 5, iconv('UTF-8', 'windows-1252', $c->nombre_empresa), 1);
            $pdf->Ln();
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(45, 5, "USUARIO:", 1);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(139, 5, $c->nit, 1);
            $pdf->Ln();
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(45, 5, iconv('UTF-8', 'windows-1252', "CONTRASEÑA:"), 1);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(139, 5, $c->pass, 1);
            $pdf->Ln();
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(45, 5, iconv('UTF-8', 'windows-1252', "PÁGINA DE ACCESO:"), 1);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(139, 5, "https://acreditacion.fexco.com.bo", 1);
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(184, 5, "2. SEGURIDAD DE LOS DATOS ENTREGADOS");
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 8);
            $pdf->MultiCell(184, 4, iconv('UTF-8', 'windows-1252', 'La cuenta de usuario y password del EXPOSITOR es ÚNICA, SECRETA E INTRANSFERIBLE, siendo el único responsable del manejo y uso de la información existente en la ZONA DE EXPOSITORES ONLINE de FEXCO.'));
            $pdf->Ln();
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(184, 5, "3. SOPORTE");
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 8);
            $pdf->MultiCell(184, 4, iconv('UTF-8', 'windows-1252', 'FEXCO se responsabiliza de dar el soporte necesario al EXPOSITOR para el correcto uso del sistema, sin que esto conlleve la obligación de acreditar al personal en nombre del EXPOSITOR.'));
            $pdf->Ln();
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(184, 5, "4. SEGURIDAD DE LA CUENTA");
            $pdf->Ln();
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(184, 4, "4.1 PERDIDA DE DATOS DE LA CUENTA");
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 8);
            $pdf->MultiCell(184, 4, iconv('UTF-8', 'windows-1252', 'En caso de pérdida u olvido del usuario o password, el EXPOSITOR podrá solicitar a FEXCO la restauración del usuario y password original y la entrega de una copia del ADEMDUM # 2 previa carta de solicitud dirigida a FEXCO.'));
            $pdf->Ln();
            $pdf->MultiCell(184, 4, iconv('UTF-8', 'windows-1252', 'La restauración del usuario y password y la entrega de la copia del ADEMDUM # 2 no tiene ningún costo y será entregada de manera inmediata.'));
            $pdf->Ln();
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(184, 5, "5. REGISTRO DE LA INFORMACION");
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 8);
            $pdf->MultiCell(184, 4, iconv('UTF-8', 'windows-1252', 'Toda consulta efectuada por el EXPOSITOR, quedará registrada en el sistema a efectos de determinar la identidad del usuario, cuando el caso lo amerite.'));
            $pdf->Ln();
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(184, 5, "6. PERSONAL ACREDITADO.");
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 8);
            $pdf->MultiCell(184, 4, iconv('UTF-8', 'windows-1252', 'El EXPOSITOR tiene la potestad de agregar, modificar y eliminar a su personal acreditado para la presente feria dentro de los plazos establecidos por el Manual del Expositor, siendo el responsable final de la acreditación de los mismos.'));
            $pdf->Ln();
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(184, 5, iconv('UTF-8', 'windows-1252', "7. CONFORMIDAD."));
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 8);
            $pdf->MultiCell(184, 4, iconv('UTF-8', 'windows-1252', 'El EXPOSITOR manifiesta su entera conformidad con todas y cada una de las cláusulas detalladas en el presente ADEMDUM #2 el cual declara conocer, por lo que en señal de conformidad firman al pie del presente documento.'));
            $pdf->Ln(40);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(75, 8, iconv('UTF-8', 'windows-1252', "FIRMA REPRESENTANTE FEICOBOL"), 'T', 0, 'C');
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(30, 8, iconv('UTF-8', 'windows-1252', ""));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(75, 8, iconv('UTF-8', 'windows-1252', "FIRMA EXPOSITOR"), 'T', 0, 'C');
            $pdf->SetFont('Arial', '', 8);
            $pdf->Ln(4);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(75, 8, iconv('UTF-8', 'windows-1252', "Lic. Eunice Acha"), 0, 0, 'C');
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(30, 8, iconv('UTF-8', 'windows-1252', ""));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(75, 8, iconv('UTF-8', 'windows-1252', $c->nombre_gerente), 0, 0, 'C');
            $pdf->Ln(4);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(75, 8, iconv('UTF-8', 'windows-1252', "CI: 2865473 CB."), 0, 0, 'C');
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(30, 8, iconv('UTF-8', 'windows-1252', ""));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(75, 8, iconv('UTF-8', 'windows-1252', "CI: " . $c->ci_gerente . " " . $c->exp_ci_gerente), 0, 0, 'C');
            
            // Nombre de archivo limpio para la pestaña del navegador
            $nombreArchivo = "Adendum2-" . $c->prefijo . $this->agr_ceros($c->codigo_contrato) . ".pdf";
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            $pdf->Output($nombreArchivo, "I");
        } catch (\Exception $e) {
            Log::error('Error imprimiendo adendum: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el PDF del adendum: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Imprimir Adendum desde plantilla Word (.docx).
     * Reemplaza marcadores {evento}, {nombre_empresa}, {nit}, {pass}, {nombre_gerente}, {ci_gerente}, {exp_ci_gerente}
     * y convierte a PDF.
     */
    private function imprimirAdendumWord($id_contrato)
    {
        try {
            // 1️⃣ VALIDACIONES INICIALES
            if (empty($id_contrato) || !is_numeric($id_contrato)) {
                throw new \Exception('ID de contrato inválido');
            }

            // 2️⃣ OBTENER DATOS DEL CONTRATO Y EVENTO
            $c = DB::table('contrato as c')
                ->join('empresas as e', 'c.id_empresa', '=', 'e.id_empresa')
                ->join('eventos as ev', 'ev.id_feria', '=', 'c.id_feria')
                ->select(
                    'c.id_feria', 'c.codigo_contrato',
                    'ev.codigo_contrato as prefijo', 'ev.nombre_feria',
                    'ev.id_modeloademda',
                    'e.nombre_empresa', 'e.nit', 'e.pass',
                    'e.nombre_gerente', 'e.ci_gerente', 'e.exp_ci_gerente'
                )
                ->where('c.id_contrato', '=', $id_contrato)
                ->first();

            if (!$c) {
                throw new \Exception('Contrato no encontrado');
            }

            // 3️⃣ VALIDAR QUE EXISTE MODELO WORD PARA ADENDUM
            if (empty($c->id_modeloademda)) {
                throw new \RuntimeException('El contrato no tiene un modelo de adendum asignado.');
            }

            $modelo = DB::table('modelo_contrato')
                ->where('id_modelo_contrato', '=', $c->id_modeloademda)
                ->where('tipo', '=', 2)  // Tipo 2 = Adenda
                ->first();

            if (!$modelo || empty($modelo->ruta_documento)) {
                throw new \RuntimeException(
                    'No se encontró el modelo de adendum asignado (ID ' . $c->id_modeloademda . ') o no tiene un archivo configurado.'
                );
            }

            // 4️⃣ CARGAR PLANTILLA WORD
            $rutaWord = $this->resolveModelDocumentPath($modelo->ruta_documento);
            if ($rutaWord === null) {
                throw new \Exception('Archivo de plantilla no encontrado: ' . $modelo->ruta_documento);
            }

            // Crear copia temporal limpia para este proceso de impresión
            $rutaWordTemp = tempnam(sys_get_temp_dir(), 'tpl_') . '.docx';
            copy($rutaWord, $rutaWordTemp);
            \App\Http\Controllers\ModeloContratoController::limpiarDocxFragmentado($rutaWordTemp);

            $templateProcessor = new TemplateProcessor($rutaWordTemp);
            
            // Configurar los caracteres de apertura y cierre para que coincidan con el template
            $templateProcessor->setMacroChars('{', '}');

            // 5️⃣ MAPEO EXACTO DE VARIABLES → MARCADORES PARA ADENDUM
            $reemplazos = [
                'evento' => $this->normalizar($c->nombre_feria),
                'nombre_empresa' => $this->normalizar($c->nombre_empresa),
                'nit' => $this->normalizar($c->nit),
                'pass' => $this->normalizar($c->pass),
                'nombre_gerente' => $this->normalizar($c->nombre_gerente),
                'ci_gerente' => $this->normalizar($c->ci_gerente),
                'exp_ci_gerente' => $this->normalizar($c->exp_ci_gerente),
            ];

            // 6️⃣ REEMPLAZAR MARCADORES EN WORD
            foreach ($reemplazos as $marcador => $valor) {
                try {
                    $templateProcessor->setValue($marcador, (string)$valor);
                } catch (\Exception $e) {
                    // Marcador no encontrado en el template, continuar
                }
            }

            // 7️⃣ GUARDAR WORD TEMPORAL
            $rutaTemporal = tempnam(sys_get_temp_dir(), 'adendum_');
            $rutaTemporal = substr($rutaTemporal, 0, -4) . '.docx';
            $templateProcessor->saveAs($rutaTemporal);

            // 8️⃣ CONVERTIR WORD A PDF
            $nombreArchivo = "Adendum2-" . $c->prefijo . $this->agr_ceros($c->codigo_contrato) . ".pdf";
            
            // Intentar convertir DOCX a PDF
            $rutaPDFFinal = null;
            
            // Método 1: LibreOffice (si está instalado)
            $rutaPDFFinal = $this->convertirDocxAPdfLibreOffice($rutaTemporal);
            
            // Método 2: Word COM (Windows)
            if (empty($rutaPDFFinal) || !file_exists($rutaPDFFinal)) {
                $rutaPDFFinal = $this->convertirDocxAPdfAlternativo($rutaTemporal);
            }
            
            // No sustituir silenciosamente la plantilla asignada por el PDF genérico.
            if (empty($rutaPDFFinal) || !file_exists($rutaPDFFinal)) {
                Log::error('No se pudo convertir el adendum Word a PDF para el contrato ' . $id_contrato . '.');
                @unlink($rutaTemporal);
                @unlink($rutaWordTemp);
                throw new \RuntimeException(
                    'No se pudo convertir el modelo Word del adendum a PDF. Verifique que LibreOffice esté instalado y que el servidor tenga permisos para ejecutarlo.'
                );
            }

            // 9️⃣ ENVIAR PDF AL NAVEGADOR
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Content-Length: ' . filesize($rutaPDFFinal));
            readfile($rutaPDFFinal);

            // LIMPIAR ARCHIVOS TEMPORALES
            @unlink($rutaTemporal);
            @unlink($rutaPDFFinal);
            @unlink($rutaWordTemp);

        } catch (\Throwable $e) {
            Log::error('Error generando adendum desde Word: ' . $e->getMessage() . ' - ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar el PDF desde el modelo Word asignado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Imprimir Contrato desde plantilla Word (.docx).
     * Reemplaza marcadores {variable} y convierte a PDF.
     */
    private function imprimirContratoWord($id)
    {
        try {
            // 1️⃣ VALIDACIONES INICIALES
            if (empty($id) || !is_numeric($id)) {
                throw new \Exception('ID de contrato inválido');
            }

            // 2️⃣ OBTENER DATOS DEL CONTRATO
            $c = DB::table('contrato as c')
                ->join('empresas as e', 'c.id_empresa', '=', 'e.id_empresa')
                ->join('eventos as ev', 'ev.id_feria', '=', 'c.id_feria')
                ->join('localidades as l', 'l.id', '=', 'e.ciudad')
                ->join('paises as p', 'p.id', '=', 'e.pais')
                ->join('stands as st', 'st.id_stand', '=', 'c.stand_1')
                ->join('pabellones as pab', 'pab.id_pabellon', '=', 'st.id_pabellon')
                ->select(
                    'c.id_feria', 'c.codigo_contrato', 'c.tipo_credenciales',
                    'ev.codigo_contrato as prefijo', 'ev.nombre_feria', 'ev.fecha_inicio', 'ev.fecha_fin',
                    'ev.id_modelocontrato',
                    'e.nombre_empresa', 'e.nit', 'e.direccion', 'e.email', 'e.telefono', 'e.web',
                    'p.nombre as pais', 'l.nombre as ciudad',
                    'e.nombre_gerente', 'e.ci_gerente', 'e.exp_ci_gerente', 'e.fono_gerente',
                    'c.productos', 'c.observaciones',
                    'c.stand_1', 'c.stand_2', 'c.stand_3', 'c.stand_4', 'c.stand_5', 
                    'c.stand_6', 'c.stand_7', 'c.stand_8', 'c.stand_9', 'c.stand_10', 'c.stand_11', 'c.stand_12',
                    'pab.nombre_pabellon', 'c.metraje_total', 'c.nro_credenciales', 'c.potencia',
                    'c.precio_unit', 'c.descuento', 'c.tipo_desc', 'c.precio_total_desc',
                    'c.porcentaje_inicial', 'c.porcentaje_final', 'c.total_stands', 'st.numero_stand',
                    'c.precio_total', 'c.monto_inicial', 'c.monto_final',
                    'c.fecha_inicial', 'c.fecha_final', 'c.fecha_realizacion',
                    'e.nr_escritura', 'e.fecha_nr_escritura', 'e.matricula',
                    'e.nr_poder', 'e.nr_notaria', 'e.fecha_nr_poder', 'e.distrito'
                )
                ->where('c.id_contrato', '=', $id)
                ->first();

            if (!$c) {
                throw new \Exception('Contrato no encontrado');
            }

            // 3️⃣ VALIDAR QUE EXISTE MODELO WORD
            if (empty($c->id_modelocontrato)) {
                throw new \RuntimeException('La feria no tiene un modelo de contrato asignado.');
            }

            $modelo = DB::table('modelo_contrato')
                ->where('id_modelo_contrato', '=', $c->id_modelocontrato)
                ->first();

            if (!$modelo || empty($modelo->ruta_documento)) {
                throw new \RuntimeException(
                    'No se encontró el modelo de contrato asignado (ID ' . $c->id_modelocontrato . ') o no tiene un archivo configurado.'
                );
            }

            // 4️⃣ CALCULAR TODAS LAS VARIABLES (reutilizar lógica FPDF)
            setlocale(LC_ALL, 'es_ES');
            $meses = array("", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
            
            $fecha_imprimir = substr($c->fecha_realizacion, 8, 2) . " de " . $meses[ceil(substr($c->fecha_realizacion, 5, 2))] . " del " . substr($c->fecha_realizacion, 0, 4);
            
            $c->precio_total = round($c->precio_total, 2);
            $c->porcentaje_inicial = round($c->porcentaje_inicial, 2);
            $c->porcentaje_final = round($c->porcentaje_final, 2);

            // Montos a letras
            if ($c->precio_total > 0) {
                $monto_literal = mb_strtoupper($this->convertir((int)$c->precio_total));
            } else {
                $monto_literal = "Cero";
            }

            if ($c->precio_total_desc > 0) {
                $monto_literal_desc = mb_strtoupper($this->convertir((int)$c->precio_total_desc));
            } else {
                $monto_literal_desc = "Cero";
            }

            // Centavos
            if (strpos($c->precio_total, ".") === false) {
                $cent = "00/100";
            } else {
                if (strlen(substr($c->precio_total, strpos($c->precio_total, "."), 3)) == 2) {
                    $cent = substr(substr($c->precio_total, strpos($c->precio_total, ".")), 1, 1) . "0/100";
                } else {
                    $cent = substr(substr($c->precio_total, strpos($c->precio_total, ".")), 1, 2) . "/100";
                }
            }

            if (strpos($c->precio_total_desc, ".") === false) {
                $cent_desc = "00/100";
            } else {
                if (strlen(substr($c->precio_total_desc, strpos($c->precio_total_desc, "."), 3)) == 2) {
                    $cent_desc = substr(substr($c->precio_total_desc, strpos($c->precio_total_desc, ".")), 1, 1) . "0/100";
                } else {
                    $cent_desc = substr(substr($c->precio_total_desc, strpos($c->precio_total_desc, ".")), 1, 2) . "/100";
                }
            }

            // Montos formateados
            if (strpos($c->precio_total, ".") === false) {
                $monto1 = $c->precio_total . ".00";
            } else {
                $monto1 = $c->precio_total;
                if (strlen(substr(substr($c->precio_total, strpos($c->precio_total, "."), 3), 1, 2)) == 1) {
                    $monto1 = $monto1 . "0";
                }
            }

            // Porcentajes y montos iniciales/finales
            $inicial_porcentaje = $c->porcentaje_inicial;
            if (strpos($c->monto_inicial, ".") === false) {
                $inicial_cantidad = $c->monto_inicial . ".00";
            } else {
                $inicial_cantidad = $c->monto_inicial;
                if (strlen(substr(substr($c->monto_inicial, strpos($c->monto_inicial, "."), 3), 1, 2)) == 1) {
                    $inicial_cantidad = $inicial_cantidad . "0";
                }
            }

            $resto_porcentaje = $c->porcentaje_final;
            if (strpos($c->monto_final, ".") === false) {
                $resto_cantidad = $c->monto_final . ".00";
            } else {
                $resto_cantidad = $c->monto_final;
                if (strlen(substr(substr($c->monto_final, strpos($c->monto_final, "."), 3), 1, 2)) == 1) {
                    $resto_cantidad = $resto_cantidad . "0";
                }
            }

            // Stands
            $stands = $c->numero_stand;
            $sector = substr($c->numero_stand, 0, 2);
            for ($i = 2; $i < $c->total_stands; $i++) {
                $st_temp = DB::table('stands')
                    ->select('numero_stand')
                    ->where('id_stand', '=', $c->{'stand_' . ($i + 1)})
                    ->first();
                if ($st_temp) {
                    $stands .= ', ' . $st_temp->numero_stand;
                }
            }

            // Descuento - Formatear precio_total_desc con decimales
            $nom_desc = strtoupper($c->tipo_desc);
            $precio_total_desc_formateado = $c->precio_total_desc;
            if (strpos($c->precio_total_desc, ".") === false) {
                $precio_total_desc_formateado = $c->precio_total_desc . ".00";
            } else {
                if (strlen(substr(substr($c->precio_total_desc, strpos($c->precio_total_desc, "."), 3), 1, 2)) == 1) {
                    $precio_total_desc_formateado = $c->precio_total_desc . "0";
                }
            }
            
            if ($c->descuento > 0) {
                $descuento_texto = " Se ha aplicado un descuento " . $nom_desc . " del " . $c->descuento . "%, resultando un total de Bs. " . $precio_total_desc_formateado . " (" . $monto_literal_desc . " " . $cent_desc . " Bolivianos)";
            } else {
                $descuento_texto = "";
            }

            // Tipo de credenciales (0=Física, 1=Digital)
            $tipo_credenciales_texto = (int) $c->tipo_credenciales === 1 ? 'DIGITALES' : 'FÍSICAS';

            // 5️⃣ CARGAR PLANTILLA WORD
            $rutaWord = $this->resolveModelDocumentPath($modelo->ruta_documento);
            if ($rutaWord === null) {
                throw new \Exception('Archivo de plantilla no encontrado: ' . $modelo->ruta_documento);
            }

            // Crear copia temporal limpia para este proceso de impresión
            // Esto garantiza que funcione aunque el template no haya sido limpiado al subir
            $rutaWordTemp = tempnam(sys_get_temp_dir(), 'tpl_') . '.docx';
            copy($rutaWord, $rutaWordTemp);
            \App\Http\Controllers\ModeloContratoController::limpiarDocxFragmentado($rutaWordTemp);

            $montoPagable = (float) ($c->descuento > 0 ? $c->precio_total_desc : $c->precio_total);
            $saldoPendiente = round($montoPagable - (float) $c->monto_inicial, 2, PHP_ROUND_HALF_UP);
            \App\Http\Controllers\ModeloContratoController::aplicarBloquePagoRestante($rutaWordTemp, $saldoPendiente > 0);

            $templateProcessor = new TemplateProcessor($rutaWordTemp);
            
            // CRÍTICO: El template usa {marcador} pero PhpOffice por defecto busca ${marcador}
            // Configurar los caracteres de apertura y cierre para que coincidan con el template
            $templateProcessor->setMacroChars('{', '}');

            // 6️⃣ MAPEO EXACTO DE VARIABLES → MARCADORES (basado en MODELOCONTRATO.docx - SIN ACENTOS)
            $reemplazos = [
                // Encabezado Contrato
                'codigo_contrato' => $c->prefijo . $this->agr_ceros($c->codigo_contrato),
                'evento' => $this->normalizar($c->nombre_feria),
                
                // Fechas principales
                'fecha_inicio' => $this->normalizar($c->fecha_inicio),
                'fecha_fin' => $this->normalizar($c->fecha_fin),
                
                // Empresa - Datos generales
                'nombre_empresa' => $this->normalizar($c->nombre_empresa),
                'direccion' => $this->normalizar($c->direccion),
                'telefono' => $this->normalizar($c->telefono),
                'email' => $this->normalizar($c->email),
                'web' => $this->normalizar($c->web),
                'ciudad' => $this->normalizar($c->ciudad),
                'pais' => $this->normalizar($c->pais),
                
                // Representante Legal
                'representante_legal' => $this->normalizar($c->nombre_gerente),
                'ci_representante' => $this->normalizar($c->ci_gerente . " " . $c->exp_ci_gerente),
                'telefono_representante' => $this->normalizar($c->fono_gerente),
                'ci_gerente' => $this->normalizar($c->ci_gerente),
                'exp_ci_gerente' => $this->normalizar($c->exp_ci_gerente),
                
                // Documentación Empresa
                'productos' => $this->normalizar($c->productos),
                'escritura' => $this->normalizar($c->nr_escritura),
                'fecha_escritura' => $this->normalizar($c->fecha_nr_escritura),
                'matricula' => $this->normalizar($c->matricula),
                'nit' => $this->normalizar($c->nit),
                'poder' => $this->normalizar($c->nr_poder),
                'notaria' => $this->normalizar($c->nr_notaria),
                'fecha_poder' => $this->normalizar($c->fecha_nr_poder),
                'distrito' => $this->normalizar($c->distrito),
                'observaciones' => $this->normalizar($c->observaciones),
                
                // Ubicación del Stand
                'nombre_pabellon' => $this->normalizar($c->nombre_pabellon),
                'pabellon' => $this->normalizar($c->nombre_pabellon), // Alias
                'metraje' => $this->normalizar($c->metraje_total) . ' m2',
                'credenciales' => $this->normalizar($c->nro_credenciales),
                'tipo_credenciales' => $tipo_credenciales_texto,
                'stand' => $this->normalizar($stands),
                'numero_stand' => $this->normalizar($stands), // Alias
                
                // Información Económica
                'precio_unit' => 'Bs. ' . $this->normalizar($c->precio_unit),
                'potencia' => $this->normalizar($c->potencia) . 'W',
                'monto1' => $this->normalizar($monto1),
                'monto_literal' => $this->normalizar($monto_literal),
                'cent' => $this->normalizar($cent),
                'descuento_texto' => $descuento_texto !== '' ? $descuento_texto : ' ',
                
                // Plan de Pagos
                'porcentaje_inicial' => ($inicial_porcentaje !== null && $inicial_porcentaje !== '') ? $inicial_porcentaje : '0',
                'monto_inicial' => ($inicial_cantidad !== null && $inicial_cantidad !== '') ? $inicial_cantidad : '0',
                'fecha_inicial' => $this->normalizar(substr($c->fecha_inicial, 8, 2) . "/" . substr($c->fecha_inicial, 5, 2) . "/" . substr($c->fecha_inicial, 0, 4)),
                'porcentaje_resto' => ($resto_porcentaje !== null && $resto_porcentaje !== '') ? $resto_porcentaje : '0',
                'monto_resto' => ($resto_cantidad !== null && $resto_cantidad !== '') ? $resto_cantidad : '0',
                'fecha_final' => $this->normalizar(substr($c->fecha_final, 8, 2) . "/" . substr($c->fecha_final, 5, 2) . "/" . substr($c->fecha_final, 0, 4)),
                
                // Firma del Contrato
                'fecha_contrato' => 'Cochabamba, ' . $fecha_imprimir,
                'nombre_gerente' => $this->normalizar($c->nombre_gerente),
            ];

            // 7️⃣ REEMPLAZAR MARCADORES EN WORD
            foreach ($reemplazos as $marcador => $valor) {
                try {
                    $templateProcessor->setValue($marcador, (string)$valor);
                } catch (\Exception $e) {
                    // Marcador no encontrado en el template, continuar
                }
            }

            // 8️⃣ GUARDAR WORD TEMPORAL
            $rutaTemporal = tempnam(sys_get_temp_dir(), 'contrato_');
            $rutaTemporal = substr($rutaTemporal, 0, -4) . '.docx';
            $templateProcessor->saveAs($rutaTemporal);

            // 9️⃣ CONVERTIR WORD A PDF
            $nombreArchivo = "Contrato-" . $c->prefijo . $this->agr_ceros($c->codigo_contrato) . ".pdf";
            
            // Intentar convertir DOCX a PDF
            $rutaPDFFinal = null;
            
            // Método 1: LibreOffice (si está instalado)
            $rutaPDFFinal = $this->convertirDocxAPdfLibreOffice($rutaTemporal);
            
            // Método 2: Word COM (Windows)
            if (empty($rutaPDFFinal) || !file_exists($rutaPDFFinal)) {
                $rutaPDFFinal = $this->convertirDocxAPdfAlternativo($rutaTemporal);
            }
            
            // No sustituir silenciosamente la plantilla asignada por el PDF genérico.
            if (empty($rutaPDFFinal) || !file_exists($rutaPDFFinal)) {
                Log::error('No se pudo convertir el contrato Word a PDF para el contrato ' . $id . '.');
                @unlink($rutaTemporal);
                @unlink($rutaWordTemp);
                throw new \RuntimeException(
                    'No se pudo convertir el modelo Word del contrato a PDF. Verifique que LibreOffice esté instalado y que el servidor tenga permisos para ejecutarlo.'
                );
            }

            // 🔟 ENVIAR PDF AL NAVEGADOR
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Content-Length: ' . filesize($rutaPDFFinal));
            readfile($rutaPDFFinal);

            // LIMPIAR ARCHIVOS TEMPORALES
            @unlink($rutaTemporal);
            @unlink($rutaPDFFinal);
            @unlink($rutaWordTemp);

        } catch (\Throwable $e) {
            Log::error('Error generando contrato desde Word: ' . $e->getMessage() . ' - ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar el PDF desde el modelo Word asignado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convierte DOCX a PDF usando LibreOffice
     * Retorna la ruta del PDF si es exitoso, null si falla
     */
    private function convertirDocxAPdfLibreOffice($rutaDocx)
    {
        try {
            $rutaPDF = str_replace('.docx', '.pdf', $rutaDocx);
            $dirTemp = dirname($rutaPDF);
            
            $comando = null;
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows: buscar soffice.exe en rutas comunes
                $rutasSoffice = [
                    'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                    'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
                    'C:\\laragon\\bin\\libreoffice\\program\\soffice.exe',
                    'C:\\Program Files\\LibreOffice\\soffice.exe',
                ];
                
                foreach ($rutasSoffice as $rutaEj) {
                    if (file_exists($rutaEj)) {
                        $comando = '"' . $rutaEj . '" --headless --safe-mode --convert-to pdf:writer_pdf_Export --outdir ' . escapeshellarg($dirTemp) . ' ' . escapeshellarg($rutaDocx) . ' 2>&1';
                        break;
                    }
                }
                
                if (!$comando) {
                    $comando = 'soffice --headless --safe-mode --convert-to pdf:writer_pdf_Export --outdir ' . escapeshellarg($dirTemp) . ' ' . escapeshellarg($rutaDocx) . ' 2>&1';
                }
            } else {
                $rutasSoffice = [
                    '/Applications/LibreOffice.app/Contents/MacOS/soffice',
                    '/usr/local/bin/soffice',
                    '/opt/homebrew/bin/soffice',
                    '/usr/bin/soffice',
                    '/usr/bin/libreoffice',
                ];

                $salidaRuta = [];
                exec('command -v soffice 2>/dev/null', $salidaRuta, $codigoRuta);
                if ($codigoRuta === 0 && !empty($salidaRuta[0])) {
                    array_unshift($rutasSoffice, trim($salidaRuta[0]));
                }

                $ejecutableSoffice = collect($rutasSoffice)
                    ->first(fn ($ruta) => is_executable($ruta));

                if (!$ejecutableSoffice) {
                    Log::warning('No se encontró LibreOffice/soffice para convertir DOCX a PDF.');
                    return null;
                }

                $comando = escapeshellarg($ejecutableSoffice) . ' --headless --convert-to pdf:writer_pdf_Export --outdir ' . escapeshellarg($dirTemp) . ' ' . escapeshellarg($rutaDocx) . ' 2>&1';
            }
            
            // Ejecutar conversión
            $output = [];
            $returnCode = 0;
            exec($comando, $output, $returnCode);
            
            if ($returnCode !== 0) {
                Log::warning("LibreOffice conversión falló. Código: {$returnCode}, Comando: {$comando}, Output: " . implode("\n", $output));
                return null;
            }
            
            if (file_exists($rutaPDF)) {
                Log::info("Conversión exitosa con LibreOffice: {$rutaPDF}");
                return $rutaPDF;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning("Error en convertirDocxAPdfLibreOffice: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Convierte DOCX a PDF usando método alternativo
     * Para Windows, intenta usar PowerShell + Word COM
     * Para Linux/Mac, intenta usar LibreOffice de nuevo con opciones diferentes
     */
    private function convertirDocxAPdfAlternativo($rutaDocx)
    {
        try {
            $rutaPDF = str_replace('.docx', '.pdf', $rutaDocx);
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Método Windows: PowerShell + Word COM
                // Usamos ExportAsFixedFormat con BitmapMissingFonts=true para garantizar
                // que los caracteres especiales (acentos, tildes) se rendericen correctamente
                // incluso si la fuente del documento no tiene soporte Unicode completo.
                $scriptPs = tempnam(sys_get_temp_dir(), 'word2pdf_') . '.ps1';
                $rutaDocxEsc = str_replace('\\', '\\\\', $rutaDocx);
                $rutaPDFEsc  = str_replace('\\', '\\\\', $rutaPDF);
                $psContent = <<<PS
\$ErrorActionPreference = 'Stop'
\$word = New-Object -ComObject Word.Application
\$word.Visible = \$false
\$word.DisplayAlerts = 0
try {
    \$doc = \$word.Documents.Open('{$rutaDocxEsc}', \$false, \$true)
    # ExportAsFixedFormat con BitmapMissingFonts=true resuelve problemas de fuentes
    # que no tienen soporte para caracteres acentuados (e.g. Helvetica Type1)
    \$doc.ExportAsFixedFormat(
        '{$rutaPDFEsc}',  # OutputFileName
        17,               # wdExportFormatPDF
        \$false,          # OpenAfterExport
        0,                # wdExportOptimizeForPrint
        0,                # wdExportAllDocument
        0, 0,             # From, To
        0,                # wdExportDocumentContent
        \$true,           # IncludeDocProps
        \$true,           # KeepIRM
        0,                # wdExportCreateNoBookmarks
        \$true,           # DocStructureTags
        \$true,           # BitmapMissingFonts - CLAVE para fuentes sin Unicode
        \$false           # UseISO19005_1
    )
    \$doc.Close(\$false)
} finally {
    \$word.Quit()
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject(\$word) | Out-Null
}
PS;
                file_put_contents($scriptPs, $psContent);
                
                $output = [];
                $returnCode = 0;
                exec('powershell -NonInteractive -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($scriptPs) . ' 2>&1', $output, $returnCode);
                @unlink($scriptPs);
                
                if ($returnCode === 0 && file_exists($rutaPDF)) {
                    Log::info("Conversión exitosa con Microsoft Word ExportAsFixedFormat: {$rutaPDF}");
                    return $rutaPDF;
                }
                
                Log::warning("Word ExportAsFixedFormat falló (código $returnCode). Output: " . implode(' | ', $output));
            }
            
            // Método fallback: Intenta LibreOffice con configuración mínima
            $comando = null;
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $comando = 'libreoffice --convert-to pdf --outdir ' . escapeshellarg(dirname($rutaPDF)) . ' ' . escapeshellarg($rutaDocx);
            } else {
                $comando = 'libreoffice --convert-to pdf --outdir ' . escapeshellarg(dirname($rutaPDF)) . ' ' . escapeshellarg($rutaDocx);
            }
            
            $output = [];
            $returnCode = 0;
            exec($comando, $output, $returnCode);
            
            if (file_exists($rutaPDF)) {
                Log::info("Conversión exitosa con método alternativo: {$rutaPDF}");
                return $rutaPDF;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning("Error en convertirDocxAPdfAlternativo: " . $e->getMessage());
            return null;
        }
    }

    /**
        while (strlen($x) < 5)
            $x = "0" . $x;
        return $x;
    }

    /**
     * Convierte números básicos (1-29) a texto
     */
    private function basico($numero)
    {
        $valor = array('uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciseis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidos', 'veintitres', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve');
        return $valor[$numero - 1];
    }

    /**
     * Convierte decenas (30-99) a texto
     */
    private function decenas($n)
    {
        $decenas = array(
            30 => 'treinta', 40 => 'cuarenta', 50 => 'cincuenta', 60 => 'sesenta',
            70 => 'setenta', 80 => 'ochenta', 90 => 'noventa'
        );
        if ($n <= 29) return $this->basico($n);
        $x = $n % 10;
        if ($x == 0) {
            return $decenas[$n];
        } else return $decenas[$n - $x] . ' y ' . $this->basico($x);
    }

    /**
     * Convierte centenas (100-999) a texto
     */
    private function centenas($n)
    {
        $cientos = array(
            100 => 'cien', 200 => 'doscientos', 300 => 'trecientos',
            400 => 'cuatrocientos', 500 => 'quinientos', 600 => 'seiscientos',
            700 => 'setecientos', 800 => 'ochocientos', 900 => 'novecientos'
        );
        if ($n >= 100) {
            if ($n % 100 == 0) {
                return $cientos[$n];
            } else {
                $u = (int) substr($n, 0, 1);
                $d = (int) substr($n, 1, 2);
                return (($u == 1) ? 'ciento' : $cientos[$u * 100]) . ' ' . $this->decenas($d);
            }
        } else return $this->decenas($n);
    }

    /**
     * Convierte miles (1000-999999) a texto
     */
    private function miles($n)
    {
        if ($n > 999) {
            if ($n == 1000) {
                return 'mil';
            } else {
                $l = strlen($n);
                $c = (int)substr($n, 0, $l - 3);
                $x = (int)substr($n, -3);
                if ($c == 1) {
                    $cadena = 'mil ' . $this->centenas($x);
                } else if ($x != 0) {
                    $cadena = $this->centenas($c) . ' mil ' . $this->centenas($x);
                } else $cadena = $this->centenas($c) . ' mil';
                return $cadena;
            }
        } else return $this->centenas($n);
    }

    /**
     * Convierte millones (>= 1000000) a texto
     */
    private function millones($n)
    {
        if ($n == 1000000) {
            return 'un millón';
        } else {
            $l = strlen($n);
            $c = (int)substr($n, 0, $l - 6);
            $x = (int)substr($n, -6);
            if ($c == 1) {
                $cadena = ' millón ';
            } else {
                $cadena = ' millones ';
            }
            return $this->miles($c) . $cadena . (($x > 0) ? $this->miles($x) : '');
        }
    }

    /**
     * Función principal de conversión de números a texto
     */
    private function convertir($n)
    {
        switch ($n) {
            case ($n >= 1 && $n <= 29):
                return $this->basico($n);
                break;
            case ($n >= 30 && $n < 100):
                return $this->decenas($n);
                break;
            case ($n >= 100 && $n < 1000):
                return $this->centenas($n);
                break;
            case ($n >= 1000 && $n <= 999999):
                return $this->miles($n);
                break;
            case ($n >= 1000000):
                return $this->millones($n);
        }
    }

    /**
     * Normaliza valores NULL, vacíos o inválidos → 'N/A'
     */
    private function normalizar($valor)
    {
        if ($valor === null || $valor === '') {
            return 'N/A';
        }
        $normalizado = trim((string)$valor);
        return empty($normalizado) ? 'N/A' : $normalizado;
    }

    /**
     * Agrega ceros a la izquierda hasta completar 5 dígitos
     */
    private function agr_ceros($x)
    {
        while (strlen($x) < 5)
            $x = "0" . $x;
        return $x;
    }
}
