<?php

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 *
 */
class FileSystemAnalysis extends Audit {

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    $path = $sandbox->getParameter('path', '%files');
    $stat = $sandbox->drush(['format' => 'json'])->status();

    $path = strtr($path, $stat['%paths']);

    $size = trim($sandbox->exec("du -d 0 -m $path | awk '{print $1}'"));

    $max_size = (int) $sandbox->getParameter('max_size', 20);

    // Set the size in MB for rendering
    $sandbox->setParameter('size', $size);
    // Set the actual path.
    $sandbox->setParameter('path', $path);

    return $size < $max_size;
  }
}