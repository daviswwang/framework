<?php
/**
 * Created by PhpStorm.
 * User: fanxinyu
 * Date: 2021-01-26
 * Time: 08:33
 */

namespace Ex\Router;

use ArrayObject;
use JsonSerializable;
use Ex\Contracts\Support\Arrayable;
use Ex\Contracts\Support\Jsonable;
use Ex\Contracts\Support\Renderable;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class Response extends BaseResponse
{

    public $original;

    public function setContent($content)
    {
        $this->original = $content;

        // If the content is "JSONable" we will set the appropriate header and convert
        // the content to JSON. This is useful when returning something like models
        // from routes that will be automatically transformed to their JSON form.
        if ($this->shouldBeJson($content)) {
            $this->header('Content-Type', 'application/json');

            $content = $this->morphToJson($content);
        }

        // If this content implements the "Renderable" interface then we will call the
        // render method on the object so we will avoid any "__toString" exceptions
        // that might be thrown and have their errors obscured by PHP's handling.
        elseif ($content instanceof Renderable) {
            $content = $content->render();
        }

        parent::setContent($content);

        return $this;
    }

    /**
     * Morph the given content into JSON.
     *
     * @param  mixed $content
     * @return string
     */
    protected function morphToJson($content)
    {
        if ($content instanceof Jsonable) {
            return $content->toJson();
        } elseif ($content instanceof Arrayable) {
            return json_encode($content->toArray());
        }

        return json_encode($content);
    }


    protected function shouldBeJson($content)
    {
        return $content instanceof Arrayable ||
            $content instanceof Jsonable ||
            $content instanceof ArrayObject ||
            $content instanceof JsonSerializable ||
            is_array($content);
    }

}