<?php

namespace Drupal\fastly_streamline_access_admin\Commands;

use Drupal\fastly_streamline_access\FsaFastly;
use Drupal\fastly_streamline_access\FsaFastlyDrupalUtilities;
use Drush\Commands\DrushCommands;

/**
 * Fastly Admin commands
 *
 * This set of commands provides the primary CLI administration system for FSA
 */
class FsaAdminCommands extends DrushCommands {

  const AGE_OFF_STANDARD_NO_DAYS = 90;

  const AGE_OFF_EXTENDED_NO_DAYS = 270;

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
   * Ages off IPs
   *
   * @command fastly_streamline_access:clear
   * @aliases fsac
   * @usage fastly_streamline_access:clear
   *   Will remove any ips from Fastly older than 90 days and 6 months on long lived IPs
   */
  public function cleanAcls($options = []) {
    $fastlyInterface = $this->getFastlyInterface();

    $acls = $this->getFsaAcls();
    $now = new \DateTime();

    foreach ($acls as $acl) {
      $ips = $fastlyInterface->getAclMembers($acl->id);
      $days = self::AGE_OFF_STANDARD_NO_DAYS;

      if ($acl->name == \Drupal::config('fastly_streamline_access.settings')
          ->get('acl_long_name')) {
        $days = self::AGE_OFF_EXTENDED_NO_DAYS;
      }

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
   * Add Long Lived IP address
   *
   * @param string $ipAddress
   *   IP Address to add to the long lived ACL
   *
   * @command fastly_streamline_access:addLongLived
   * @aliases fsaal
   * @usage fastly_streamline_access:addLongLived "ipaddress"
   *   To add a subnet mask, use a slash ("192.0.2.0/24"). To exclude, use an exclamation mark ("!192.0.2.0").
   */
  public function addLongLivedIp($ipAddress, $options = []) {
    $fastlyInterface = $this->getFastlyInterface();

    $acl = $fastlyInterface->getAclByName(
      \Drupal::config('fastly_streamline_access.settings')->get('acl_long_name')
    );

    if (!$acl) {
      $this->io()->error("Cannot find ACL - check settings and try again");
      return;
    }

    try {
      $ret = $fastlyInterface->addAclMember($acl->id, $ipAddress);
      if (isset($ret->created_at)) {
        $this->io()->writeln("Successfully added ip address");
      }
    } catch (\Exception $ex) {
      $this->io()->error($ex->getMessage());
      return;
    }
  }

  /**
   * Remove IP from all ACLs
   *
   * @param string $ipAddress
   *   Ip Address to remove from ACLs
   *
   *
   * @command fastly_streamline_access:remove
   * @aliases fsar
   * @usage fastly_streamline_access:remove "W.X.Y.Z"
   *   Will remove any ip W.X.Y.Z from Fastly
   */
  public function removeIp($ipAddress, $options = []) {
    $fastlyInterface = $this->getFastlyInterface();

    $aclMatches = [];

    foreach ($this->getFsaAcls() as $acl) {

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
   *   Target search text
   *
   * @command fastly_streamline_access:search
   * @aliases fsas
   * @usage fastly_streamline_access:search "some text"
   *   Will remove any ips from Fastly older than 90 days
   */
  public function searchAcls($searchTerms, $options = []) {
    $fastlyInterface = $this->getFastlyInterface();

    //run through all ACLs and IPs - search for matching terms

    $now = new \DateTime();
    $now->add(new \DateInterval('P4M'));
    $retIps = [];
    $acls = $this->getFsaAcls($fastlyInterface);

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
   * Generate an encrypted key using a given passphrase
   *
   * @param string $key
   *   The key to encrypt
   *
   * @param string $passphrase
   *   The passphrase used to encrypt
   *
   * @command fastly_streamline_access:encrypt
   * @aliases fsae
   * @usage fastly_streamline_access:encrypt "key" "passphrase"
   *
   */
  public function generateEncryptedFSAKey($key, $passphrase, $options = []) {
    $this->io()->writeln(openssl_encrypt($key, 'aes-256-ctr', $passphrase));
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

  /**
   * Returns an array of the long and short ACL names registered with the parent module
   *
   * @return array
   */
  protected function getRegisteredACLNames() {
    $config = \Drupal::config('fastly_streamline_access.settings');
    return [
      $config->get('acl_long_name'),
      $config->get('acl_name')
    ];
  }

  /**
   * @param \Drupal\fastly_streamline_access\FsaFastly $fastlyInterface
   *
   * @return mixed
   */
  protected function getFsaAcls(FsaFastly $fastlyInterface) {
    $acls = $fastlyInterface->getAclList();
    $ourACLS = $this->getRegisteredACLNames();
    $acls = array_filter(
      $acls,
      function ($e) use ($ourACLS) {
        return in_array($e->name, $ourACLS);
      }
    );
    return $acls;
  }
}

