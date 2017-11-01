# commex_server
A mini-framework to make it easy for any platform to serve the Commex API
This package contains shared code and code specific to each platform.

#installation
* Copy the config_example.php to config.php
* set the variable $platformm to a directory name e.g. drupal8, or drupal7
* on Apache the .htaccess file should just work, otherwise adjust your nginx host file so that requests to /commex/* all go to index.php.
