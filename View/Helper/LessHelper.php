<?php
/**
 * LESS to CSS Converter Plugin
 *
 * This plugin compiles your .less files to regular CSS withouth relying on
 * client- or serverside JavaScript
 *
 * Relies on the lessify.inc.php class in Vendor/lessphp
 *
 * PHP versions 4 and 5
 *
 * Mindthecode: http://www.mindthecode.com
 * Copyright 2011, Stef van den Ham
 *
 * Perfect Space: http://perfectspace.com
 * Copyright 2014, Brandon Ferens
 *
 * This code is based off of Stef van den Ham's Less plugin: https://github.com/Hyra/less
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author        Brandon Ferens
 * @copyright     Copyright 2014, Perfect Space (http://www.perfectspace.com)
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');
App::uses('Component', 'Controller');
App::import(
  'Vendor',
  'Less.Lessc',
  array(
    'file' => 'lessphp' . DS . 'lessc.inc.php'
  )
);

class LessHelper extends AppHelper {

  public $helpers = array('Html');

  public function __construct(View $View, $options = array()) {
    parent::__construct($View, $options);
    $this->lessFolder = new Folder(WWW_ROOT.'less');
    $this->cssFolder = new Folder(WWW_ROOT.'css');
  }

  /**
   * When called, will reset lessFolder for specific theme
   *
   * @note Note that it assumes themes are stored in app/View/Themed
   *
   * @param string $theme_name
   */
  private function setTheme() {

    $theme_path = App::themePath($this->theme) . 'webroot' . DS;
    $this->lessFolder = new Folder($theme_path.'less');
    $this->cssFolder = new Folder($theme_path.'css');

  }

  public function css($file, $options = array()) {

    if ($this->theme) {
      $this->setTheme();
    }

    if (is_array($file)) {
      foreach ($file as $candidate) {
        $source = $this->lessFolder->path.DS.$candidate.'.less';
        $target = str_replace('.less', '.css', str_replace($this->lessFolder->path, $this->cssFolder->path, $source));
        $this->autoCompileLess($source, $target, $options);
      }
    } else {
      $source = $this->lessFolder->path.DS.$file.'.less';
      $target = str_replace('.less', '.css', str_replace($this->lessFolder->path, $this->cssFolder->path, $source));
      $this->autoCompileLess($source, $target, $options);
    }
    echo $this->Html->css($file);
  }

  public function autoCompileLess($lessFilename, $cssFilename, $options = array()) {
    // Check if cache & output folders are writable and the less file exists.
    if (!is_writable(CACHE.'less')) {
      trigger_error(__d('cake_dev', '"%s" directory is NOT writable.', CACHE.'less'), E_USER_NOTICE);
      return;
    }
    if (file_exists($lessFilename) == false) {
      trigger_error(__d('cake_dev', 'File: "%s" not found.', $lessFilename), E_USER_NOTICE);
      return;
    }

    // Set the proper compression type. Defaults to 'compressed'
    $compressed = 'compressed';
    if (isset($options['compressed'])) {
      switch ($options['compressed']) {
        case 'lessjs':
          $compressed = 'lessjs';
          break;
        case 'classic':
          $compressed = 'classic';
          break;
        case null:
        case '':
          $compressed = null;
          break;
        default:
          $compressed = 'compressed';
          break;
      }
    }

    // Set if less should be cached. Defaults to 'true'
    $isCached = true;
    if (isset($options['cache'])) {
      switch ($options['cache']) {
        case false:
          $isCached = false;
          break;
        default:
          $isCached = true;
          break;
      }
    }

    $less = new lessc;
    $less->setFormatter($compressed);

    if ($isCached) {
      // Cache location
      $cacheFilename = CACHE . 'less' . DS .str_replace('/', '_', str_replace($this->lessFolder->path, '', $lessFilename) . ".cache");

      // Load the cache
      if (file_exists($cacheFilename)) {
        $cache = unserialize(file_get_contents($cacheFilename));
      } else {
        $cache = $lessFilename;
      }

      $new_cache = $less->cachedCompile($cache);

      if (!is_array($cache) || $new_cache['updated'] > $cache['updated'] || file_exists($cssFilename) === false) {
        $cssFile = new File($cssFilename, true);
        if ($cssFile->write($new_cache['compiled']) === false) {
          if (!is_writable(dirname($cssFilename))) {
            trigger_error(__d('cake_dev', '"%s" directory is NOT writable.', dirname($cssFilename)), E_USER_NOTICE);
          }
          trigger_error(__d('cake_dev', 'Failed to write "%s"', $cssFilename), E_USER_NOTICE);
        }
        $cacheFile = new File($cacheFilename, true);
        $cacheFile->write(serialize($new_cache));
      }
    } else {
      $less->checkedCompile($lessFilename, $cssFilename);
    }
  }
}
