<?php
declare(strict_types=1);

namespace Xycc\Proxy\Visitor;

use PhpParser\Builder\Method;
use PhpParser\Builder\Namespace_;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use Xycc\Proxy\Proxy\MethodProxy;

class NodeVisitor extends NameResolver
{
    private const METHOD_PROXY = MethodProxy::class;
    private const METHOD_PROXY_INVOKE = 'invoke';
    private ?Class_               $proxyClass      = null;
    private ?Node\Stmt\Namespace_ $targetNamespace = null;
    private ?Class_               $targetClass     = null;

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->targetNamespace = $this->createClassInNs($node);
            return NodeTraverser::STOP_TRAVERSAL;
        } elseif ($node instanceof Class_) {
            $this->proxyClass = $node;
            if ($node->isFinal() || $node->isAbstract()) {
                return NodeTraverser::STOP_TRAVERSAL;
            }

            $this->targetClass = $this->createTargetClass();
            $this->targetNamespace = (new Namespace_(null))->addStmts([$this->targetClass])->getNode();
            return NodeTraverser::STOP_TRAVERSAL;
        }
    }

    protected function createClassInNs(Node\Stmt\Namespace_ $ns): Node\Stmt\Namespace_
    {
        $ns->stmts = array_map(function (Node $node) use ($ns) {
            if ($node instanceof Class_) {
                $this->proxyClass = $node;
                if ($node->isFinal() || $node->isAbstract()) {
                    return $node;
                }
                return $this->targetClass = $this->createTargetClass();
            }
            return $node;
        }, $ns->stmts);

        return $ns;
    }

    public function getProxyClass(): ?Class_
    {
        return $this->proxyClass;
    }

    public function getTargetNamespace(): ?Node\Stmt\Namespace_
    {
        return $this->targetNamespace;
    }

    protected function createTargetClass(): Class_
    {
        $builder = new \PhpParser\Builder\Class_($this->generateClassName());
        $builder = $builder->extend($this->proxyClass->name->name)
            ->addStmts($this->getRewriteStmts())
            ->setDocComment('// @generated generate by proxy. DONT UPDATE');

        if ($this->proxyClass->isReadonly()) {
            $builder = $builder->makeReadonly();
        }

        return $builder->getNode();
    }

    protected function getRewriteStmts(): array
    {
        return array_values(array_filter(array_map(function (Node $node) {
            if ($node instanceof Node\Stmt\ClassMethod) {
                if ($node->isPrivate() || $node->isStatic() || $node->isFinal() || $node->isMagic()) {
                    return null;
                }

                $method = (new Method($node->name->name))
                    ->addParams($node->params)
                    ->addStmt($this->makeMethodStmts($node));
                if ($node->returnType !== null) {
                    $method->setReturnType($node->returnType);
                }
                if ($node->isPublic()) {
                    $method->makePublic();
                } elseif ($node->isProtected()) {
                    $method->makeProtected();
                } elseif ($node->isPrivate()) {
                    $method->makePrivate();
                }
                return $method->getNode();
            }

            return null;
        }, $this->proxyClass->stmts)));
    }

    protected function makeMethodStmts(Node\Stmt\ClassMethod $node): Node\Stmt\Return_
    {
        $expr = new Node\Expr\StaticCall(
            new Node\Name('\\'.self::METHOD_PROXY),
            new Node\Name(self::METHOD_PROXY_INVOKE),
            [
                new Node\Arg(
                    new Node\Expr\Variable('this')
                ),
                new Node\Arg(
                    new Node\Scalar\String_($node->name->name)
                ),
                new Node\Arg(
                    new Node\Expr\Array_(
                        array_map(function (Node\Param $param) {
                            return new Node\Expr\ArrayItem(
                                $param->var,
                                unpack: $param->variadic
                            );
                        }, $node->getParams())
                    )
                ),
            ]
        );
        return new Node\Stmt\Return_($expr);
    }

    protected function generateClassName(): string
    {
        return sprintf('%s__PROXY__%s', $this->proxyClass->name, uniqid());
    }

    public function getTargetName(): string
    {
        if (!$this->targetNamespace->name) {
            return $this->targetClass->name->toString();
        }
        return $this->targetNamespace->name. '\\' . $this->targetClass->name;
    }
}