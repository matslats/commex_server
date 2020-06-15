# commex_server
A mini-framework to make it easy for any platform to serve the Commex API
This package contains shared code and code specific to each platform.

#installation
* Copy the config_example.php to config.php
* set the variable $platform to a directory name e.g. drupal8, or drupal7
* on Apache the .htaccess file should just work, otherwise adjust your nginx host file so that requests to /commex/* all go to index.php.

#drupal & aegir .htaccess modifications
<?php //var/aegir/.drush/provision/commex.drush.inc

/**
 * Drush hook to add a line to the .htaccess file for each site.
 * @see http://docs.aegirproject.org/en/3.x/extend/altering-behaviours/#injecting-into-site-vhosts
 */
function commex_provision_apache_vhost_config($uri, $data) {
  return array(
    '',
    '#extra config for the commex mobile app',
    'RewriteEngine On', // Drupal requires that this be on.
    'RewriteRule ^/commex/(.*) /commex/index.php [L]',
    ''
  );
}

?>
 
