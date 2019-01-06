<?php
namespace Greenbean\SlimErrorHandler\Exceptions;
class AbstractException extends \Exception
{
    public function getStatusCode():int;
    abstract public function getConvertedMessage(string $protocol):string;
}