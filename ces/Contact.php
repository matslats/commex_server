<?php

/**
 * @file
 * This is a required endpoint, but very limited in functionality.
 * Note that it does NOT extend CommexRestResourceInterface.
 */
class Contact {

  /**
   * get a list of categories, keyed by category id.
   */
  static function message($recipient_id, $subject, $body, $test) {
    $db = new CommexDb();
    if ($mail = $db->select1("SELECT email FROM users WHERE uid = '$recipient_id'")) {
      return mail($mail, $subject, $body);//bool
    }
    else {
      trigger_error('User has no email');
    }
  }

}
