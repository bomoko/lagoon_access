<?php

namespace Drupal\ops_if;

class OpsIfFastlyDrupalUtilities {

  /**
   * @return array|false|string
   */
  public static function getServiceId() {
    return getenv("OPS_IF_SERVICE_ID");
  }

  /**
   * @return array|false|string
   */
  public static function getApiKey() {
    $opsPassphrase = \Drupal::config('ops_if.settings')->get('passphrase');

    $ifKeyEncrypted = getenv("OPS_IF_KEY");

    if(empty($opsPassphrase) || empty($ifKeyEncrypted)) {
      throw new \Exception("OPS_IF_KEY or Module passphrase not set");
    }
    return openssl_decrypt($ifKeyEncrypted, 'aes-256-ctr' , $opsPassphrase);
  }
}
