<?php

namespace Drutiny\algm\Audit;

use Drutiny\Annotation\Token;
use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 * Test policy
 */
class TestPolicy extends Audit {

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    $status = $sandbox->drush()->status();

    if ($status === null) {
      return AUDIT::ERROR;
    }

    $sandbox->setParameter('status', $status);
    return Audit::SUCCESS;
  }
}
