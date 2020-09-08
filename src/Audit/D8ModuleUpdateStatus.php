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
class D8ModuleUpdateStatus extends Audit {

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    // Drush 8 site
    //$output = $sandbox->exec('ssh -t -o LogLevel=ERROR -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -p 32222 amazeelabsv4-com-prod@ssh.lagoon.amazeeio.cloud drush pm-updatestatus --format=json --full');
    //$output = $sandbox->drush()->pmUpdatestatus('--format=json --full');

    // Drush 9 site
    // # Debug
    // $output = $sandbox->exec('ssh -t -o LogLevel=ERROR -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -p 32222 zhinst-pr-1385@ssh.lagoon.amazeeio.cloud COMPOSER_MEMORY_LIMIT=-1 composer show "drupal/*" -o --no-cache --format=json');
    $output = $sandbox->exec('COMPOSER_MEMORY_LIMIT=-1 composer show "drupal/*" -o --no-cache --format=json');

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
    $installed = $modules["installed"];

    // 'composer show "*/*" -o --format=json' will return an empty installed [] if there are no updates.
    if (empty($installed)) {
      return TRUE;
    }

    $num_modules = count($installed);
    $i = 0;
    foreach ($installed as $key => $module) {
      if (++$i === $num_modules) {
        $installed[$key]['last_item'] = true;
      }
    }

    $sandbox->setParameter('updates', $installed);

    return Audit::FAIL;
  }
}
