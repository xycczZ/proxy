<?php
declare(strict_types=1);

namespace Xycc\Proxy\Proxy;

use WeakMap;
use Xycc\Proxy\ClassLoader;
use Xycc\Proxy\Interceptor\MethodInterceptor;

class MethodProxy
{
    /**
     * @var array<string, static>
     */
    private static array $map = [];

    private WeakMap $instances;

    public static function invoke(object $obj, string $method, array $args)
    {
        if (!isset(self::$map[$obj::class])) {
            throw new \RuntimeException('callback not found: ' . $obj::class);
        }

        $instance = self::$map[$obj::class];
        return ($instance->callback)($obj, $method, $args, $instance);
    }

    public function invokeSuper(object $obj, string $method, array $args)
    {
        return $this->instances[$obj]->{$method}(...$args);
    }

    public static function proxy(string $class, MethodInterceptor|\Closure $callback): static
    {
        if (isset(self::$map[$class])) {
            throw new \RuntimeException('class has been proxied: ' . $class);
        }

        $self = new static($callback);

        return self::$map[$class] = $self;
    }

    protected function __construct(private MethodInterceptor|\Closure $callback)
    {
        $this->instances = new WeakMap();
    }

    public function createInstance(string $targetClass, array $args)
    {
        $targetInstance = new $targetClass(...$args);

        $parent = get_parent_class($targetClass);
        $proxyInstance = new $parent(...$args);

        $this->instances[$targetInstance] = $proxyInstance;

        return $targetInstance;
    }
}