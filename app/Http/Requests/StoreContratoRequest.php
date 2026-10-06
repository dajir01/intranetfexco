<?php

namespace App\Http\Requests;

use App\Models\Contrato;
use Illuminate\Foundation\Http\FormRequest;

class StoreContratoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $idContrato = $this->route('id_contrato');
        $idFeria = $idContrato
            ? Contrato::query()->whereKey($idContrato)->value('id_feria')
            : null;
        $reglaTipoCredenciales = (int) $idFeria >= 21
            ? 'required|in:0,1'
            : 'nullable|in:0,1';

        return [
            // ========== SECCIÓN 1: DATOS DE LA EMPRESA ==========
            'nombre_empresa' => 'required|string|max:255',
            'direccion' => 'required|string|max:500',
            'nit' => 'required|string|max:50',
            'telefono' => 'required|string|min:7|max:20',
            'email' => 'nullable|email|max:255',
            'pais_id' => 'required|integer|exists:paises,id',
            'ciudad_id' => 'required|integer|exists:localidades,id',
            'cluster' => 'required|in:1,2,3,4', // Categoría de empresa
            'actividad_principal' => 'required|in:1,2,3',
            'rubro' => 'required|string|not_in:0,No Asignado',
            'subrubro' => 'required|string|not_in:0,No Asignado',
            
            // Campos opcionales de la sección 1
            'fax' => 'nullable|string|max:20',
            'web' => 'nullable|url|max:255',
            'aniversario' => 'nullable|date',
            'nr_escritura' => 'nullable|string|max:100',
            'fecha_nr_escritura' => 'nullable|date',
            'matricula' => 'nullable|string|max:100',
            'nr_poder' => 'nullable|string|max:100',
            'nr_notaria' => 'nullable|string|max:100',
            'fecha_nr_poder' => 'nullable|date',
            'distrito' => 'nullable|string|max:100',
            'otro_rubro' => 'required|string|max:255',
            
            // ========== SECCIÓN 2: DATOS RESPONSABLE / CONTACTO ==========
            // Representante Legal
            'nombre_gerente' => 'required|string|max:255',
            'ci_gerente' => 'required|string|max:50',
            'exp_ci_gerente' => 'required|string|in:Cb.,Lp.,Sc.,Or.,Tj.,Pt.,Ch.,Bn.,Pn.,Ex.',
            'fono_gerente' => 'required|string|min:7|max:20',
            'cargo_gerente' => 'required|string|max:100',
            
            // Contacto
            'nombre_responsable' => 'required|string|max:255', // nombre_contacto en el frontend
            'telefono_responsable' => 'required|string|min:7|max:20', // telefono_contacto en el frontend
            'email_representante' => 'nullable|email|max:255', // email_contacto en el frontend (opcional)
            
            // ========== SECCIÓN 3: DATOS DEL EVENTO ==========
            'medio_comunicacion' => 'required|in:1,2,3,4,5',
            'como_entero' => 'required|in:1,2,3,4',
            'tipo_expositor' => 'required|in:1,2,3',
            'tipo_credenciales' => $reglaTipoCredenciales,
            'productos' => 'required|string|min:10',
            
            // Campos opcionales de la sección 3
            'perfil_visitante' => 'nullable|string',
            'observaciones' => 'nullable|string',
            
            // ========== MARCAS (OPCIONALES) ==========
            'marca_principal' => 'nullable|integer',
            'mn' => 'nullable|array',
            'mn.*' => 'nullable|string|max:255',
            
            'pais_principal' => 'nullable|integer',
            'pp' => 'nullable|array',
            'pp.*' => 'nullable|string|max:255',
            'pais_pp' => 'nullable|array',
            'pais_pp.*' => 'nullable',
            
            'marcas_secundarios' => 'nullable|integer',
            'mu' => 'nullable|array',
            'mu.*' => 'nullable|string|max:255',
            'pais_mu' => 'nullable|array',
            'pais_mu.*' => 'nullable',
            
            // ========== INFORMACIÓN DEL CONTRATO (OPCIONALES) ==========
            'metraje_total' => 'nullable|numeric|min:0',
            'precio_unit' => 'nullable|numeric|min:0',
            'descuento' => 'nullable|numeric|min:0|max:100',
            'tipo_desc' => 'nullable|string|max:100',
            'monto_inicial' => 'nullable|numeric|min:0',
            'fecha_inicial' => 'nullable|date',
            'fecha_final' => 'nullable|date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Mensajes personalizados para campos obligatorios
            'nombre_empresa.required' => 'El nombre de la empresa es obligatorio.',
            'direccion.required' => 'La dirección es obligatoria.',
            'nit.required' => 'El NIT es obligatorio.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.min' => 'El teléfono debe tener al menos 7 dígitos.',
            'email.email' => 'Debe ingresar un email válido.',
            'pais_id.required' => 'Debe seleccionar un país.',
            'pais_id.exists' => 'El país seleccionado no es válido.',
            'ciudad_id.required' => 'Debe seleccionar una ciudad.',
            'ciudad_id.exists' => 'La ciudad seleccionada no es válida.',
            'cluster.required' => 'Debe seleccionar la categoría de la empresa.',
            'actividad_principal.required' => 'Debe seleccionar la actividad principal.',
            'rubro.required' => 'Debe seleccionar un rubro.',
            'rubro.not_in' => 'Debe seleccionar un rubro válido.',
            'subrubro.required' => 'Debe seleccionar un sub rubro.',
            'subrubro.not_in' => 'Debe seleccionar un sub rubro válido.',
            'otro_rubro.required' => 'Los otros rubros adicionales son obligatorios.',
            
            // Representante
            'nombre_gerente.required' => 'El nombre del representante es obligatorio.',
            'ci_gerente.required' => 'El documento de identidad del representante es obligatorio.',
            'exp_ci_gerente.required' => 'Debe seleccionar el lugar de expedición del CI.',
            'fono_gerente.required' => 'El teléfono del representante es obligatorio.',
            'fono_gerente.min' => 'El teléfono del representante debe tener al menos 7 dígitos.',
            'cargo_gerente.required' => 'El cargo del representante es obligatorio.',
            
            // Contacto
            'nombre_responsable.required' => 'El nombre del contacto es obligatorio.',
            'telefono_responsable.required' => 'El teléfono del contacto es obligatorio.',
            'telefono_responsable.min' => 'El teléfono del contacto debe tener al menos 7 dígitos.',
            
            // Datos del evento
            'medio_comunicacion.required' => 'Debe seleccionar el medio por el cual fue aproximado.',
            'como_entero.required' => 'Debe seleccionar cómo se enteró de la feria.',
            'tipo_expositor.required' => 'Debe seleccionar el tipo de expositor.',
            'tipo_credenciales.required' => 'Debe seleccionar el tipo de credencial.',
            'productos.required' => 'Debe describir los productos/servicios que ofrecerá.',
            'productos.min' => 'La descripción de productos debe tener al menos 10 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre_empresa' => 'nombre de la empresa',
            'direccion' => 'dirección',
            'nit' => 'NIT',
            'telefono' => 'teléfono',
            'email' => 'email',
            'pais_id' => 'país',
            'ciudad_id' => 'ciudad',
            'cluster' => 'categoría de empresa',
            'actividad_principal' => 'actividad principal',
            'rubro' => 'rubro',
            'subrubro' => 'sub rubro',
            'otro_rubro' => 'otros rubros adicionales',
            'nombre_gerente' => 'nombre del representante',
            'ci_gerente' => 'CI del representante',
            'exp_ci_gerente' => 'expedido CI',
            'fono_gerente' => 'teléfono del representante',
            'cargo_gerente' => 'cargo del representante',
            'nombre_responsable' => 'nombre del contacto',
            'telefono_responsable' => 'teléfono del contacto',
            'medio_comunicacion' => 'medio de comunicación',
            'como_entero' => 'cómo se enteró',
            'tipo_expositor' => 'tipo de expositor',
            'tipo_credenciales' => 'tipo de credencial',
            'productos' => 'productos/servicios',
        ];
    }
}
