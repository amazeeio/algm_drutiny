<?php

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Param;
use Exception;

/**
 * Check the version of Drupal core or a module in a site.
 *
 * @Param(
 *  name = "module",
 *  description = "The module to version information for",
 *  type = "string"
 * )
 * @Param(
 *  name = "version",
 *  description = "The static version to check against.",
 *  type = "string"
 * )
 * @Param(
 *  name = "comparator",
 *  description = "How to compare the version (greaterThan, greaterThanOrEqualTo, lessThan etc. See https://github.com/composer/semver)",
 *  type = "string",
 *  default = "greaterThanOrEqualTo"
 * )
 */
class ModuleVersion extends Audit {

  public function audit(Sandbox $sandbox)
  {
    $module = $sandbox->getParameter('module');
    $version = $sandbox->getParameter('version');
    $comparator_method = $sandbox->getParameter('comparator');

    if (!method_exists("Composer\Semver\Comparator", $comparator_method)) {
      throw new \Exception("Comparator method not available: $comparator_method");
    }

    try {
      $info = $sandbox->drush(['format' => 'json'])->pmList();
    }
    catch (Exception $e) {
      throw new \Exception("Drush command failed: " . $e);
      return Audit::ERROR;
    }


    if (!isset($info[$module])) {
      return Audit::NOT_APPLICABLE;
    }

    $current_version = strtolower($info[$module]['version']);
    $sandbox->setParameter('current_version', $current_version);

    $sandbox->logger()->info("$comparator_method($current_version, $version)");

    return call_user_func("Composer\Semver\Comparator::$comparator_method", $current_version, $version);
  }
}