<?php
declare(strict_types=1);

namespace Xycc\Proxy;


use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Xycc\Proxy\Interceptor\MethodInterceptor;
use Xycc\Proxy\Proxy\MethodProxy;
use Xycc\Proxy\Visitor\NodeVisitor;

class Enhancer
{
    private string     $targetFQN;
    private Namespace_ $targetNamespace;
    private MethodProxy $methodProxy;

    public function __construct(
        private string $tempPath,
        private string $filePath,
        private MethodInterceptor|\Closure $callback,
    )
    {
        $this->parseClass();
    }

    public function create(...$args): object
    {
        return $this->methodProxy->createInstance($this->targetFQN, $args);
    }

    public function setTempPath(string $tempPath): Enhancer
    {
        $this->tempPath = $tempPath;
        return $this;
    }

    public function getTempPath(): string
    {
        return $this->tempPath;
    }

    protected function parseClass(): void
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $ast = $parser->parse(file_get_contents($this->filePath));
        $traverser = new NodeTraverser();
        $visitor = new NodeVisitor();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $this->validate($visitor);

        $this->targetFQN = $visitor->getTargetName();
        $this->targetNamespace = $visitor->getTargetNamespace();
        $this->createFile();
    }

    protected function validate(NodeVisitor $visitor): void
    {
        $proxyClass = $visitor->getProxyClass();
        if ($proxyClass === null) {
            throw new \RuntimeException(sprintf('file [%s] does not contains class', $this->filePath));
        }
        if ($proxyClass->isFinal() || $proxyClass->isAbstract()) {
            throw new \RuntimeException('could not generate proxy for `final` class or `abstract` class');
        }
        $targetClass = $visitor->getTargetNamespace();
        if ($targetClass === null) {
            throw new \RuntimeException('generate proxy class fail');
        }
    }

    private function createFile(): void
    {
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755);
        }

        $out = new Standard();
        $data = $out->prettyPrintFile([$this->targetNamespace]);
        $fileName = str_replace('\\', '_', $this->targetFQN);
        $filePath = $this->tempPath . '/' . $fileName . '.php';
        file_put_contents($filePath, $data);

        ClassLoader::addClassMap([$this->targetFQN => $filePath]);
        ClassLoader::register();

        $this->methodProxy = MethodProxy::proxy($this->targetFQN, $this->callback);
    }
}