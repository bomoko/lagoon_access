<?php


namespace Drupal\fastly_streamline_access;

use \Exception;


/**
 * Class FsaFastlyServiceDrafter
 * This class is the primary wrapper for dealing with Drafting new services
 * Specifically, it should be used for setting up and tearing down OPS instances
 *
 * Essentially it provides guide rails for setup and teardown of the objects
 * required by OPS
 *
 * @package Fsa
 */
class FsaFastlyServiceDrafter {

  protected $FsaFastly;

  protected $editingService;

  protected $serviceName;

  private $editingVersionDetails;

  public static function doStandardACLRegistration(
    FsaFastly $fastly,
    $serviceName
  ) {
    $drafter = new FsaFastlyServiceDrafter($fastly, $serviceName);
    $drafter->stageNewVersionOfService();
//    var_dump($drafter->registerACLs($serviceName . '_long_lived'));
    var_dump($drafter->registerACLs($serviceName));
    //    $drafter->registerVCL(
    //      $serviceName,
    //      'sub vcl_recv {
    //    error 403 "Forbidden";
    //}'
    //    );
    $drafter->publishNewVersion();
  }

  public function __construct(FsaFastly $fastly, $serviceName) {
    $this->FsaFastly = $fastly;
    $this->serviceName = $serviceName;
  }

  // Should this be moved into a second class? ... perhaps
  public function stageNewVersionOfService() {
    if ($this->editingService == TRUE) {
      throw new Exception(
        "You are already editing a service - can not clone a new service"
      );
    }

    $latestServiceVersion = $this->FsaFastly->getLatestServiceVersion();
    if (is_null($latestServiceVersion)) {
      throw new Exception(
        "Cannot stage a new version of the latest service, there has been no service published"
      );
    }
    if ($latestServiceVersion->number != $this->FsaFastly->getLatestActiveServiceVersion(
      )->number) {
      throw new Exception(
        "It seems that the latest version of the service isn't the active version - this means either that there has been a service rollback, or that the service is being edited. Contact Fastly administrator"
      );
    }


    $endpoint = sprintf(
      "/service/%s/version/%s/clone",
      $latestServiceVersion->service_id,
      $latestServiceVersion->number
    );

    $clonedService = $this->FsaFastly->getOpsCommInstance()->doPut($endpoint);

    $this->editingVersionDetails = $clonedService;
    $this->editingService = TRUE;

    return $clonedService->number;
  }

  public function registerVCL($name, $vcl) {
    return $this->FsaFastly->addVclSnippetToVersion(
      $name,
      $vcl,
      FsaFastly::VCL_PRIORITY
    );
  }

  public function registerACLs($name) {
    return $this->FsaFastly->addAclToVersion($name);
  }


  public function publishNewVersion() {
    $endpoint = sprintf(
      "/service/%s/version/%s/activate",
      $this->editingVersionDetails->service_id,
      $this->editingVersionDetails->number
    );
    $this->FsaFastly->getOpsCommInstance()->doPut($endpoint);
  }


}
