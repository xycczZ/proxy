<?php

namespace Xycc\Proxy\Tests;

use PhpParser\Node\Stmt\Class_;
use PHPUnit\Framework\TestCase;
use Xycc\Proxy\Enhancer;
use Xycc\Proxy\Interceptor\MethodInterceptor;
use Xycc\Proxy\Proxy\MethodProxy;
use Xycc\Proxy\Visitor\NodeVisitor;

class ProxyTest extends TestCase
{
    private const RUNTIME_PATH = __DIR__ . '/../runtime';

    public static function setUpBeforeClass(): void
    {
        if (!is_dir(self::RUNTIME_PATH)) {
            mkdir(self::RUNTIME_PATH, 0755);
        }
    }

    public function testSubclassGenerated()
    {
        $enhancer = new Enhancer(self::RUNTIME_PATH, __DIR__ . '/../src/Visitor/NodeVisitor.php', fn () => 1);
        $target = $enhancer->create();
        $this->assertInstanceOf(NodeVisitor::class, $target, 'generated class is not a subclass');
        $this->assertNotEquals(NodeVisitor::class, $target::class);
    }

    public function testProxy()
    {
        $enhancer = new Enhancer(self::RUNTIME_PATH, __DIR__ . '/../src/Visitor/NodeVisitor.php', new class implements MethodInterceptor {
            public function __invoke(object $obj, string $method, array $args, MethodProxy $methodProxy)
            {
                if ($method === 'getProxyClass') {
                    return new Class_('forTest');
                }
                return $methodProxy->invokeSuper($obj, $method, $args);
            }
        });

        /**@var NodeVisitor $obj*/
        $obj = $enhancer->create();
        $proxyResult = $obj->getProxyClass();

        $this->assertEquals('forTest', $proxyResult->name->name);

        $obj2 = $enhancer->create();
        $this->assertTrue($obj !== $obj2);
    }
}