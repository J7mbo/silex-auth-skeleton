<?php

/**
 * Called automatically during Symfony's login process
 */

namespace App\User;

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException,
    Symfony\Component\Security\Core\Exception\UnsupportedUserException,
    Symfony\Component\Security\Core\User\UserProviderInterface,
    Symfony\Component\Security\Core\User\UserInterface,
    Doctrine\DBAL\Connection;
 
class UserProvider implements UserProviderInterface
{
    private $conn;
 
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }
 
    public function loadUserByUsername($usernameOrEmail)
    {
        $stmt = $this->conn->executeQuery('SELECT * FROM users WHERE username = ? OR email = ?', array(strtolower($usernameOrEmail), strtolower($usernameOrEmail)));
        
        if (!$user = $stmt->fetch()) 
        {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $usernameOrEmail));
        }
 
        return new User($user['username'], $user['email'], $user['password'], explode(',', $user['roles']), true, true, true, true);
    }
 
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }
 
        return $this->loadUserByUsername($user->getUsername());
    }
 
    public function supportsClass($class)
    {
        return $class === 'Symfony\Component\Security\Core\User\User';
    }
}