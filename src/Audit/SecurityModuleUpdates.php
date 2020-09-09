<?php

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Token;

/**
 * Look for available security modules updates.
 * @Token(
 *  name = "updates",
 *  type = "array",
 *  description = "Description of security module updates available."
 * )
 */
class SecurityModuleUpdates extends Audit {

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    // Debugging drush status cmd
    // Drush 8 site
    // $output = $sandbox->exec('ssh -t -o LogLevel=ERROR -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -p 32222 amazeelabsv4-com-prod@ssh.lagoon.amazeeio.cloud drush pm-updatestatus --format=json --full');

    // Drush 9 site
    $output = $sandbox->exec('ssh -t -o LogLevel=ERROR -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -p 32222 sonova-d8-prod@ssh.lagoon.amazeeio.cloud drush pm:security');
    var_dump($output);
    exit();

    // Check drush version
    // If drush 9
    $output = $sandbox->drush()->pmSecurity('--format=json');

    $issues = [];

    foreach ($modules as $name => $info) {
      if (isset($info['recommended_major']) && $info['existing_major'] != $info['recommended_major']) {
        $issues[] = $info;
      }
      elseif ($info['existing_version'] != $info['candidate_version']) {
        $issues[] = $info;
      }
    }

    $sandbox->setParameter('updates', $issues);

    if (!count($issues)) {
      return TRUE;
    }

    $sec_updates = array_filter($issues, function ($info) {
      return strpos($info['status_msg'], 'SECURITY') !== FALSE;
    });

    if (count($issues)) {
      return Audit::FAIL;
    }

    // Pure failure if all issues are security ones.
    // if (count($sec_updates) === count($issues)) {
      // return FALSE;
    // }
    // Security updates and normal updates available.
    // elsif (count($sec_updates)) {
      // return Audit::WARNING_FAIL;
    // }

    // Just normal updates available.
    // return Audit::WARNING;
  }

}
