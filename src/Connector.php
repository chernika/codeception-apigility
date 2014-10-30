<?php

namespace Codeception\Module;

use Symfony\Component\BrowserKit\Client;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;
use Zend\Http\Request as HttpRequest;
use Zend\Stdlib\Parameters;
use Zend\Uri\Http as HttpUri;
use Codeception\Lib\Connector\ZF2 as ZF2;

class Connector extends ZF2
{
    /**
     * @param Request $request
     *
     * @return Response
     * @throws \Exception
     */
    public function doRequest($request)
    {
        $zendRequest  = $this->application->getRequest();
        $zendResponse = $this->application->getResponse();

        $zendResponse->setStatusCode(200);

        $uri         = new HttpUri($request->getUri());
        $queryString = $uri->getQuery();
        $method      = strtoupper($request->getMethod());

        $zendRequest->setCookies(new Parameters($request->getCookies()));

        $server = $request->getServer();
        $zendHeaders = $zendRequest->getHeaders();

        if (!empty($server['HTTP_ACCEPT'])) {
            $zendHeaders->addHeaders(array('Accept' => $server['HTTP_ACCEPT']));
        }

        if (!empty($server['HTTP_AUTHORIZATION'])) {
            $zendHeaders->addHeaders(array('Authorization' => $server['HTTP_AUTHORIZATION']));
        }

        $_SERVER = $server;

        if ($queryString) {
            parse_str($queryString, $query);
            $zendRequest->setQuery(new Parameters($query));
        }

        if ($method == HttpRequest::METHOD_POST) {
            $post = $request->getParameters();
            $zendRequest->setPost(new Parameters($post));
        } elseif ($method == HttpRequest::METHOD_PUT) {
            $zendRequest->setContent($request->getContent());
        } elseif ($method == HttpRequest::METHOD_PATCH) {
            $post = $request->getParameters();
            $zendHeaders->addHeaders(array('Content-Type' => 'application/json'));
            $zendRequest->setContent(json_encode($post));
        }

        $zendRequest->setMethod($method);
        $zendRequest->setUri($uri);

        $em = \Codeception\Module\Doctrine2::$em;
        $sm = $this->application->getServiceManager();
        $sm->setAllowOverride(true)
            ->setService('Doctrine\ORM\EntityManager', $em)
            ->setAllowOverride(false);

        $this->application->run();

        $this->zendRequest = $zendRequest;

        $exception = $this->application->getMvcEvent()->getParam('exception');
        if ($exception instanceof \Exception) {
            throw $exception;
        }

        $zendResponse = $this->application->getResponse();
        $response = new Response(
            $zendResponse->getBody(),
            $zendResponse->getStatusCode(),
            $zendResponse->getHeaders()->toArray()
        );

        return $response;
    }
}
