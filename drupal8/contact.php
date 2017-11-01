<?php

use \Drupal\user\Entity\User;

/**
 * @file
 * This is a required endpoint, but very limited in functionality.
 * Note that it does NOT extend CommexRestResourceInterface.
 */
class Contact {

  /**
   * get a list of categories, keyed by category id.
   * $headers = 'From: webmaster@example.com' . "\r\n" .
   * 'Reply-To: webmaster@example.com' . "\r\n" .
   * 'X-Mailer: PHP/' . phpversion();
   */
  static function message($recipient_id, $subject, $body, $test) {
    if ($user = User::load($recipient_id)) {
      $headers[] = 'From: noreply@communityforge.net';
      $reply = \Drupal::currentUser()->getDisplayName() . '<'.\Drupal::currentUser()->getEmail().'>';
      $headers[] = "Reply-To: $reply";
      $headers[] = 'X-Mailer: PHP/' . phpversion();
      return mail($user->getEmail(), $subject, $body, implode("\n", $headers));
    }
    else {
      trigger_error('Cannot mail unknown user: '.$recipient_id);
    }
  }
  
  public static function authenticate($username, $password) {
    // This is for Drupal 8
    global $container;
    if ($uid = $container->get('user.auth')->authenticate($username, $password)) {
      \Drupal::currentUser()->setAccount(User::load($uid));
    }
    return (bool)$uid;
  }

}
