<?php

namespace Drupal\fastly_streamline_access;

/**
 * Class FsaFastly
 *
 */
class FsaFastly {

  const VCL_PRIORITY = 300;

  protected $serviceId;

  protected $FsaCommsInstance;

  protected $serviceVersions = NULL; //this will only be populated if needed

  protected $editingService = FALSE;

  protected $editingVersion = NULL; //which version are we editing, if $this->>editingService is true?

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
   * used internally to populate the version information for this service id
   */
  public function getServiceVersions() {
    $endpoint = sprintf(
      "/service/%s/version",
      $this->serviceId
    );
    $serviceList = $this->fsaCommsInstance->doGet($endpoint);

    ksort($serviceList);
    $this->serviceVersions = $serviceList;
    return $this->serviceVersions;
  }

  /**
   * @return \Drupal\fastly_streamline_access\FsaComms
   */
  public function getOpsCommInstance() {
    return $this->fsaCommsInstance;
  }

  /**
   * @return false|mixed
   */
  public function getLatestServiceVersion() {
    $this->getServiceVersions();
    return end($this->serviceVersions);
  }

  /**
   * @return mixed|null
   */
  public function getLatestActiveServiceVersion() {
    $this->getServiceVersions();
    $la = NULL;
    foreach ($this->serviceVersions as $k => $v) {
      $latestSet = !is_null($la);
      $vIsActive = $v->active == TRUE;
      if ((!$latestSet && $vIsActive) || ($latestSet && $la->number < $v->number && $vIsActive)) {
        $la = $v;
      }
    }
    return $la;
  }

  /**
   * @param $versionToActive
   *
   * @return mixed
   */
  public function activateVersionOfService($versionToActive) {
    $endpoint = sprintf(
      "/service/%s/version/%s/activate",
      $this->serviceId,
      $versionToActive
    );

    return $this->fsaCommsInstance->doPut($endpoint);
  }

  /**
   * @return bool
   */
  public function isServiceCurrentlyBeingEdited() {
    return $this->editingService;
  }

  /**
   * @return null
   */
  public function getCurrentlyEditingService() {
    if (!$this->isServiceCurrentlyBeingEdited()) {
      return NULL;
    }
    return $this->editingVersion;
  }

  /**
   * @param $aclName
   *
   * @return mixed
   */
  public function addAclToVersion($aclName) {
    //    we can only actually run this if we're currently editing
    if (!$this->isServiceCurrentlyBeingEdited()) {
      throw new \Exception(
        sprintf(
          "Cannot create ACL named '%s' - we are not currently editing a service",
          $aclName
        )
      );
    }

    //first we check whether the ACL already exists or not
    $aclList = $this->getAclList();
    foreach ($aclList as $i) {
      if ($i->name == $aclName) {
        throw new \Exception(
          sprintf("Cannot create ACL named '%s' - already exists", $aclName)
        );
      }
    }

    $endpoint = sprintf(
      "/service/%s/version/%s/acl",
      $this->serviceId,
      $this->getLatestServiceVersion()->number
    );

    $content = [
      "name" => $aclName,
    ];

    return $this->fsaCommsInstance->doPost($endpoint, $content);
  }


  /**
   * @param $aclName
   *
   * @return mixed
   */
  public function deleteAcl($aclName) {
    $endpoint = sprintf(
      "/service/%s/version/%s/acl/%s",
      $this->serviceId,
      $this->getLatestServiceVersion()->number,
      $aclName
    );

    return $this->fsaCommsInstance->doDelete($endpoint);
  }


  /**
   * @param $vclName
   * @param $vcl
   * @param $priority
   *
   * @return mixed
   */
  public function addVclSnippetToVersion($vclName, $vcl, $priority) {
    if (!$this->isServiceCurrentlyBeingEdited()) {
      throw new \Exception(
        "You cannot add VCL to a service that is not being edited"
      );
    }

    //Take a look at currently registered vcl - if one with the current name exists, we bail
    $currentVcls = $this->getVcls();
    foreach ($currentVcls as $v) {
      if ($v->name == $vclName) {
        throw new \Exception("There is a preexisting vcl with this name");
      }
    }

    $endpoint = sprintf(
      "/service/%s/version/%s/snippet",
      $this->serviceId,
      $this->getLatestServiceVersion()->number
    );

    $content = [
      "name" => $vclName,
      "dynamic" => 1,
      "type" => "recv",
      "content" => $vcl,
      "priority" => self::VCL_PRIORITY,
    ];


    return $this->fsaCommsInstance->doPost($endpoint, $content);
  }

  /**
   * @param $vclName
   *
   * @return mixed
   */
  public function deleteVcl($vclName) {
    $endpoint = sprintf(
      "/service/%s/version/%s/snippet/%s",
      $this->serviceId,
      $this->getLatestServiceVersion()->number,
      $vclName
    );

    return $this->fsaCommsInstance->doDelete($endpoint);
  }

  /**
   * @param null $serviceVersion
   *
   * @return mixed
   */
  public function getVcls($serviceVersion = NULL) {
    $vclList = [];

    $endpoint = sprintf(
      "/service/%s/version/%s/snippet",
      $this->serviceId,
      $serviceVersion ? $serviceVersion : $this->getLatestServiceVersion(
      )->number
    );

    return $this->fsaCommsInstance->doGet($endpoint);
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

    return $this->fsaCommsInstance->doJsonPost($endpoint, $payload);
  }

  /**
   * @param $ipaddress
   * @param $aclId
   *
   * @return mixed
   */
  public function deleteAclMember($aclId, $aclEntryId) {
    $endpoint = sprintf(
      "/service/%s/acl/%s/entry/%s",
      $this->serviceId,
      $aclId,
      $aclEntryId
    );
    return $this->fsaCommsInstance->doDelete($endpoint);
  }


  /**
   * @param $aclId
   *
   * @return array
   */
  public function getAclMembers($aclId) {
    $acls = [];

    $endpointGeg = function ($page = 1) use ($aclId) {
      return sprintf(
        "/service/%s/acl/%s/entries?page=%s",
        $this->serviceId,
        $aclId,
        $page
      );
    };

    $done = FALSE;
    $page = 1;

    while (!$done) {
      $aclRet = $this->fsaCommsInstance->doGet($endpointGeg($page++));

      if (count($aclRet) == 0) {
        $done = TRUE;
      }
      else {
        $acls = array_merge($acls, $aclRet);
      }

      $done = TRUE;
    }

    return $acls;
  }

}
