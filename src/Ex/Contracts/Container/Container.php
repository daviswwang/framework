<?php
/**
 * Created by PhpStorm.
 * User: fanxinyu
 * Date: 2021-01-20
 * Time: 14:48
 */

namespace Ex\Contracts\Container;

use Closure;
use Psr\Container\ContainerInterface;

interface Container extends ContainerInterface
{
    public function bound($abstract);

    public function bind($abstract, $concrete = null, $shared = false);

    public function singleton($abstract, $concrete = null);

    public function extend($abstract, Closure $closure);

    public function instance($abstract, $instance);


}