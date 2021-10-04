<?php

namespace Drupal\fastly_streamline_access;

class FsaFastlyDrupalUtilities {

  /**
   * @return array|false|string
   */
  public static function getServiceId() {
    return getenv("FSA_SERVICE_ID");
  }

  /**
   * @return array|false|string
   */
  public static function getApiKey() {
    $opsPassphrase = variable_get("fsa_passphrase");
    $ifKeyEncrypted = getenv("FSA_KEY");

    if(empty($opsPassphrase) || empty($ifKeyEncrypted)) {
      throw new \Exception("FSA_KEY or Module passphrase not set");
    }
    return openssl_decrypt($ifKeyEncrypted, 'aes-256-ctr' , $opsPassphrase);
  }

}
