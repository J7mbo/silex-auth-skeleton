# silex-auth-skeleton

Annoyed with having to figure out the best code and directory structure for a working Silex skeleton? Well, look no further. This code is a great baseline for all your needs.

## Features

- Configuration loaded from YAML files, just like in Symfony2
- Environment specific config file (dev/live), routes config file, firewall file
- Login form using MySQL authentication (username *or* email) and a SHA1 password requirement
- Twig set up and ready to go for your templates
- Working firewall - currently, anonymous users can visit `/` and `/login`. Everything else is secured!
- Code is in controllers, with requests routed to these automatically
- Nicely laid out PSR compliant code

## Installation

You can **clone this repo** to get the code directly:

    $ git clone git://github.com/j7mbo/silex-auth-skeleton.git

Once you have the code, you'll need to use **composer** to update your dependencies:

    $ cd silex-auth-skeleton/
    $ composer update

You will need the following database table:

    +----------+------------------+------+-----+---------+----------------+
    | Field    | Type             | Null | Key | Default | Extra          |
    +----------+------------------+------+-----+---------+----------------+
    | id       | int(11) unsigned | NO   | PRI | NULL    | auto_increment |
    | username | varchar(100)     | NO   | UNI |         |                |
    | email    | varchar(100)     | NO   | UNI | NULL    |                |
    | password | varchar(255)     | NO   |     |         |                |
    | roles    | varchar(255)     | NO   |     |         |                |
    +----------+------------------+------+-----+---------+----------------+

SQL statement to **create the above database and table**:

    mysql> CREATE DATABASE `silexauth`;
    mysql> CREATE TABLE `users` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `username` varchar(100) NOT NULL DEFAULT '',
        `email` varchar(100) NOT NULL,
        `password` varchar(255) NOT NULL DEFAULT '',
        `roles` varchar(255) NOT NULL DEFAULT '',
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_username` (`username`),
        UNIQUE KEY `unique_email` (`email`)
    ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

You'll also need a **test user**:

    mysql> INSERT INTO `users` (username, email, password, roles) VALUES (
        'test', 'test@test.com', SHA1('password'), 'ROLE_USER'
    );

You'll also need a **host / vhost** to route everything through.

Add "`127.0.0.1 silex-auth.local`" (no quotes) to your `/etc/hosts` file.

Run the following commands to create a **vhost file** for `silex-auth.local`.

    $ cd /etc/apache2/sites-available
    $ sudo nano silex-auth.local

Copy / paste the following into your `silex-auth.local` file:

    <VirtualHost 127.0.0.1:80>
        DocumentRoot "/home/james/Dev/github/silex-auth/web/"
        DirectoryIndex index.php

        <Directory "/home/james/Dev/github/silex-auth/web/">
            AllowOverride All
            Allow from All
        </Directory>
    </VirtualHost>

Now save it, enable the vhost, and restart apache so that everything loads properly.

    $ ctrl + x, then y, then enter
    $ sudo a2ensite silex-auth.local
    $ sudo service apache2 restart

You should now be able to point at `http://silex-auth.local` in your browser and see the home page. After clicking the login link, you should be able to login with either **"test" and "password"**, or **"test@test.com" and "password"**.
