<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /** Muestra el perfil del usuario. */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'usuario' => $request->user()->load('rol'),
        ]);
    }

    /** Actualiza el nombre (el correo no se cambia desde aquí). */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('profile.edit')->with('status', 'perfil-actualizado');
    }

    /** Cambia la contraseña: pide la actual y aplica la misma regla que al crear usuarios. */
    public function actualizarClave(Request $request): RedirectResponse
    {
        $letrasYNumeros = function ($atributo, $valor, $fallar) {
            if (! preg_match('/\p{L}/u', (string) $valor) || ! preg_match('/\d/', (string) $valor)) {
                $fallar('La contraseña debe incluir al menos una letra y un número.');
            }
        };

        $request->validate([
            'current_password'      => ['required', 'string', 'current_password'],
            'password'              => ['required', 'string', 'min:8', 'max:72', 'different:current_password', $letrasYNumeros],
            'password_confirmation' => ['required', 'same:password'],
        ], [
            'current_password.required'         => 'Ingresa tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required'                 => 'La nueva contraseña es obligatoria.',
            'password.min'                      => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max'                      => 'La contraseña admite como máximo 72 caracteres.',
            'password.different'                => 'La nueva contraseña debe ser distinta de la actual.',
            'password_confirmation.required'    => 'Confirma la contraseña.',
            'password_confirmation.same'        => 'Las contraseñas no coinciden.',
        ]);

        // Sin auditoría, para que el hash no quede en el historial.
        $request->user()->forceFill([
            'password'       => Hash::make($request->input('password')),
            'remember_token' => Str::random(60),
        ])->saveQuietly();

        return redirect()->route('profile.edit')->with('status', 'clave-actualizada');
    }
}
