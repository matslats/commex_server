<?php

/**
 * @file
 * This is a required endpoint, but very limited in functionality.
 * Note that it does NOT extend CommexRestResourceInterface.
 */
class Category {

  /**
   * Get a list of categories, keyed by category id.
   */
  static function getCategories() {
    $categories = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => SMALLAD_CATEGORIES]);
    foreach ($categories as $cat) {
      $options[$cat->id()] = $cat->label();
    }
    return $options;
  }

  /**
   * Get more metadata about categories, to be used by the client for navigation, menu items etc.
   *
   * @return array|NULL
   *   An array keyed by category id; each value is an array with keys name, color, and icon
   */
  static function getCategoryNavigation() {
    foreach (static::getCategories() as $id => $name) {
      $cats['categories'][$id] = array('name' => $name, 'logo' => '', 'color' => '');
    }
    return $cats; 
  }

}
