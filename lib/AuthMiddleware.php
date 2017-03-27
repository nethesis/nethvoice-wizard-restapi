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
        if ($request->isOptions()) {
            $response = $next($request, $response);
        }
        else if ($request->getUri()->getPath() != 'testauth' && (!$request->hasHeader('Secretkey') || !$request->hasHeader('User'))) {
            return $response->withJson(['error' => 'Forbidden: no credentials'], 403);
	} else {
	    $dbh = FreePBX::Database();
            $given_user = $request->getHeaderLine('User');
            $given_secret = $request->getHeaderLine('Secretkey');

	    $stmt = $dbh->prepare("SELECT * FROM ampusers WHERE sections='*' AND username = ?");
	    $stmt->execute(array($given_user));
	    $user = $stmt->fetchAll();
            $password_sha1 = $user[0]['password_sha1'];
            $username = $user[0]['username'];

            # check the user is valid and is an admin (sections = *)
            if ($request->getUri()->getPath() != 'testauth' && !$username ) {
                return $response->withJson(['error' => 'Forbidden: invalid user'], 403);
            }
            $hash = sha1($username . $password_sha1 . $this->secret);
            if ($request->getUri()->getPath() != 'testauth' && $given_secret != $hash) {
                $response = $response->withJson(['error' => 'Forbidden: wrong secret key'], 403);
            } else {
                $response = $next($request, $response);
            }
        }

        return $response;
    }
}
