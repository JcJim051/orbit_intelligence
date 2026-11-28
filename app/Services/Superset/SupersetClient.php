<?php

namespace App\Services\Superset;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupersetClient
{
    private ?string $accessToken = null;

    private ?string $csrfToken = null;

    private ?CookieJar $cookies = null;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $payload = []): array
    {
        $url = rtrim((string) config('superset.url'), '/');

        if ($url === '') {
            throw new RuntimeException('Superset no está configurado.');
        }

        try {
            $request = Http::timeout(20)
                ->retry(2, 200, fn (mixed $exception): bool => $exception instanceof ConnectionException)
                ->acceptJson()
                ->withToken($this->tokenAcceso())
                ->withHeaders([
                    'X-CSRFToken' => $this->tokenCsrf(),
                    'Referer' => $url,
                ])
                ->withOptions(['cookies' => $this->cookieJar()]);

            $response = match (strtolower($method)) {
                'get' => $request->get($url.$path, $payload),
                'post' => $request->post($url.$path, $payload),
                'put' => $request->put($url.$path, $payload),
                'delete' => $request->delete($url.$path, $payload),
                default => throw new RuntimeException('Método HTTP no soportado para Superset.'),
            };

            $response->throw();
        } catch (RequestException $exception) {
            $detail = $exception->response?->json('message');
            $message = is_string($detail) && $detail !== ''
                ? 'Superset rechazó la operación: '.$detail
                : 'No fue posible completar la operación en Superset.';

            throw new RuntimeException($message, 0, $exception);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  list<array{clause: string}>  $rls
     */
    public function tokenInvitado(string $dashboardUuid, array $rls, bool $publico): string
    {
        $usuario = $publico
            ? ['username' => 'publico_micrositio', 'first_name' => 'Ciudadano', 'last_name' => 'Anonimo']
            : ['username' => 'usuario_siid', 'first_name' => 'Usuario', 'last_name' => 'SIID'];
        $response = $this->request('post', '/api/v1/security/guest_token/', [
            'user' => $usuario,
            'resources' => [['type' => 'dashboard', 'id' => $dashboardUuid]],
            'rls' => $rls,
        ]);
        $token = $response['token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('No fue posible obtener el token de Superset.');
        }

        return $token;
    }

    public function tokenAcceso(): string
    {
        $url = (string) config('superset.url');

        if ($url === '' || ! filled(config('superset.username')) || ! filled(config('superset.password'))) {
            throw new RuntimeException('Superset no está configurado.');
        }

        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        try {
            $respuesta = Http::timeout(8)
                ->retry(2, 100, fn (mixed $excepcion): bool => $excepcion instanceof ConnectionException)
                ->acceptJson()
                ->withOptions(['cookies' => $this->cookieJar()])
                ->post(rtrim($url, '/').'/api/v1/security/login', [
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

        $this->accessToken = $token;

        return $token;
    }

    private function tokenCsrf(): string
    {
        if ($this->csrfToken !== null) {
            return $this->csrfToken;
        }

        $url = rtrim((string) config('superset.url'), '/');

        try {
            $response = Http::timeout(8)
                ->retry(2, 100, fn (mixed $exception): bool => $exception instanceof ConnectionException)
                ->acceptJson()
                ->withToken($this->tokenAcceso())
                ->withHeaders(['Referer' => $url])
                ->withOptions(['cookies' => $this->cookieJar()])
                ->get($url.'/api/v1/security/csrf_token/')
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('No fue posible obtener el token CSRF de Superset.', 0, $exception);
        }

        $token = $response->json('result');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Superset no devolvió un token CSRF válido.');
        }

        return $this->csrfToken = $token;
    }

    private function cookieJar(): CookieJar
    {
        return $this->cookies ??= new CookieJar;
    }
}
