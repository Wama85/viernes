<?php

namespace App\Modules\Usuarios\Http\Requests;

use App\Modules\Usuarios\Http\Requests\Concerns\ReglasUsuario;
use Illuminate\Foundation\Http\FormRequest;

class StoreUsuarioRequest extends FormRequest
{
    use ReglasUsuario;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->reglasUsuario();
    }
}
