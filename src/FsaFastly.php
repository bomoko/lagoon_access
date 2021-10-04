<?php

namespace Drupal\fastly_streamline_access;

/**
 * Class FsaFastly
 *
 */
class FsaFastly {

  protected $serviceId;

  protected $fsaCommsInstance;

  /**
   * @param $fastlyKey
   * @param $serviceId
   *
   * New ups an FsaFastly interface
   *
   * @return \Drupal\fastly_streamline_access\FsaFastly
   */
  public static function GetFsaFastlyInstance(
    $fastlyKey,
    $serviceId
  ) {
    return new FsaFastly(new FsaComms($fastlyKey), $serviceId);
  }

  /**
   * FsaFastly constructor.
   *
   * @param \Drupal\fastly_streamline_access\FsaComms $fsaCommsInstance
   * @param $serviceId
   */
  protected function __construct(
    FsaComms $fsaCommsInstance,
    $serviceId
  ) {
    $this->fsaCommsInstance = $fsaCommsInstance;
    $this->serviceId = $serviceId;
  }

  /**
   * @param string $aclName
   * returns acl details matching name - FALSE otherwise
   *
   * @return mixed|null
   */
  public function getAclByName($aclName) {
    $aclList = $this->getAclList();
    foreach ($aclList as $acl) {
      if ($acl->name == $aclName) {
        return $acl;
      }
    }
    return FALSE;
  }

  /**
   * @param null $version
   *
   * @return mixed
   */
  public function getAclList($version = NULL) {
    $aclList = [];

    $endpoint = sprintf(
      "/service/%s/version/%s/acl",
      $this->serviceId,
      $version ? $version : $this->getLatestServiceVersion()->number
    );

    return $this->fsaCommsInstance->doGet($endpoint);
  }


  /**
   * @param $ipaddress
   * @param $aclId
   *
   * @return mixed
   */
  public function addAclMember($aclId, $ipaddress, $entryData = []) {
    $endpoint = sprintf(
      "/service/%s/acl/%s/entry",
      $this->serviceId,
      $aclId
    );

    $payload = ["ip" => $ipaddress, "comment" => json_encode($entryData)];

    $ret = $this->fsaCommsInstance->doJsonPost($endpoint, $payload);
    //if this returns a bad response, we signal via exception
    if (empty($ret->id) && isset($ret->msg)) {
      throw new \Exception(
        "Could not add ip: " . $ret->msg . " - " . $ret->detail
      );
    }
    return $ret;
  }

}
