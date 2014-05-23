<?php

/**
 * Main application class that extends silex application to perform all the
 * bootstrapping and setting up. Should only need to be touched to add new
 * service providers and environmental settings.
 */
namespace App;

use Dflydev\Silex\Provider\DoctrineOrm\DoctrineOrmServiceProvider,
    Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder,
    Symfony\Component\Security\Core\SecurityContext,
    Doctrine\ORM\Mapping\Driver\AnnotationDriver,
    Doctrine\Common\Annotations\AnnotationReader,
    Silex\Provider\UrlGeneratorServiceProvider,
    Silex\Provider\SecurityServiceProvider,
    Silex\Provider\DoctrineServiceProvider,
    Silex\Provider\SessionServiceProvider,
    \Twig_SimpleFunction as TwigFunction,
    Entea\Twig\Extension\AssetExtension,
    Silex\Provider\TwigServiceProvider,
    Doctrine\Common\Cache\ArrayCache,
    Doctrine\Common\Cache\ApcCache,
    Auryn\ReflectionPool,
    Auryn\Provider;

class Application extends \Silex\Application
{
    /**
     * @var string Configuration array from all yaml files from /config
     */
    private $config;

    /**
     * @var \Auryn\Provider Auryn DiC Provider / Injector
     */
    protected $provider;
    
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
        $this->resolveAuryn();
        $this->registerRoutes();
    }
    
    /**
     * Set up environmental variables
     * 
     * If development environment, set xdebug to display all the things
     *
     * @throws \RuntimeException When invalid environment given
     */
    private function registerEnvironmentParams()
    {
        $environment = $this->config['environment'];

        switch ($environment)
        {
            case "dev":

                if (extension_loaded('xdebug'))
                {
                    ini_set('display_errors', true);
                    ini_set('xdebug.var_display_max_depth', 100);
                    ini_set('xdebug.var_display_max_children', 100);
                    ini_set('xdebug.var_display_max_data', 100);
                }

                $this['debug'] = true;
            break;
            case "live":
                ini_set('display_errors', false);
            break;
            default:
                throw new \RuntimeException(sprintf("Environment should be 'dev' or 'live', '%s' given", $environment));
            break;
        }
    }
    
    /**
     * Register Silex service providers
     * 
     * Twig doesn't like is_granted(), so a custom twig function is added here
     */
    private function registerServiceProviders()
    {
        $app = $this;
        
        $app->register(new DoctrineServiceProvider(), [
            'db.options' => $this->config['database']
        ]);

        $app->register(new DoctrineOrmServiceProvider, [
            'orm.proxies_dir'           => dirname(dirname(__DIR__)) . '/cache/doctrine',
            'orm.proxies_namespace'     => 'cache\doctrine',
            'orm.cache'                 => !$app['debug'] && extension_loaded('apc') ? new ApcCache() : new ArrayCache(),
            'orm.auto_generate_proxies' => true,
            'orm.em.options' => [
                'mappings'  => [
                    [
                        'type'      => 'annotation',
                        'path'      => __DIR__ .'/Model/Entity',
                        'namespace' => 'App\Model\Entity'
                    ]
                ]
            ]
        ]);

        /** @var \Doctrine\DBAL\Connection $orm */
        $orm = $app['orm.em'];

        /** @var \Doctrine\ORM\Configuration $ormConfig */
        $ormConfig = $orm->getConfiguration();

        $ormConfig->setMetadataDriverImpl(new AnnotationDriver(new AnnotationReader(), [
            __DIR__ . '/Model/Entity'
        ]));
        
        $app->register(new SessionServiceProvider());
        $app->register(new UrlGeneratorServiceProvider());
        $app->register(new TwigServiceProvider(), [
            'twig.path'    => dirname(dirname(__DIR__)) . '/web/views',
            'twig.options' => ['debug' => (($this->config['environment'] === "dev") ? true : false)]
        ]);

        /** @var \Twig_Environment $twig */
        $twig = $app['twig'];

        $twig->addExtension(new AssetExtension($this));
        $twig->addFunction(new TwigFunction('is_granted', function($role) use ($app) {
            /** @var SecurityContext $security */
            $security = $app['security'];
            return $security->isGranted($role);
        }));
    }
    
    /**
     * Register firewalls from security.yml
     * 
     * Uses a custom UserProvider so the user can have whatever fields they like in the db returned
     *
     * Also uses a custom encoder for security / authentication using the password_compat library
     */
    private function registerSecurityFirewalls()
    {
        $firewalls   = $this->config['security']['firewalls'];
        $heirarchy   = $this->config['security']['heirarchy'];
        $accessRules = $this->config['security']['access_rules'];

        $firewalls['default']['users'] = $this->share(function($app) {
            /** @var \Doctrine\ORM\EntityManager $orm */
            $orm = $app['orm.em'];
            return $orm->getRepository('\App\Model\Entity\User');
        });
    
        $this->register(new SecurityServiceProvider(), [
            'security.firewalls'      => $firewalls,
            'security.role_hierarchy' => $heirarchy,
            'security.access_rules'   => $accessRules
        ]);

        $app = $this;
        $app['security.encoder.digest'] = $app->share(function() {
            return new BCryptPasswordEncoder(10);
        });
    }

    /**
     * Resolves Auryn controller dependencies after everything else has been set up correctly
     *
     * @see <https://github.com/rdlowrey/Auryn>
     */
    public function resolveAuryn()
    {
        $app = $this;
        $config = $this->config['auryn'];

        $provider = new Provider(new ReflectionPool);

        foreach ($config as $key => $values)
        {
            switch ($key)
            {
                case ($key === 'define'):
                    if (!is_null($values))
                    {
                        array_walk($values, function($definition, $key) use ($provider) {
                            $provider->define($key, $definition);
                        });
                    }
                break;
                case ($key === 'delegate'):
                    if (!is_null($values))
                    {
                        array_walk($values, function($delegate, $object) use ($provider) {
                            $provider->delegate($object, $delegate);
                        });
                    }
                break;
                case ($key === 'alias'):
                    if (!is_null($values))
                    {
                        array_walk($values, function ($concrete, $interface) use ($provider) {
                            $provider->alias($interface, $concrete);
                        });
                    }
                break;
                case ($key === 'share'):
                    if (!is_null($values))
                    {
                        array_walk($values, function($share) use ($provider, $app) {
                            $provider->share($app[$share]);
                        });
                    }
                break;
            }
        }

        $repositoryFiles = glob(__DIR__ . "/Model/Repository/*Repository.php");

        array_walk($repositoryFiles, function($file) use ($app, $provider) {
            /** @var \Doctrine\ORM\EntityManager $orm */
            $orm = $app['orm.em'];

            $repositoryName = str_replace(['Repository', '.php'], '', basename($file));
            $provider->share($orm->getRepository(sprintf('\App\Model\Entity\%s', $repositoryName)));
        });

        $app['resolver'] = $app->share($app->extend('resolver', function ($resolver, $app) use ($provider, $config) {
            return new AurynControllerResolver($resolver, $provider, $app, $app['request'], $config);
        }));
    }
    
    /**
     * Register routes from routes.yml
     *
     * If a method key is not provided with the route, it is defaulted to 'GET'
     * Possible permutations involve GET, POST and GET|POST
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