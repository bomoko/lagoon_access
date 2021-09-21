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
    return getenv("OPS_IF_KEY");
  }
}
