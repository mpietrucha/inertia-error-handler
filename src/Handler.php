<?php

namespace Mpietrucha\Inertia\Error;

use Closure;
use Throwable;
use Mpietrucha\Support\Concerns\HasFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Handler
{
    use HasFactory;

    protected Response $response;

    protected array $beforeResolve = [];

    public function __construct(protected Request $request, protected Throwable $exception)
    {
        $this->response();
    }

    public function response(Response $response = new Response): self
    {
        $this->response = $response;

        return $this;
    }

    public function overrideException(string $from, $to, bool $debug = true): self
    {
        return $this->beforeResolve(function () use ($from, $to, $debug) {
            throw_if($this->exception instanceof $from && config('app.debug') !== $debug, $to);
        });
    }

    public function dontProcessRedirect(): self
    {
        return $this->beforeResolve(function () {
            if ($this->response->status() >= 300 && $this->response->status() < 400) {
                return $this->response;
            }
        });
    }

    public function transformRequest(array $transform): self
    {
        return $this->beforeResolve(function () use ($transform) {
            $this->request = $this->request->duplicate(...$transform);
        });
    }

    public function beforeResolve(Closure $before): self
    {
        $this->beforeResolve[] = $before;

        return $this;
    }

    public function render(string $component, array $props): Response
    {
        return collect($this->beforeResolve)->map(fn (Closure $before) => value($before))
            ->whereInstanceOf(Response::class)
            ->last(default: function () use ($component, $props) {
                return inertia()->render($component, $props)->toResponse($this->request)->setStatusCode($this->response->status());
            });
    }
}
