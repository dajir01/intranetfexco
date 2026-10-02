<?php

namespace App\Http\Controllers;

use App\Models\Pais;
use App\Models\Localidad;
use App\Models\Rubro;
use App\Models\Subrubro;
use Illuminate\Http\Request;

class LocalidadController extends Controller
{
    /**
     * Obtener todos los países
     */
    public function getPaises()
    {
        try {
            $paises = Pais::all()->map(function ($pais) {
                return [
                    'id' => $pais->id,
                    'nombre_pais' => $pais->nombre,
                    'nombre' => $pais->nombre,
                    'label' => $pais->nombre,
                ];
            });

            return response()->json($paises);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener países'], 500);
        }
    }

    /**
     * Autocomplete de países
     */
    public function autocompletePaises(Request $request)
    {
        try {
            $term = $request->query('term', '');

            $paises = Pais::where('nombre', 'like', '%' . $term . '%')
                ->get()
                ->map(function ($pais) {
                    return [
                        'id' => $pais->id,
                        'value' => $pais->nombre,
                        'label' => $pais->nombre,
                    ];
                });

            return response()->json($paises);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error en autocomplete de países'], 500);
        }
    }

    /**
     * Obtener ciudades por país
     */
    public function getCiudadesByPais($idPais)
    {
        try {
            $ciudades = Localidad::where('id_pais', $idPais)
                ->get()
                ->map(function ($ciudad) {
                    return [
                        'id' => $ciudad->id,
                        'nombre' => $ciudad->nombre,
                        'value' => $ciudad->nombre,
                        'label' => $ciudad->nombre,
                    ];
                });

            return response()->json($ciudades);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener ciudades'], 500);
        }
    }

    /**
     * Autocomplete de ciudades
     */
    public function autocompleteCiudades(Request $request)
    {
        try {
            $term = $request->query('term', '');
            $idPais = $request->query('id_pais');

            $query = Localidad::where('nombre', 'like', '%' . $term . '%');

            if ($idPais) {
                $query->where('id_pais', $idPais);
            }

            $ciudades = $query->get()
                ->map(function ($ciudad) {
                    return [
                        'id' => $ciudad->id,
                        'value' => $ciudad->nombre,
                        'label' => $ciudad->nombre,
                    ];
                });

            return response()->json($ciudades);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error en autocomplete de ciudades'], 500);
        }
    }

    /**
     * Obtener nombre del país por ID
     */
    public function getPaisNombre($id)
    {
        try {
            $pais = Pais::find($id);

            if (!$pais) {
                return response()->json(['error' => 'País no encontrado'], 404);
            }

            return response()->json(['nombre' => $pais->nombre]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener país'], 500);
        }
    }

    /**
     * Obtener nombre de la ciudad por ID
     */
    public function getCiudadNombre($id)
    {
        try {
            $ciudad = Localidad::find($id);

            if (!$ciudad) {
                return response()->json(['error' => 'Ciudad no encontrada'], 404);
            }

            return response()->json(['nombre' => $ciudad->nombre]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener ciudad'], 500);
        }
    }

    /**
     * Obtener todos los rubros
     */
    public function getRubros()
    {
        try {
            $rubros = Rubro::all()->map(function ($rubro) {
                return [
                    'id_rubro' => $rubro->id_rubro,
                    'nombre_rubro' => $rubro->nombre_rubro,
                ];
            });

            return response()->json($rubros);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener rubros'], 500);
        }
    }

    /**
     * Obtener todos los subrubros
     */
    public function getSubrubros()
    {
        try {
            $subrubros = Subrubro::all()->map(function ($subrubro) {
                return [
                    'id_subrubro' => $subrubro->id_subrubro,
                    'nombre_subrubro' => $subrubro->nombre_subrubro,
                    'id_rubro' => $subrubro->id_rubro,
                ];
            });

            return response()->json($subrubros);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener subrubros'], 500);
        }
    }
}
