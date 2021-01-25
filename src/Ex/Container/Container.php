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
use ReflectionParameter;
use Ex\Contracts\Container\Container as ContainerContract;

class Container implements ArrayAccess, ContainerContract
{
    protected static $instance;

    protected $instances;

    protected $bindings;

    protected $alias = [];

    protected $resolvingCallbacks;

    protected $globalResolvingCallbacks;

    protected $globalAfterResolvingCallbacks;

    protected $afterResolvingCallbacks;

    protected $resolved;


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

    /**
     * 获取闭包
     * @param $abstract
     * @param $concrete
     * @return Closure
     * @author: fanxinyu
     */
    public function getClosure($abstract, $concrete)
    {
        return function ($container, $parameters = []) use ($abstract, $concrete) {
            if ($abstract == $concrete) //相等为闭包
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

    public function make($abstract, $arg = [])
    {
        return $this->resolve($abstract, $arg);
    }

    public function getAlias($abstract)
    {
        return $this->alias[$abstract] ?? '';
    }

    //解析容器
    protected function resolve($abstract, $arg)
    {
        //先看看有没有别名
        $abstract = $this->getAlias($abstract);

        $needsContextualBuild = !empty($arg);

        //有实例化好,在容器内的直接返回
        if (isset($this->instances[$abstract]) && !$needsContextualBuild) {
            return $this->instances[$abstract];
        }

        //尝试获取闭包数据
        $concrete = $this->getConcrete($abstract);

        //判断闭包内是否已经实例化

        //判断是否为闭包或者是否相等
        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete);
        } else {
            //递归解析容器
            $object = $this->make($concrete);
        }

        //将不需要构造参数的 实例放入 instances
        if ($this->isShared($abstract) && !$needsContextualBuild) {
            $this->instances[$abstract] = $object;
        }

        $this->fireResolvingCallbacks($abstract, $object);

        // Before returning, we will also set the resolved flag to "true" and pop off
        // the parameter overrides for this build. After those two things are done
        // we will be ready to return back the fully constructed class instance.
        $this->resolved[$abstract] = true;

        return $object;

    }

    /**
     * 启动所有正在解析的回调
     * @param $abstract
     * @param $object
     * @author: fanxinyu
     */
    protected function fireResolvingCallbacks($abstract, $object)
    {
        $this->fireCallbackArray($object, $this->globalResolvingCallbacks);

        $this->fireCallbackArray(
            $object, $this->getCallbacksForType($abstract, $object, $this->resolvingCallbacks)
        );

        $this->fireCallbackArray($object, $this->globalAfterResolvingCallbacks);

        $this->fireCallbackArray(
            $object, $this->getCallbacksForType($abstract, $object, $this->afterResolvingCallbacks)
        );

    }

    protected function getCallbacksForType($abstract, $object, array $callbacksPerType)
    {
        $results = [];

        foreach ($callbacksPerType as $type => $callbacks) {
            if ($type === $abstract || $object instanceof $type) {
                $results = array_merge($results, $callbacks);
            }
        }

        return $results;
    }


    protected function fireCallbackArray($object, $callbacks)
    {
        foreach ($callbacks as $v) {
            $v($object, $this);
        }
    }


    /**
     * 判断是否为共享类
     * @param $abstract
     * @return bool
     * @author: fanxinyu
     */
    public function isShared($abstract)
    {
        return isset($this->instances[$abstract]) ||
            (isset($this->bindings[$abstract]['shared']) &&
                $this->bindings[$abstract]['shared'] === true);
    }

    protected function getExtenders($abstract)
    {
        $abstract = $this->getAlias($abstract);

//        if (isset($this->extenders[$abstract])) {
//            return $this->extenders[$abstract];
//        }

        return [];
    }

    /**
     * 判断是否为闭包
     * @param $concrete
     * @param $abstract
     * @return bool
     * @author: fanxinyu
     */
    protected function isBuildable($concrete, $abstract)
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * 拿到容器内的闭包实例
     * @param $abstract
     * @return mixed
     * @author: fanxinyu
     */
    protected function getConcrete($abstract)
    {

        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }


    /**
     * 解析依赖项
     * @param array $dependencies
     * @return array
     * @author: fanxinyu
     */
    protected function resolveDependencies(array $dependencies)
    {
        $results = [];

        //循环参数
        foreach ($dependencies as $dependency) {

            //判断是否在 with (容器)内 如果在,就直接拿,放入数组中
//            if ($this->hasParameterOverride($dependency)) {
//                $results[] = $this->getParameterOverride($dependency);
//
//                continue;
//            }

            $results[] = is_null($dependency->getClass())
                ? $this->resolvePrimitive($dependency)
                : $this->resolveClass($dependency); //循环调用make方法
        }

        return $results;
    }

    protected function resolveClass(ReflectionParameter $parameter)
    {
        return $this->make($parameter->getClass()->name);   //获取类并返回类名,然后make解析
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