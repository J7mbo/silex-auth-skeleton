<?php

namespace App\Controllers;

use Symfony\Component\Security\Core\SecurityContext,
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
    
    public function loginAction(Request $req, Twig_Environment $twig, SecurityContext $sc)
    {
        $session = $req->getSession();
        $errorConst = $sc::AUTHENTICATION_ERROR;
        $lastUsernameConst = $sc::LAST_USERNAME;

        $error = ($session->has($errorConst)) ? $session->get($errorConst)->getMessage() : null;
        $lastUsername = $session->get($lastUsernameConst);

        return $twig->render('login.html.twig', array(
            'error' => $error,
            'last_username' => $lastUsername
        ));
    }
}