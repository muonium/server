# Requirements
- MySQL/MariaDB
- Redis
- Composer
- HTTPD Apache, 2.4 preferred
- PHP 5.6 / 7.0+
- PHP mcrypt extension

# Installation
Follow these steps to install Muonium's server API.
1. git clone https://github.com/muonium/server.git at the root of your configuration and create a folder called nova at the same level with server.git

--root

----/server

----/nova

2. run this command inside server folder in order to install dependencies.
```
php composer.phar install
```
3. create a database named "cloud"
4. create an user for the "cloud" database, give it all the privileges, and exec cloud.sql in the "cloud" db.
5. create server/config/confDB.php and configure it like in confDB.php.model:
```php
<?php
namespace config;
class confDB {
	const host = "a.b.c.d"; //the ip of the database server, can be localhost/127.0.0.1
	const user = "user"; //mysql user who has the privileges on the DB "cloud"
	const password = "password"; //its password
	const db = "cloud"; //the DB
}
?>
```
6. Do the same for server/config/confMail.php, for server/config/confPayments.php and for server/config/secretKey.php

7. Create a folder `public`, clone the [translations](https://github.com/muonium/translations), extract the `webclient` folder to `public` and rename it as `translations`.

PS: enable mod_rewrite

# Documentation
You can find our documentation [here](https://github.com/muonium/docs).
