<?php
namespace App\Exceptions;

use Dingo\Api\Exception;

class ProjectValidationHttpException extends \Dingo\Api\Exception\ResourceException
{
    /**
     * Create a new validation HTTP exception instance.
     *
     * @param \Illuminate\Support\MessageBag|array $errors
     * @param \Exception                           $previous
     * @param array                                $headers
     * @param int                                  $code
     *
     * @return void
     */
    public function __construct($errors = null, Exception $previous = null, $headers = [], $code = 0)
    {
        $messages = null;

        if($errors)
            $messages = implode(" ",array_map(function($a){ return implode(", ",$a);},$errors->toArray()));

        parent::__construct($messages, $errors, $previous, $headers, $code);
    }
}