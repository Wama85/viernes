<?php

namespace App\Modules\Productos\Http\Requests;

use App\Modules\Productos\Http\Requests\Concerns\ReglasCategoria;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoriaRequest extends FormRequest
{
    use ReglasCategoria;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->reglasCategoria($this->route('categoria'));
    }
}
