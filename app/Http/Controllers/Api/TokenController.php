<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoOrganizacion;
use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Emisión y revocación de tokens personales (Laravel Sanctum) para la API. */
class TokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'dispositivo' => ['nullable', 'string', 'max:100'],
        ]);

        $usuario = Usuario::sinFiltroOrganizacion()->where('email', $datos['email'])->first();
        if (! $usuario || ! Hash::check($datos['password'], $usuario->password_hash)) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }
        if (! $usuario->estaActivo() || in_array($usuario->organizacion?->estado, [EstadoOrganizacion::Suspendida, EstadoOrganizacion::Inactiva], true)) {
            throw ValidationException::withMessages(['email' => 'El usuario o su organización no están activos.']);
        }

        $token = $usuario->createToken($datos['dispositivo'] ?? 'api', ['*'], now()->addMinutes((int) config('sanctum.expiration', 480)));
        $usuario->forceFill(['ultimo_acceso' => now()])->saveQuietly();

        return response()->json([
            'token' => $token->plainTextToken,
            'tipo' => 'Bearer',
            'expira_en' => $token->accessToken->expires_at?->toIso8601String(),
            'usuario' => ['nombre' => $usuario->nombre, 'rol' => $usuario->rolPrincipal()?->value, 'organizacion' => $usuario->organizacion?->razon_social],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['mensaje' => 'Token revocado.']);
    }
}
