<?php

require_once dirname(__DIR__) . '/vendor/autoload.php'; 

$parser = new Symfony\Component\Yaml\Yaml();
$global = $parser->parse('../config/global.yml');
$config = $parser->parse(sprintf('../config/%s.yml', $global['environment']));
$security = $parser->parse('../config/security.yml');
$routes = $parser->parse('../config/routes.yml');

$app = new App\Application(array_merge($global, $config, $security, $routes));

$app->run();