<?php

namespace Drutiny\algm\Audit;

use Drutiny\Annotation\Token;
use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 * Simple Drush Status test
 *
 * @Token(
 *  name = "health_status",
 *  type = "string",
 *  description = "Results from the health check"
 * )
 */
class HealthCheck extends Audit {

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    $status = $sandbox->drush()->status();

    $lines = explode(PHP_EOL, $status);
    $lines = array_map('trim', $lines);

    $bootstrap_health = '';
    foreach($lines as $key => $line) {
        if(preg_match("/\bDrupal bootstrap\b/i", $line)) {
            $bootstrap_health = $line;
        }
    }

    if ($bootstrap_health === null) {
      return AUDIT::NOTICE;
    }

    $sandbox->setParameter('health_status', $bootstrap_health);

    return Audit::SUCCESS;
  }
}
