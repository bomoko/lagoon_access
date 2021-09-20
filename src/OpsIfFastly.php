<?php

namespace Drupal\ops_if;

/**
 * Class OpsIfFastly
 *
 */

class OpsIfFastly {

  const VCL_PRIORITY = 300;

  protected $aclId;

  protected $serviceId;

  protected $opsFsCommsInstance;

  protected $serviceVersions = NULL; //this will only be populated if needed

  protected $editingService = FALSE;

  protected $editingVersion = NULL; //which version are we editing, if $this->>editingService is true?

  public static function GetOpsIfFastlyInstance(
    $fastlyKey,
    $serviceId,
    $aclId
  ) {
    return new OpsIfFastly(new OpsFsComms($fastlyKey), $serviceId, $aclId);
  }

  protected function __construct(
    OpsFsComms $opsFsCommsInstance,
    $serviceId,
    $aclId
  ) {
    $this->aclId = $aclId; //Do we use this anywhere?
    $this->opsFsCommsInstance = $opsFsCommsInstance;
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
    $serviceList = $this->opsFsCommsInstance->doGet($endpoint);

    ksort($serviceList);
    $this->serviceVersions = $serviceList;
    return $this->serviceVersions;
  }

  public function getOpsCommInstance() {
    return $this->opsFsCommsInstance;
  }

  public function getLatestServiceVersion() {
    $this->getServiceVersions();
    return end($this->serviceVersions);
  }

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

  public function activateVersionOfService($versionToActive) {
    $endpoint = sprintf(
      "/service/%s/version/%s/activate",
      $this->serviceId,
      $versionToActive
    );

    return $this->opsFsCommsInstance->doPut($endpoint);
  }

  public function isServiceCurrentlyBeingEdited() {
    return $this->editingService;
  }

  public function getCurrentlyEditingService() {
    if (!$this->isServiceCurrentlyBeingEdited()) {
      return NULL;
    }
    return $this->editingVersion;
  }

  public function addAclToVersion($aclName) {
    //we can only actually run this if we're currently editing
    //    if (!$this->isServiceCurrentlyBeingEdited()) {
    //      throw new Exception(sprintf("Cannot create ACL named '%s' - we are not currently editing a service", $aclName));
    //    }

    //first we check whether the ACL already exists or not
    $aclList = $this->getAclList();
    foreach ($aclList as $i) {
      if ($i->name == $aclName) {
        throw new Exception(
          sprintf("Cannot create ACL named '%s' - already exists", $aclName)
        );
      }
    }

    //{{fastly_url}}/service/{{service_id}}/version/{{version_id}}/acl
    $endpoint = sprintf(
      "/service/%s/version/%s/acl",
      $this->serviceId,
      $this->getLatestServiceVersion()->number
    );

    $content = [
      "name" => $aclName,
    ];


    return $this->opsFsCommsInstance->doPost($endpoint, $content);
  }


  public function deleteAcl($aclName) {
    $endpoint = sprintf(
      "/service/%s/version/%s/acl/%s",
      $this->serviceId,
      $this->getLatestServiceVersion()->number,
      $aclName
    );

    return $this->opsFsCommsInstance->doDelete($endpoint);
  }


  public function addVclSnippetToVersion($vclName, $vcl, $priority) {
    //    if (!$this->isServiceCurrentlyBeingEdited()) {
    //      throw new Exception("You cannot add VCL to a service that is not being edited");
    //    }

    //Take a look at currently registered vcl - if one with the current name exists, we bail
    $currentVcls = $this->getVcls();
    foreach ($currentVcls as $v) {
      if ($v->name == $vclName) {
        throw new Exception("There is a preexisting vcl with this name");
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


    return $this->opsFsCommsInstance->doPost($endpoint, $content);
  }

  public function deleteVcl($vclName) {
    $endpoint = sprintf(
      "/service/%s/version/%s/snippet/%s",
      $this->serviceId,
      $this->getLatestServiceVersion()->number,
      $vclName
    );

    return $this->opsFsCommsInstance->doDelete($endpoint);
  }

  public function getVcls($serviceVersion = NULL) {
    //    https://api.fastly.com/service/SU1Z0isxPaozGVKXdv0eY/version/1/snippet
    $vclList = [];

    $endpoint = sprintf(
      "/service/%s/version/%s/snippet",
      $this->serviceId,
      $serviceVersion ? $serviceVersion : $this->getLatestServiceVersion(
      )->number
    );

    return $this->opsFsCommsInstance->doGet($endpoint);
  }

  public function getAclList($version = NULL) {
    $aclList = [];

    $endpoint = sprintf(
      "/service/%s/version/%s/acl",
      $this->serviceId,
      $version ? $version : $this->getLatestServiceVersion()->number
    );

    return $this->opsFsCommsInstance->doGet($endpoint);
  }


  public function addAclMember($ipaddress, $aclId = null) {
    //{{fastly_url}}/service/{{service_id}}/acl/{{acl_id}}/entry
    //post, {"subnet":0,"ip":"127.0.0.1"} // also needs some details about the context

    $endpoint = sprintf(
      "/service/%s/acl/%s/entry",
      $this->serviceId,
      is_null($aclId) ? $this->aclId : $aclId
    );

    var_dump($endpoint);
    $payload = ["ip" => $ipaddress];

    return $this->opsFsCommsInstance->doJsonPost($endpoint, $payload);
  }

  public function getAclMembers($aclId = null) {
    $acls = [];

    $endpointGeg = function ($page = 1) use ($aclId) {
      return sprintf(
        "/service/%s/acl/%s/entries?page=%s",
        $this->serviceId,
        is_null($aclId) ? $this->aclId: $aclId,
        $page
      );
    };

    $done = FALSE;
    $page = 1;

    while (!$done) {
      $aclRet = $this->opsFsCommsInstance->doGet($endpointGeg($page++));

      if (count($aclRet) == 0) {
        $done = TRUE;
      }
      else {
        $acls = array_merge($acls, $aclRet);
      }

      $done = TRUE;
//      $done = TRUE;
    }

    return $acls;
  }

}
