<?php

/**
 * Main application class that extends silex application to perform all the
 * bootstrapping and setting up. Should only need to be touched to add new
 * service providers and environmental settings.
 */

namespace App;

use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\SecurityServiceProvider;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\SessionServiceProvider;
use \Twig_SimpleFunction as TwigFunction;
use Entea\Twig\Extension\AssetExtension;
use Silex\Provider\TwigServiceProvider;
use App\User\UserProvider;

class Application extends \Silex\Application
{
    /**
     * @var string Configuration array from all yaml files from /config
     */
    private $config;
    
    /**
     * Class constructor
     * 
     * @param array $config Environment config array from merged yml files
     */
    public function __construct($config)
    {
        parent::__construct();
        
        $this->config = $config;
        
        $this->registerEnvironmentParams();
        $this->registerServiceProviders();
        $this->registerSecurityFirewalls();
        $this->registerRoutes();
    }
    
    /**
     * Set up environmental variables
     * 
     * If development environment, set xdebug to display all the things
     *
     * @return void
     */
    private function registerEnvironmentParams()
    {
        switch ($this->config['environment'])
        {
            case "dev":
                ini_set('display_errors', true);
                ini_set('xdebug.var_display_max_depth', 100);
                ini_set('xdebug.var_display_max_children', 100);
                ini_set('xdebug.var_display_max_data', 100);
                $this['debug'] = true;
            break;
            case "live":
                ini_set('display_errors', false);
            break;
            default:
            break;
        }
    }
    
    /**
     * Register Silex service providers
     * 
     * Twig doesn't like is_granted(), so a custom twig function is added here
     *
     * @return void
     */
    private function registerServiceProviders()
    {
        $app = $this;
        
        $app->register(new DoctrineServiceProvider(), array(
            'db.options' => $this->config['database']
        ));
        
        $app->register(new SessionServiceProvider());
        $app->register(new UrlGeneratorServiceProvider());
        $app->register(new TwigServiceProvider(), array(
            'twig.path'    => dirname(dirname(__DIR__)) . '/web/views',
            'twig.options' => array('debug' => (($this->config['environment'] === "dev") ? true : false))
        ));
        
        $app['twig']->addExtension(new AssetExtension($this));
        $app['twig']->addFunction(new TwigFunction('is_granted', function($role) use ($app) {
            return $app['security']->isGranted($role);
        }));
    }
    
    /**
     * Register firewalls from security.yml
     * 
     * Uses a custom UserProvider so the user can have whatever fields they
     * like in the db returned
     * 
     * @return void
     */
    private function registerSecurityFirewalls()
    {
        $firewalls   = $this->config['security']['firewalls'];
        $heirarchy   = $this->config['security']['heirarchy'];
        $accessRules = $this->config['security']['access_rules'];
        
        $firewalls['default']['users'] = $this->share(function($app) { 
            return new UserProvider($app['db']);
        });
    
        $this->register(new SecurityServiceProvider(), array(
            'security.firewalls'      => $firewalls,
            'security.role_hierarchy' => $heirarchy,
            'security.access_rules'   => $accessRules
        ));
    }
    
    /**
     * Register routes from routes.yml
     *
     * If a method key is not provided with the route, it is defaulted to 'GET'
     * Possible permutations involve GET, POST and GET|POST
     * 
     * @return void
     */
    private function registerRoutes()
    {        
        $routes =  $this->config['routes'];
        
        foreach ($routes as $name => $route)
        {
            $this->match($route['pattern'], sprintf('App\Controllers\%s', $route['defaults']['_controller']))->bind($name)->method(isset($route['method']) ? $route['method'] : 'GET');    
        }
    }
}