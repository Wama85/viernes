<?php

namespace App\Modules\Clientes\Http\Requests;

use App\Modules\Clientes\Http\Requests\Concerns\ReglasCliente;
use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    use ReglasCliente;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->reglasCliente();
    }
}
