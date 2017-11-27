<?php

use \Drupal\user\Entity\User;

/**
 * @file
 * This is a required endpoint, but very limited in functionality.
 * Note that it does NOT extend CommexRestResourceInterface.
 */
class contact {

  /**
   * get a list of categories, keyed by category id.
   * $headers = 'From: webmaster@example.com' . "\r\n" .
   * 'Reply-To: webmaster@example.com' . "\r\n" .
   * 'X-Mailer: PHP/' . phpversion();
   */
  static function message($recipient_id, $subject, $body, $test) {
    if ($user = user_load($recipient_id)) {
      $headers[] = 'From: noreply@communityforge.net';
      $headers[] = "Reply-To: ".$GLOBALS['user']->name . '<'.$GLOBALS['user']->mail.'>';
      $headers[] = 'X-Mailer: PHP/' . phpversion();
      return mail($user->mail, $subject, $body, implode("\n", $headers));
    }
    else {
      trigger_error('Cannot mail unknown user: '.$recipient_id);
    }
  }

  public static function authenticate($username, $password) {
    global $user;
    if ($uid = user_authenticate($username, $password)) {
      $user = user_load($uid);
    }
    return (bool)$uid;
  }

}
