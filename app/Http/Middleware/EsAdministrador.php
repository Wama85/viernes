<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EsAdministrador
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->id_rol == 1) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
