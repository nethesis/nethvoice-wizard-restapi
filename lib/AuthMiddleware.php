<?php

class AuthMiddleware
{
    private $secret = NULL;

    public function __construct($secret) {
        $this->secret = $secret;
    }

    /**
     * Authentication middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
        if (!$request->hasHeader('Secretkey')) {
            $response = $response->withStatus(403);
            $response->getBody()->write(json_encode(array('result' => 'Forbidden: no secret key given')));
        } else {
            $given_secret = $request->getHeaderLine('Secretkey');
            if ($given_secret != $this->secret) {
                $response = $response->withStatus(403);
                $response->getBody()->write(json_encode(array('result' => 'Forbidden: wrong secret key')));
            } else {
                $response = $next($request, $response);
            }
        }

        return $response;
    }

}
