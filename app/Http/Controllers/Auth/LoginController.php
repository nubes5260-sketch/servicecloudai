<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EstadoOrganizacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_INTENTOS = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $clave = Str::lower($request->string('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Vuelve a intentarlo en '.RateLimiter::availableIn($clave).' segundos.',
            ]);
        }

        $credenciales = $request->only('email', 'password');
        if (! Auth::validate($credenciales)) {
            RateLimiter::hit($clave, 60);
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }

        /** @var Usuario $usuario */
        $usuario = Auth::getProvider()->retrieveByCredentials($credenciales);

        if (! $usuario->estaActivo()) {
            throw ValidationException::withMessages(['email' => 'Tu usuario está '.Str::lower($usuario->estado->label()).'. Contacta al administrador.']);
        }
        $organizacion = $usuario->organizacion;
        if ($organizacion && in_array($organizacion->estado, [EstadoOrganizacion::Suspendida, EstadoOrganizacion::Inactiva], true)) {
            throw ValidationException::withMessages(['email' => 'La organización '.$organizacion->razon_social.' está '.Str::lower($organizacion->estado->label()).'.']);
        }

        RateLimiter::clear($clave);
        Auth::login($usuario);
        $request->session()->regenerate();
        $usuario->forceFill(['ultimo_acceso' => now()])->saveQuietly();

        return redirect()->intended(route('dashboard'))
            ->with('ok', ['Bienvenido, '.Str::before($usuario->nombre, ' '), 'Sesión iniciada como '.$usuario->rolPrincipal()?->label().'.']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('ok', ['Sesión cerrada', 'Hasta pronto.']);
    }
}
