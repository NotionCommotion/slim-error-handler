<?php
namespace Greenbean\SlimErrorHandler\Exceptions;
class ValidationException extends AbstractException
{
    public function getStatusCode(){
        return 422;
    }
}