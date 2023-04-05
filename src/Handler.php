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
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler
{
    use HasFactory;

    protected bool $enabled = true;

    public function __construct(protected Throwable $exception, protected ?Request $request = null, protected ?Response $response = null)
    {
        $this->enabled(function () {
            if ($this->exception instanceof HttpException) {
                return true;
            }

            return ! config('app.debug');
        });
    }

    public function enabled(bool|Closure $mode = true): self
    {
        $this->enabled = value($mode, $this->enabled);

        return $this;
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

    public function render(string $component, array $props): Response
    {
        if (! $this->request || ! $this->response) {
            throw new Exception('Cannot process error without request/response.');
        }

        $this->enableNginxInterceptorIfPossible(
            $response = $this->resolve($component, $props)
        );

        return $response;
    }

    protected function resolve(string $component, array $props): Response
    {
        if ($this->response->status() < 400) {
            return $this->response;
        }

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
