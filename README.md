```php
// Aa.php
class Aa {
    public function __construct(private string $a) {
        
    }
    
    public function getA() {
        return $this->a . static::class;
    }
}

$enhancer = new \Xycc\Proxy\Enhancer(
    $tempPath, // directory to place the generated files
    __DIR__ . '/Aa.php', // file path of the class to proxy
    new class implements \Xycc\Proxy\Interceptor\MethodInterceptor {
        public function __invoke(object $obj,string $method,array $args,\Xycc\Proxy\Proxy\MethodProxy $methodProxy){
            // before ...
            if ($method === 'getA') {
                $result = $methodProxy->invokeSuper($obj, $method, $args); // call original method
                return 'proxy' . $result;
            }
            
            return $methodProxy->invokeSuper($obj, $method, $args);
        }
    }
);

$proxy = $enhancer->create('~~'/*constructor args*/);
$proxyResult = $proxy->getA(); // proxy~~Aa
```