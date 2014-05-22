<?php

namespace App\Controllers;

use Symfony\Component\HttpFoundation\RedirectResponse,
    Symfony\Component\Routing\Generator\UrlGenerator,
    Symfony\Component\Security\Core\SecurityContext,
    Symfony\Component\HttpFoundation\Request,
    App\TestInjection\TestInterface,
    Twig_Environment;

class HomeController
{
    public function indexAction(Twig_Environment $twig, TestInterface $t)
    {
        echo $t->writeMessage();
        return $twig->render('index.html.twig', array());
    }
    
    public function loginAction(Request $req, Twig_Environment $twig, SecurityContext $sc, UrlGenerator $urlgen)
    {
        if ($sc->isGranted('IS_AUTHENTICATED_FULLY'))
        {
            return new RedirectResponse($urlgen->generate('home'));
        }
        else
        {
            $session = $req->getSession();
            $errorConst = $sc::AUTHENTICATION_ERROR;
            $lastUsernameConst = $sc::LAST_USERNAME;

            return $twig->render('login.html.twig', array(
                'error' => ($session->has($errorConst)) ? $session->get($errorConst)->getMessage() : null,
                'last_username' => $session->get($lastUsernameConst),
            ));
        }
    }
}