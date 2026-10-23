<?php

namespace App\Services\Superset;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupersetClient
{
    /**
     * @param  list<array{clause: string}>  $rls
     */
    public function tokenInvitado(string $dashboardUuid, array $rls, bool $publico): string
    {
        $url = (string) config('superset.url');

        if ($url === '' || ! filled(config('superset.username')) || ! filled(config('superset.password'))) {
            throw new RuntimeException('Superset no está configurado.');
        }

        $acceso = $this->tokenDeServicio($url);
        $usuario = $publico
            ? ['username' => 'publico_micrositio', 'first_name' => 'Ciudadano', 'last_name' => 'Anonimo']
            : ['username' => 'usuario_siid', 'first_name' => 'Usuario', 'last_name' => 'SIID'];

        try {
            $respuesta = Http::timeout(8)
                ->retry(2, 100, fn (mixed $excepcion): bool => $excepcion instanceof ConnectionException)
                ->acceptJson()
                ->withToken($acceso)
                ->post($url.'/api/v1/security/guest_token/', [
                    'user' => $usuario,
                    'resources' => [['type' => 'dashboard', 'id' => $dashboardUuid]],
                    'rls' => $rls,
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('No fue posible obtener el token de Superset.', 0, $exception);
        }

        $token = $respuesta->json('token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('No fue posible obtener el token de Superset.');
        }

        return $token;
    }

    private function tokenDeServicio(string $url): string
    {
        $cacheado = Cache::get('superset.access_token');

        if (is_string($cacheado) && $cacheado !== '') {
            return $cacheado;
        }

        try {
            $respuesta = Http::timeout(8)
                ->retry(2, 100, fn (mixed $excepcion): bool => $excepcion instanceof ConnectionException)
                ->acceptJson()
                ->post($url.'/api/v1/security/login', [
                    'username' => config('superset.username'),
                    'password' => config('superset.password'),
                    'provider' => config('superset.provider', 'db'),
                    'refresh' => true,
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('No fue posible autenticar la cuenta de servicio de Superset.', 0, $exception);
        }

        $token = $respuesta->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('No fue posible autenticar la cuenta de servicio de Superset.');
        }

        Cache::put('superset.access_token', $token, now()->addMinutes(4));

        return $token;
    }
}
