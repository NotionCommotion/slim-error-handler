<?php
namespace Greenbean\SlimErrorHandler;
class SlimErrorHandler
{

    private $app, $container, $returnJson, $displayErrors, $excludedExceptions;

    public function __construct(\Slim\App $app, array $options=[]){
        $this->app=$app;
        $this->container=$app->getContainer();
        $this->returnJson=$options['returnJson']??true;
        $this->displayErrors=$options['displayErrors']??false;
        $this->excludedExceptions=$options['excludedExceptions']??[];
    }

    public function setHandlers(){
        $this->container['notFoundHandler'] = function ($c) {
            return function ($request, $response) use ($c) {
                syslog(LOG_ERR, 'Slim notFoundHandler from client '.$this->getClientIp());
                return $this->returnError(404, 'Page not found');
            };
        };
        $this->container['notAllowedHandler'] = function ($c) {
            return function ($request, $response, $methods) use ($c) {
                syslog(LOG_ERR, 'Slim notAllowedHandler from client '.$this->getClientIp());
                return $this->returnError(405, 'Method must be one of: ' . implode(', ', $methods))->withHeader('Allow', implode(', ', $methods));
            };
        };
        $this->container['errorHandler'] = function ($c) {
            return function ($request, $response, $e) use ($c) {
                $msg=in_array(get_class($e), $a)?$e->getMessage():'Slim errorHandler: '.$this->getExceptionError($e);
                syslog(LOG_ERR, $msg);
                return $this->returnError(500, $this->displayErrors?$msg:'Server Error (0)');
            };
        };
        $this->container['phpErrorHandler'] = function ($c) {
            return function ($request, $response, $e) use ($c) {
                $msg="PHP Error: {$e->getFile()} ({$e->getline()}): {$e->getMessage()} ({$e->getCode()})";
                syslog(LOG_ERR, 'Slim phpErrorHandler: '.$msg);
                return $this->returnError(500, $this->displayErrors?$msg:'Server Error (1)');
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

    private function returnError(int $status, string $msg, bool $returnJson=null) {
        return $returnJson??$this->returnJson
        ?$this->container['response']->withJson(['message'=>$msg],$status)
        :$this->container['response']->withStatus($status)->withHeader('Content-Type', 'text/html')->write($msg);
    }

    private function getExceptionError(\Exception $e) {
        return "{$e->getFile()} ({$e->getline()}): {$e->getMessage()} ({$e->getCode()})";
    }
}