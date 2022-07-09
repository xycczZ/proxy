<?php
declare(strict_types=1);

namespace Xycc\Proxy\Interceptor;

use Xycc\Proxy\Proxy\MethodProxy;

interface MethodInterceptor
{
    public function __invoke(object $obj, string $method, array $args, MethodProxy $methodProxy);
}