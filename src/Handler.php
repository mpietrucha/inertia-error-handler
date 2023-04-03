<?php

namespace Mpietrucha\Inertia\Error;

use Closure;
use Throwable;
use Mpietrucha\Nginx\Error\Interceptor;
use Mpietrucha\Support\Condition;
use Mpietrucha\Support\Concerns\HasFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Handler
{
    use HasFactory;

    protected Request $request;

    protected ?Response $response = null;

    protected bool $enabled = true;

    protected bool $hasReplacedException = false;

    protected bool $redirects = false;

    public function __construct(protected Throwable $exception)
    {
        $this->enabled(! config('app.debug'));
    }

    public function response(Response $response): self
    {
        $this->response = $response;

        return $this;
    }

    public function request(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function redirects(bool $mode = true): self
    {
        $this->redirects = $mode;

        return $this;
    }

    public function enabled(bool|Closure $mode = true): self
    {
        $this->enabled = value($mode, $this->enabled);

        return $this;
    }

    public function replaceException(string $from, string $to): self
    {
        $this->exception = Condition::create($this->exception)
            ->add(fn () => new $to, $this->exception instanceof $from)
            ->resolve();

        $this->hasReplacedException = $this->exception instanceof $to;

        return $this;
    }

    public function render(string $component, array $props): Response
    {
        if (! $this->enabled && ! $this->hasReplacedException) {
            return $this->response;
        }

        if ($this->redirects && $this->isRedirectResponse()) {
            return $this->response;
        }

        $response = inertia()->render($component, $props)->toResponse($this->request)->setStatusCode($this->response->status());

        if (class_exists(Interceptor::class)) {
            Interceptor::enable($response);
        }

        return $response;
    }

    protected function isRedirectResponse(): bool
    {
        return $this->response->status() >= 300 && $this->response->status() < 400;
    }
}
