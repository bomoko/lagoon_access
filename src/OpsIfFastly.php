<?php

namespace \Drupal\ops_if;



/**
 * Class OpsIfFastly
 *
 */
class OpsIfFastly {

  protected $aclId;

  protected $serviceId;

  protected $fastlyKey;

  //This isn't a constant since we might want to override it at some point?
  protected $fastlyApiBase = "https://api.fastly.com";

  protected $serviceVersions = NULL; //this will only be populated if needed

  const HTTP_REQUEST_TYPE_POST = 'POST';

  const HTTP_REQUEST_TYPE_GET = 'GET';

  const HTTP_REQUEST_TYPE_PUT = 'PUT';

  public function __construct($fastlyKey, $serviceId, $aclId) {
    $this->aclId = $aclId;
    $this->fastlyKey = $fastlyKey;
    $this->serviceId = $serviceId;
  }

  /**
   * used internall to populate the version information for this service id
   */
  public function getServiceVersions() {
    $endpoint = sprintf("%s/service/%s/version",
      $this->fastlyApiBase,
      $this->serviceId
    );
    $serviceList = json_decode($this->doApiCall(self::HTTP_REQUEST_TYPE_GET, $endpoint));
    if (json_last_error()) {
      throw new Exception(sprintf("Error with json decoding: " . json_last_error_msg()));
    }
    ksort($serviceList);
    $this->serviceVersions = $serviceList;
    return $this->serviceVersions;
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

  public function stageNewVersionOfService() {
    $latestServiceVersion = $this->getLatestServiceVersion();
    if (is_null($latestServiceVersion)) {
      throw new Exception("Cannot stage a new version of the latest service, there has been no service published");
    }
    if ($latestServiceVersion->number != $this->getLatestActiveServiceVersion()->number) {
      throw new Exception("It seems that the latest version of the service isn't the active version - this means either that there has been a service rollback, or that the service is being edited. Contact Fastly administrator");
    }

    //{{fastly_url}}/service/{{service_id}}/version/{{version_id}}/clone

    $endpoint = sprintf("%s/service/%s/version/%s/clone",
      $this->fastlyApiBase,
      $this->serviceId,
      $latestServiceVersion->number
    );

    $clonedService = json_decode($this->doApiCall(self::HTTP_REQUEST_TYPE_PUT, $endpoint));
    if (json_last_error()) {
      throw new Exception(sprintf("Error with json decoding: " . json_last_error_msg()));
    }

    return $clonedService->number;
  }


  public function activateVersionOfService($versionToActive) {
    $endpoint = sprintf("%s/service/%s/version/%s/activate",
      $this->fastlyApiBase,
      $this->serviceId,
      $versionToActive
    );

    $versionActivated = json_decode($this->doApiCall(self::HTTP_REQUEST_TYPE_PUT, $endpoint));
    if (json_last_error()) {
      throw new Exception(sprintf("Error with json decoding: " . json_last_error_msg()));
    }

    return $versionToActive;
  }

  public function addVclSnippet($vcl, $priority) {


  }


  public function getAclList() {
    $aclList = [];

    $endpoint = sprintf("%s/service/%s/version/%s/acl",
      $this->fastlyApiBase,
      $this->serviceId,
      $this->getLatestServiceVersion()->number
    );

    $aclList = json_decode($this->doApiCall(self::HTTP_REQUEST_TYPE_GET, $endpoint));

    if (json_last_error()) {
      throw new Exception(sprintf("Error with json decoding: " . json_last_error_msg()));
    }

    return $aclList;
  }


  public function addAclMember($ipaddress) {
    //{{fastly_url}}/service/{{service_id}}/acl/{{acl_id}}/entry
    //post, {"subnet":0,"ip":"127.0.0.1"} // also needs some details about the context

    $endpoint = sprintf("%s/service/%s/acl/%s/entry",
      $this->fastlyApiBase,
      $this->serviceId,
      $this->aclId);


    $payload = ["ip" => $ipaddress];

    return $this->doApiCall(self::HTTP_REQUEST_TYPE_POST, $endpoint, $payload);
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

      $aclRet = json_decode($this->doApiCall(self::HTTP_REQUEST_TYPE_GET, $endpointGeg($page++)));

      if (json_last_error()) {
        throw new Exception(sprintf("Error with json decoding: " . json_last_error_msg()));
      }

      if (count($aclRet) == 0) {
        $done = TRUE;
      }
      else {
        $acls = $acls + $aclRet;
      }
    }

    return $acls;

  }

  protected function doApiCall($type, $urlFragment, $postdata = []) {
    $curl = curl_init();

    $curlopts = [
      CURLOPT_URL => $urlFragment,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $type,
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
        sprintf('Fastly-Key: %s', $this->fastlyKey),
      ],
    ];

    if ($type == self::HTTP_REQUEST_TYPE_POST) {
      var_dump(json_encode($postdata, JSON_FORCE_OBJECT));
      $curlopts[CURLOPT_POSTFIELDS] = json_encode($postdata, JSON_FORCE_OBJECT);
      $curlopts[CURLOPT_HTTPHEADER] = [
        'Content-Type: application/json',
        'Accept: application/json',
        sprintf('Fastly-Key: %s', $this->fastlyKey),
      ];
    }

    curl_setopt_array($curl, $curlopts);

    $response = curl_exec($curl);

    curl_close($curl);

    return $response;
  }


}
