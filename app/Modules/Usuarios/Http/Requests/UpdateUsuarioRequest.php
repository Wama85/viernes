<?php

namespace App\Modules\Usuarios\Http\Requests;

use App\Modules\Usuarios\Http\Requests\Concerns\ReglasUsuario;
use App\Modules\Usuarios\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateUsuarioRequest extends FormRequest
{
    use ReglasUsuario;

    private const ROL_ADMIN = 1;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->reglasUsuario($this->route('usuario'));
    }

    /** No se puede quitar el rol de administrador al único administrador activo. */
    public function after(): array
    {
        return [
            function (Validator $validador) {
                $usuario = $this->route('usuario');

                $eraAdminActivo = (int) $usuario->id_rol === self::ROL_ADMIN
                    && (int) $usuario->usuario_estado === 1;

                if (! $eraAdminActivo || (int) $this->input('id_rol') === self::ROL_ADMIN) {
                    return;
                }

                $otrosAdmins = Usuario::where('id_rol', self::ROL_ADMIN)
                    ->where('usuario_estado', 1)
                    ->where('id_usuario', '!=', $usuario->id_usuario)
                    ->count();

                if ($otrosAdmins === 0) {
                    $validador->errors()->add(
                        'id_rol',
                        'Es el único administrador activo: no puedes cambiarle el rol.'
                    );
                }
            },
        ];
    }
}
