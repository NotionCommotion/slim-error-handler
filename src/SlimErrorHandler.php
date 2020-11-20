<?php
namespace Greenbean\SlimErrorHandler;
class SlimErrorHandler
{

    private $app, $container, $allowedExceptions, $contentType, $debug;
    //allowedExceptions is an array of closure.

    public function __construct(\Slim\App $app, array $allowedExceptions=[], string $contentType='application/json;charset=utf-8', $debug=false){
        $this->app=$app;
        $this->container=$app->getContainer();
        $this->allowedExceptions=$allowedExceptions;
        $this->contentType=$contentType;
        $this->debug=$debug;    //$debug can be true/false or 1/0
    }

    public function setHandlers(){
        $this->container['notFoundHandler'] = function ($c) {
            return function ($request, $response) use ($c) {
                if ($this->debug) $this->logError($request, 'Slim notFoundHandler from client');
                $msg='Page not found';
                return $this->returnError($msg, 404, $response);
            };
        };
        $this->container['customErrorHandler'] = function ($c) {
            return function ($request, $response, string $msg, int $httpCode=400) use ($c) {
                //if ($this->debug) $this->logError($request, 'Slim customErrorHandler from client');
                return $this->returnError($msg, $httpCode, $response);
            };
        };
        $this->container['notAllowedHandler'] = function ($c) {
            return function ($request, $response, $methods) use ($c) {
                if ($this->debug) $this->logError($request, 'Slim notAllowedHandler from client');
                $msg='Method must be one of: ' . implode(', ', $methods);
                return $this->returnError($msg, 405, $response)->withHeader('Allow', implode(', ', $methods));
            };
        };
        $this->container['invalidUserIdHandler'] = function ($c) {
            return function ($request, $response) use ($c) {
                $msg = $request->hasHeader('X-GreenBean-UserId')?'Invalid User ID':'Missing User ID';
                if ($this->debug) $this->logError($request, 'Slim invalidUserIdHandler from client: '.$msg);
                return $this->returnError($msg, 403, $response);
            };
        };
        $this->container['errorHandler'] = function ($c) {
            return function ($request, $response, $e) use ($c) {
                if($this->debug || true) syslog(LOG_ERR, 'Slim errorHandler: '.$this->getExceptionError($e));
                if($this->debug || $e instanceof Exception) {
                    return $this->returnError($e->getMessage(), 400, $response);
                }
                elseif(isset($this->allowedExceptions[get_class($e)])) {
                    return $this->returnError($this->allowedExceptions[get_class($e)]($c, $e), 400, $response);
                }
                else {
                    return $this->returnError('Server Error (0)', 500, $response);
                }
            };
        };
        $this->container['phpErrorHandler'] = function ($c) {
            return function ($request, $response, $e) use ($c) {
                if ($this->debug || true) {
                    syslog(LOG_ERR, 'Slim phpErrorHandler: '.$this->getExceptionError($e));
                    return $this->returnError($e->getMessage(), 500, $response);
                }
                else {
                    return $this->returnError('Server Error (1)', 500, $response);
                }
            };
        };
        return $this;
    }

    private function instanceOf(\Exception $e, array $exceptions): bool {
        foreach ($exceptions as $exception){
            if($e instanceof $exception) return true;
        }
        return false;
    }

    private function getClientIp() {
        $rIp=$_SERVER['REMOTE_ADDR']??'None';
        $fIp=$_SERVER['HTTP_X_FORWARDED_FOR']??'None';
        return "REMOTE_ADDR: $rIp HTTP_X_FORWARDED_FOR: $fIp";
    }

    private function logError($request, $text) {
        $uri=(string)$request->getUri();
        syslog(LOG_ERR, "$text: method: {$request->getMethod()} uri: $uri remote IP: {$this->getClientIp()}");
    }

    private function returnError(string $msg, int $statusCode, $response) {
        return strpos($this->contentType, 'application/json') === 0
        ?$response->withJson(['message'=>$msg], $statusCode)
        :$response->withStatus($statusCode)->withHeader('Content-Type', $this->contentType)->write($msg);
    }

    private function getExceptionError($e) {    //$e will be \Error or \Greenbean\SlimErrorHandler\Exception
        return "{$e->getFile()} ({$e->getLine()}): {$e->getMessage()} ({$e->getCode()}) class: ".get_class($e);
    }
}