<?php
namespace Greenbean\SlimErrorHandler;
class SlimErrorHandler
{

    private $app, $container, $debug;

    public function __construct(\Slim\App $app, string $contentType='application/json;charset=utf-8', int $debug=false){
        $this->app=$app;
        $this->container=$app->getContainer();
        $this->contentType=$contentType;
        $this->debug=$debug;
    }

    public function setHandlers(){
        $this->container['notFoundHandler'] = function ($c) {
            return function ($request, $response) use ($c) {
                syslog(LOG_ERR, 'Slim notFoundHandler from client '.$this->getClientIp());
                $msg='Page not found';
                return $this->returnError($msg, 404, $request->isXhr());
            };
        };
        $this->container['notAllowedHandler'] = function ($c) {
            return function ($request, $response, $methods) use ($c) {
                syslog(LOG_ERR, 'Slim notAllowedHandler from client '.$this->getClientIp());
                $msg='Method must be one of: ' . implode(', ', $methods);
                return $this->returnError($msg, 405, $request->isXhr())->withHeader('Allow', implode(', ', $methods));
            };
        };
        $this->container['errorHandler'] = function ($c) {
            return function ($request, $response, $e) use ($c) {
                $msg='Slim errorHandler: '.$this->getExceptionError($e);
                syslog(LOG_ERR, $msg);
                if($e instanceof AbstractException) {
                    $msg=$this->debug?$msg:$e->getMessage();
                    return $this->returnError($msg, $e->getStatusCode(), $request->isXhr());
                }
                else {
                    $msg=$this->debug?$msg:'Server Error (0)';
                    return $this->returnError($msg, 500, $request->isXhr());
                }
            };
        };
        $this->container['phpErrorHandler'] = function ($c) {
            return function ($request, $response, $e) use ($c) {
                $msg="PHP Error: {$e->getFile()} ({$e->getline()}): {$e->getMessage()} ({$e->getCode()})";
                syslog(LOG_ERR, 'Slim phpErrorHandler: '.$msg);
                $msg=$this->debug?$msg:'Server Error (1)';
                return $this->returnError($msg, 500, $request->isXhr());
            };
        };
        return $this;
    }

    public function enableCor(bool $enabledViaApache, string $serverUrl=null) {
        if(!$enabledViaApache) {
            if(!$serverUrl) throw new \Exception('Url with prototol (i.e. http://mysite) must be supplied');
            $this->app->options('/{routes:.+}', function ($request, $response, $args) {
                return $response;
            });

            $this->app->add(function ($req, $res, $next) {
                $response = $next($req, $res);
                return $response
                ->withHeader('Access-Control-Allow-Origin',$serverUrl)
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
            });
        }

        // Catch-all route to serve a 404 Not Found page if none of the routes match
        // NOTE: make sure this route is defined last
        $this->app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function($req, $res) {
            $handler = $this->notFoundHandler; // handle using the default Slim page not found handler
            return $handler($req, $res);
        });
        return $this;
    }

    private function getClientIp() {
        $rIp=$_SERVER['REMOTE_ADDR']??'None';
        $fIp=$_SERVER['HTTP_X_FORWARDED_FOR']??'None';
        return "REMOTE_ADDR: $rIp HTTP_X_FORWARDED_FOR: $fIp";
    }

    private function returnError(string $msg, int $statusCode, int $isXhr) {
        //Future.  Check if $this->contentType='application/json;charset=utf-8' and do as applicable.
        return $isXhr
        ?$this->container['response']->withJson(['message'=>$msg], $statusCode)
        :$this->container['response']->withStatus($statusCode)->withHeader('Content-Type', 'text/html')->write($msg);
    }

    private function getExceptionError(\Exception $e) {
        return "{$e->getFile()} ({$e->getline()}): {$e->getMessage()} ({$e->getCode()})";
    }
}