<?php

namespace OpsFs;

/**
 * Class OpsIfFastly
 *
 */
class OpsIfFastly {

  protected $aclId;

  protected $serviceId;

  //  protected $fastlyKey;

  protected $opsFsCommsInstance;

  //This isn't a constant since we might want to override it at some point?
  protected $fastlyApiBase = "https://api.fastly.com";

  protected $serviceVersions = NULL; //this will only be populated if needed

  protected $editingService = FALSE;

  protected $editingVersion = NULL; //which version are we editing, if $this->>editingService is true?

  public static function GetOpsIfFastlyInstance($fastlyKey, $serviceId, $aclId) {
    return new OpsIfFastly(new OpsFsComms($fastlyKey), $serviceId, $aclId);
  }

  protected function __construct(OpsFsComms $opsFsCommsInstance, $serviceId, $aclId) {
    $this->aclId = $aclId; //Do we use this anywhere?
    $this->opsFsCommsInstance = $opsFsCommsInstance;
    $this->serviceId = $serviceId;
  }

  /**
   * used internally to populate the version information for this service id
   */
  public function getServiceVersions() {
    $endpoint = sprintf("%s/service/%s/version",
      $this->fastlyApiBase,
      $this->serviceId
    );
    $serviceList = $this->opsFsCommsInstance->doGet($endpoint);

    ksort($serviceList);
    $this->serviceVersions = $serviceList;
    return $this->serviceVersions;
  }

  public function getLatestServiceVersion() {
    $this->getServiceVersions();
    return end($this->serviceVersions);
  }

  protected function getLatestActiveServiceVersion() {
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
    $endpoint = sprintf("%s/service/%s/version/%s/activate",
      $this->fastlyApiBase,
      $this->serviceId,
      $versionToActive
    );

    $versionActivated = $this->opsFsCommsInstance->doPut($endpoint);

    return $versionActivated;
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
        throw new Exception(sprintf("Cannot create ACL named '%s' - already exists", $aclName));
      }
    }

    //{{fastly_url}}/service/{{service_id}}/version/{{version_id}}/acl
    $endpoint = sprintf("%s/service/%s/version/%s/acl",
      $this->fastlyApiBase,
      $this->serviceId,
      $this->getLatestServiceVersion()->number
    );

    $content = [
      "name" => $aclName,
    ];


    return $this->opsFsCommsInstance->doPost($endpoint, $content);
  }


  public function deleteAcl($aclName) {

    $endpoint = sprintf("%s/service/%s/version/%s/acl/%s",
      $this->fastlyApiBase,
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

    //{{fastly_url}}/service/{{service_id}}/version/{{version_id}}/snippet
    $endpoint = sprintf("%s/service/%s/version/%s/snippet",
      $this->fastlyApiBase,
      $this->serviceId,
      $this->getLatestServiceVersion()->number
    );

    $content = [
      "name" => $vclName,
      "dynamic" => 1,
      "type" => "recv",
      "content" => $vcl,
      "priority" => 300,
    ];


    return $this->opsFsCommsInstance->doPost($endpoint, $content);
  }

  public function deleteVcl($vclName) {
    //    {{fastly_url}}/service/{{service_id}}/version/{{version_id}}/snippet/{{snippet_name}}
    $endpoint = sprintf("%s/service/%s/version/%s/snippet/%s",
      $this->fastlyApiBase,
      $this->serviceId,
      $this->getLatestServiceVersion()->number,
      $vclName
    );

    return $this->opsFsCommsInstance->doDelete($endpoint);
  }

  public function getVcls($serviceVersion = NULL) {
    //    https://api.fastly.com/service/SU1Z0isxPaozGVKXdv0eY/version/1/snippet
    $vclList = [];

    $endpoint = sprintf("%s/service/%s/version/%s/snippet",
      $this->fastlyApiBase,
      $this->serviceId,
      $serviceVersion ? $serviceVersion : $this->getLatestServiceVersion()->number
    );

    return $this->opsFsCommsInstance->doGet($endpoint);
  }

  public function getAclList($version = NULL) {
    $aclList = [];

    $endpoint = sprintf("%s/service/%s/version/%s/acl",
      $this->fastlyApiBase,
      $this->serviceId,
      $version ? $version : $this->getLatestServiceVersion()->number
    );

    return $this->opsFsCommsInstance->doGet($endpoint);
  }


  public function addAclMember($ipaddress) {
    //{{fastly_url}}/service/{{service_id}}/acl/{{acl_id}}/entry
    //post, {"subnet":0,"ip":"127.0.0.1"} // also needs some details about the context

    $endpoint = sprintf("%s/service/%s/acl/%s/entry",
      $this->fastlyApiBase,
      $this->serviceId,
      $this->aclId);


    $payload = ["ip" => $ipaddress];

    return $this->opsFsCommsInstance->doJsonPost($endpoint, $payload);
  }

  public function getAclMembers() {

    $acls = [];

    $endpointGeg = function ($page = 1) {
      return sprintf("%s/service/%s/acl/%s/entries?page=%s",
        $this->fastlyApiBase,
        $this->serviceId,
        $this->aclId,
        $page);
    };

    $done = FALSE;
    $page = 1;

    while (!$done) {

      $aclRet = $this->opsFsCommsInstance->doGet($endpointGeg($page++));
      
      if (count($aclRet) == 0) {
        $done = TRUE;
      }
      else {
        $acls = $acls + $aclRet;
      }
    }

    return $acls;

  }

}
