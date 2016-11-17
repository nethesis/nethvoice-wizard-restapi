<?php

class JsonResponseMiddleware
{
    /**
     * JsonResponse middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
        $response->withHeader(
            'Content-Type',
            'application/json'
        );
        $response = $next($request, $response);

        return $response;
    }
}
