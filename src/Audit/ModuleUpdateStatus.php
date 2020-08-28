<?php

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Token;

/**
 * Look for contrib modules with available updates.
 * @Token(
 *  name = "updates",
 *  type = "array",
 *  description = "Description of module updates available."
 * )
 */
class ModuleUpdateStatus extends Audit {

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    $output = $sandbox->drush()->pmUpdatestatus('--format=json --full');

    // Debugging drush status cmd
    //$output = $sandbox->exec('ssh -t -o LogLevel=ERROR -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -p 32222 amazeelabsv4-com-prod@ssh.lagoon.amazeeio.cloud drush pm-updatestatus --format=json --full');

    $lines = explode(PHP_EOL, $output);
    $lines = array_map('trim', $lines);

    // Output can often contain non-json output which needs to be filtered out.
    while ($line = array_shift($lines)) {
      if ($line == "{") {
        array_unshift($lines, $line);
        break;
      }
    }
    $json = implode(PHP_EOL, $lines);
    $modules = json_decode($json, TRUE);

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
