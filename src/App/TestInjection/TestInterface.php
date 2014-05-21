<?php

namespace App\TestInjection;

interface TestInterface
{
    /**
     * Displays a message on the screen explaining how to use the Auryn DiC for aliasing concrete objects to interfaces
     *
     * This means you can write awesome SOLID code and typehint for interfaces anywhere for awesome, easy polymorphism
     *
     * @return string The message to be displayed
     */
    public function writeMessage();
}