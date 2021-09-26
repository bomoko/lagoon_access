<?php

namespace Drupal\fastly_streamline_access_admin\Commands;

use Drupal\fastly_streamline_access\FsaFastly;
use Drupal\fastly_streamline_access\FsaFastlyDrupalUtilities;
use Drupal\fastly_streamline_access\FsaFastlyServiceDrafter;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class FsaAdminCommands extends DrushCommands {

  protected $formattedOutputItemHeaders = [
    'ACL',
    'id',
    'IP Address',
    'Created At',
    'Subnet',
    'Negated',
    'Comment',
  ];

  /**
   * Ages off IPs older than given number of days
   *
   * @param string $days
   *   Argument provided to the drush command.
   *
   * @command fastly_streamline_access:clear
   * @aliases oifh
   * @usage fastly_streamline_access:clear 90
   *   Will remove any ips from Fastly older than 90 days
   */
  public function cleanAcls($days = 90, $options = []) {
    $fastlyInterface = $this->getFastlyInterface();

    //Let's load all ACLs in the service

    $acls = $fastlyInterface->getAclList();
    $now = new \DateTime();
    $now->add(new \DateInterval('P4M'));

    foreach ($acls as $acl) {
      $ips = $fastlyInterface->getAclMembers($acl->id);
      foreach ($ips as $ip) {
        $createdDate = \DateTime::createFromFormat(
          "Y-m-d\TH:i:s\Z",
          $ip->created_at
        );
        $diff = $createdDate->diff($now);
        if ($diff->days > $days) {
          $fastlyInterface->deleteAclMember($acl->id, $ip->id);
          \Drupal::logger('fastly_streamline_access')->info(
            "Aging off ip '%ip' from ACL '%acl' because it is over %days days old",
            ['%ip' => $ip->ip, '%days' => $days, '%acl' => $acl->name]
          );
        }
      }
    }
  }

  /**
   * TODO: command to add to long lived ACL
   * TODO: command to generate fastly key from passphrase
   */

  /**
   * Search for IP addresses in all ACLs
   *
   * @param string $ipAddress
   *   Argument provided to the drush command.
   *
   *
   * @command fastly_streamline_access:remove
   * @aliases fsar
   * @usage fastly_streamline_access:remove "W.X.Y.Z"
   *   Will remove any ip W.X.Y.Z from Fastly
   */
  public function removeIp($ipAddress, $aclName = NULL, $options = []) {
    $fastlyInterface = $this->getFastlyInterface();

    $aclMatches = [];
    foreach ($fastlyInterface->getAclList() as $acl) {
      foreach ($fastlyInterface->getAclMembers($acl->id) as $ip) {
        if ($ip->ip == $ipAddress) {
          try {
            $fastlyInterface->deleteAclMember($acl->id, $ip->id);
          } catch (\Exception $ex) {
            $this->logger()->error($ex->getMessage());
            continue;
          }
          $aclMatches[$acl->id] = $acl->name;
        }
      }
    }

    $this->io()
      ->title(\Drupal::translation()->translate('IP ACL Removal'));
    $this->io()->table(
      ["REMOVED FROM THE FOLLOWING ACCESS CONTROL LISTS"],
      [$aclMatches]
    );
  }

  /**
   * Search for IP addresses in all ACLs
   *
   * @param string $searchTerms
   *   Argument provided to the drush command.
   *
   * @command fastly_streamline_access:search
   * @aliases fsas
   * @usage fastly_streamline_access:search "some text"
   *   Will remove any ips from Fastly older than 90 days
   */
  public function searchAcls($searchTerms, $options = []) {
    $fastlyInterface = $this->getFastlyInterface();

    //run through all ACLs and IPs - search for matching terms

    $acls = $fastlyInterface->getAclList();
    $now = new \DateTime();
    $now->add(new \DateInterval('P4M'));
    $retIps = [];
    foreach ($acls as $acl) {
      $ips = $fastlyInterface->getAclMembers($acl->id);
      foreach ($ips as $ip) {
        if (str_contains(json_encode($ip), $searchTerms)) {
          $formattedOutputItem = [
            'ACL' => $acl->name,
            'id' => $ip->id,
            'IP Address' => $ip->ip,
            'Created At' => $ip->created_at,
            'Subnet' => $ip->subnet,
            'Negated' => $ip->negated,
            'Comment' => $ip->comment,
          ];
          $retIps[] = $formattedOutputItem;
        }
      }
    }
    $this->io()->title(
      \Drupal::translation()->translate("IPs containing '{$searchTerms}'")
    );
    $this->io()->table($this->formattedOutputItemHeaders, $retIps);
  }

  /**
   * @return \Drupal\fastly_streamline_access\FsaFastly
   * @throws \Exception
   */
  protected function getFastlyInterface(): FsaFastly {
    $fastlyInterface = FsaFastly::GetFsaFastlyInstance(
      FsaFastlyDrupalUtilities::getApiKey(),
      FsaFastlyDrupalUtilities::getServiceId()
    );
    return $fastlyInterface;
  }

}

