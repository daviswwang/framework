<?php
/**
 * Created by PhpStorm.
 * User: fanxinyu
 * Date: 2021-01-19
 * Time: 18:10
 */

namespace Ex\Container;

use Closure;
use ArrayAccess;
use Ex\Exception\Container\ContainerException;
use ReflectionClass;
use Ex\Contracts\Container\Container as ContainerContract;

class Container implements ArrayAccess, ContainerContract
{
    protected static $instance;

    protected $instances;

    protected $bindings;


    public function get($id)
    {

    }

    public function bind($abstract, $concrete = null, $shared = false)
    {
        $this->dropStaleInstances($abstract);

        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');

        if ($this->resolved($abstract)) {
            $this->rebound($abstract);
        }
    }

    public function getClosure($abstract, $concrete)
    {
        return function ($container, $parameters = []) use ($abstract, $concrete) {
            if ($abstract == $concrete)
                return $container->build($concrete);
            return $container->make($abstract, $parameters);
        };

    }

    public function build($concrete)
    {
        if ($concrete instanceof Closure)
            return $concrete($this);


        //如果是类名,反射类获取
        $ref = new ReflectionClass($concrete);

        //判断是否可实例化
        if (!$ref->isInstantiable()) {
            return $this->notInstantiable($concrete);
        }

        //获取实例化是否用到构造函数
        $constructor = $ref->getConstructor();

        if (!$concrete) return new $concrete;

        //如果有构造函数,递归实例化注入的类
        $dependencies = $constructor->getParameters();

        //递归实例化
        $instances = $this->resolveDependencies(
            $dependencies
        );

        //带实例化好的参数对象来 生成并注入新对象
        return $ref->newInstanceArgs($instances);
    }

    protected function resolveDependencies(array $dependencies)
    {
        $results = [];

        //循环参数
        foreach ($dependencies as $dependency) {
            if ($this->hasParameterOverride($dependency)) {
                $results[] = $this->getParameterOverride($dependency);

                continue;
            }

            $results[] = is_null($dependency->getClass())
                ? $this->resolvePrimitive($dependency)
                : $this->resolveClass($dependency); //循环调用make方法
        }

        return $results;
    }

    protected function notInstantiable($concrete)
    {
        $message = "Target [$concrete] is not instantiable.";
        throw new ContainerException($message);
    }

    protected function dropStaleInstances($abstract)
    {
        unset($this->instances[$abstract]);
    }

    public function bound($abstract)
    {
        // TODO: Implement bound() method.
    }

    public function extend($abstract, Closure $closure)
    {
        // TODO: Implement extend() method.
    }

    public function instance($abstract, $instance)
    {
        // TODO: Implement instance() method.
    }

    public function singleton($abstract, $concrete = null)
    {
        // TODO: Implement singleton() method.
    }

    public function has($id)
    {
        // TODO: Implement has() method.
    }

    public function offsetSet($offset, $value)
    {
        // TODO: Implement offsetSet() method.
    }

    public function offsetGet($offset)
    {
        // TODO: Implement offsetGet() method.
    }

    public function offsetExists($offset)
    {
        // TODO: Implement offsetExists() method.
    }

    public function offsetUnset($offset)
    {
        // TODO: Implement offsetUnset() method.
    }
}