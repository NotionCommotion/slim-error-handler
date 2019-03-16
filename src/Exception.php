<?php
namespace Greenbean\SlimErrorHandler;
class Exception extends \Exception
{
    //code is http status code and is required.
    private $json=false;
    public function __construct(string $message, int $code=400, $previous=null) {
        if(!is_string($message)) {
            $this->json=$message;
            $message='JSON error message';
        }
        parent::__construct($message, $code, $previous);
    }
    public function getData() {
        return $this->json;
    }
}