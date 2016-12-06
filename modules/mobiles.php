<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/mobiles', function (Request $request, Response $response, $args) {
    try {
        $dbh = FreePBX::Database();
        $sql = "SELECT * FROM rest_mobiles";
        $mobiles = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);
        return $response->withJson($mobiles,200);
    } catch (Exception $e){
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()),500);
    }
});

$app->get('/mobiles/{username}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $username = $route->getArgument('username');
        $dbh = FreePBX::Database();
        $sql = "SELECT `mobile` FROM `rest_mobiles` WHERE username='$username'";
        $mobile = $dbh->sql($sql,"getOne",\PDO::FETCH_ASSOC);
	if ($mobile==false){
            return $response->withJson($mobile,404);
        }
        return $response->withJson($mobile,200);
    } catch (Exception $e){
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()),500);
    }
});

$app->post('/mobiles', function (Request $request, Response $response, $args) {
    try {
        $params = $request->getParsedBody();
        $dbh = FreePBX::Database();
        $sql = "DELETE FROM `rest_mobiles` WHERE `username` = '".$params['username']."'";
        $dbh->sql($sql);
        $sql = "INSERT INTO `rest_mobiles` (`username`,`mobile`) VALUES (?,?)";
        $stmt = $dbh->prepare($sql);
        $mobile = preg_replace('/^\+/','00',$params['mobile']);
        $mobile = preg_replace('/[^0-9]/','',$mobile);
        if ($res = $stmt->execute(array($params['username'],$mobile))) {
            return $response->withJson(array("status"=>true),200);
        } else {
            return $response->withJson(array("status"=>"Unknown error"),500);
        }
    } catch (Exception $e){
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()),500);
    }
});

