<?php

namespace App\TestInjection;

class TestClass implements TestInterface
{
    /**
     * {@inheritdoc}
     */
    public function writeMessage()
    {
        echo "Even though TestInterface was typehinted for in HomeController:indexAction, TestClass was resolved. You
        can simply add these mappings for the autowiring DiC (Auryn) in global.yml! \r\n";
    }
}