<?php

namespace App;

use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface,
    Symfony\Component\HttpFoundation\Request,
    Auryn\Provider as Injector;

/**
 * Controller resolver decorator
 *
 * Decorates the default controller resolver to use the Auryn DiC
 *
 * Case: 'ClassName:methodName' - Use Auryn
 * Case: 'ClassName::methodName' - Use original controller resolver (pimple)
 */
class AurynControllerResolver implements ControllerResolverInterface
{
    const SERVICE_PATTERN = "/[A-Za-z0-9\._\-]+:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/";

    /**
     * @var \Symfony\Component\HttpKernel\Controller\ControllerResolverInterface
     */
    protected $resolver;

    /**
     * @var \Auryn\Provider
     */
    protected $injector;

    /**
     * @var \Silex\Application
     */
    protected $application;

    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @var array
     */
    protected $config;

    /**
     * @constructor
     *
     * @param ControllerResolverInterface $resolver Controller resolver to decorate
     * @param Injector                    $injector Auryn provider to use instead
     * @param Application                 $app      The Silex Application for pimple
     * @param Request                     $request  The Silex Application Request object
     * @param array                       $config   Application configuration values (mainly for DiC)
     */
    public function __construct(ControllerResolverInterface $resolver, Injector $injector, Application $app, Request $request, array $config)
    {
        $this->resolver = $resolver;
        $this->injector = $injector;
        $this->application = $app;
        $this->request = $request;
        $this->config = $config;
    }

    /**
     * Executes the controller / action with either Auryn or default resolver
     *
     * @param \Symfony\Component\HttpFoundation\Request $request Request object
     *
     * @return \Symfony\Component\HttpFoundation\Response Response object
     */
    public function getController(Request $request)
    {
        $controllerAction = $request->attributes->get('_controller', null);

        /** Fall back to symfony controller resolver **/
        if (!is_string($controllerAction) || !preg_match(static::SERVICE_PATTERN, $controllerAction))
        {
            return $this->resolver->getController($request);
        }

        list($controller, $action) = explode(':', sprintf('%s', $controllerAction), 2);

        foreach ($this->config['delay'] as $share)
        {
            $this->injector->share($this->{$share});
        }

        $this->injector->share($this->application['security']);

        $params = !empty($this->request->attributes->all()['_route_params']) ? $this->request->attributes->all()['_route_params'] : [];

        $args = [];

        array_walk($params, function($value, $key) use (&$args) {
            $args[sprintf(':%s', $key)] = $value;
        });

        /** Executed by the HTTP Kernel **/
        return function () use ($controller, $action, $args) {
            return $this->injector->execute([$this->injector->make($controller), $action], $args);
        };
    }

    /**
     * Original resolver delegation
     *
     * {@inheritdoc}
     */
    public function getArguments(Request $request, $controller)
    {
        return $this->resolver->getArguments($request, $controller);
    }
}