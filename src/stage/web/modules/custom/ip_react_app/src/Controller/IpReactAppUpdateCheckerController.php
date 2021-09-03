<?php

namespace Drupal\ip_react_app\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * Defines IpReactAppUpdateCheckerController class.
 */
class IpReactAppUpdateCheckerController extends ControllerBase {

  const SETTINGS = 'ip.react.builder.settings';

  public static function checkBuildUpdates($url,  $token, $domain){
    try {
      $response =  \Drupal::httpClient()->post($url, [
        'verify' => TRUE,
        'form_params' => [
          'token' => $token,
          'domain' => $domain,
        ],
        'headers' => [
          'Content-type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ],
      ])->getBody()->getContents();

      $json_encoder = new JsonEncoder();
      $serializer = new Serializer([], [$json_encoder]);
      $format = 'json';

      return $serializer->decode($response, $format);

    } catch (\Exception $e) {
      \Drupal::logger('ip_react_app')
             ->error(t('Something went wrong during getting react build status. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_react_app')->error($e->getMessage());
      return FALSE;
    }
  }

  public static function updateBuild($uri, $config){
    try {
      $response = \Drupal::httpClient()->get($uri);
      $data = $response->getBody();

      $dest_dir = 'public://' . $config['local_dir'] . '/';
      $dest_file = $dest_dir .'/react.build.zip';

      if ( \Drupal::service('file_system')->prepareDirectory($dest_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY)) {
        //array_map(function ($e){((is_file($e)) ? (unlink($e)) : (rmdir($e)));}, array_filter((array) glob(\Drupal::service('file_system')->realpath($dest_dir)."/*")));

        self::delTree(\Drupal::service('file_system')->realpath($dest_dir)."/");

        if (!($my_file_obj = file_save_data($data, $dest_file, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE))) {
          return FALSE;
        }
      } else {
        return FALSE;
      }

      $path = \Drupal::service('file_system')->realpath($my_file_obj->getFileUri());

      $archive = new \Drupal\Core\Archiver\Zip($path);

      $archive->getArchive()->extractTo(\Drupal::service('file_system')->realpath(file_default_scheme() . "://".$config['local_dir']));

      // remove class and zip file
      unset($archive);
      unlink($path);

      return TRUE;
    } catch (\Exception $e) {
      \Drupal::logger('ip_react_app')
             ->error(t('Something went wrong during updating react build. Please check your data or contact site administrator.'));
      \Drupal::logger('ip_react_app')->error($e->getMessage());
      return FALSE;
    }
  }

  private static function delTree($dir)
  {
    $files = array_diff(scandir($dir), array('.', '..'));

    foreach ($files as $file) {
      (is_dir("$dir/$file")) ? self::delTree("$dir/$file") : unlink("$dir/$file");
    }
  }

  private static function getSettings(){
    return \Drupal::configFactory()->get(self::SETTINGS);
  }

  public static function getLastTimeBuilded(){
    return \Drupal::configFactory()->get(self::SETTINGS)->get('last_build_timestamp');
  }

  public static function saveLastTimeBuilded($time){
    return \Drupal::configFactory()->getEditable(self::SETTINGS)->set('last_build_timestamp', $time)->save();
  }
}
