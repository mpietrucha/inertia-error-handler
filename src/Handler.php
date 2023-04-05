<?php

namespace Mpietrucha\Inertia\Error;

use Closure;
use Throwable;
use Exception;
use Mpietrucha\Nginx\Error\Interceptor;
use Mpietrucha\Support\Condition;
use Mpietrucha\Support\Concerns\HasFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Handler
{
    use HasFactory;

    protected bool $enabled = true;

    public function __construct(protected Throwable $exception, protected ?Request $request = null, protected ?Response $response = null)
    {
        $this->enabled(! config('app.debug'));
    }

    public function enabled(bool|Closure $mode = true): self
    {
        $this->enabled = value($mode, $this->enabled);

        return $this;
    }

    public function authenticated(): self
    {
        return $this->enabled(fn (bool $enabled) => $enabled && auth()->check());
    }

    public function request(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function response(Response $response): self
    {
        $this->response = $response;

        return $this;
    }

    public function swap(string $from, string $to): self
    {
        if ($this->exception instanceof $from) {
            throw new $to;
        }

        return $this;
    }

    public function render(string $component, array $props): Response
    {
        if (! $this->request || ! $this->response) {
            throw new Exception('Cannot process error without request/response.');
        }

        $this->enableNginxInterceptorIfPossible(
            $response = $this->respond()
        );

        return $response;
    }

    protected function respond(): Response
    {
        if (! $this->enabled) {
            return $this->response;
        }

        return inertia()->render($component, $props)->toResponse($this->request)->setStatusCode($this->response->status());
    }

    protected function enableNginxInterceptorIfPossible(Response $response): void
    {
        if (! class_exists(Interceptor::class)) {
            return;
        }

        Interceptor::create($response);
    }
}
