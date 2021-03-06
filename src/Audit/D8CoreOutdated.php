<?php


/**
 * Notes: The comparison follow the principles of Semantic Versioning 2.0.0.
 * We compare the current installed version of Drupal core in the format MAJOR.MINOR.
 *
 * Examples (versions as of Sep.2020):
 *   #1
 *   Core installed version: 9.0 , then latest version is 9.0.6
 *   Audit fails in that case.
 *
 *   #2
 * - Core installed version: 8.8.10 , latest version is 8.8.10
 *   Audit succeed.
 *
 *   #3
 * - Core installed version: 8.7.13.
 *   Audit fails, versions prior to 8.8 are not supported.
 *   (see also minsupportversion param)
 *
 *  We do not count alpha, beta, rc and dev releases.
 */

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Token;
use Drutiny\Annotation\Param;
use Drutiny\Target\DrushTarget;

/**
 * Simple Drush Status test.
 *
 * @Param(
 *  name = "minsupportversion",
 *  description = "The versions bellow that are not supported",
 *  type = "string"
 * )
 * @Token(
 *  name = "status",
 *  type = "string",
 *  description = "Compares the installed version of drush with the latest available version"
 * )
 * @Token(
 *  name = "warning_message",
 *  type = "string",
 *  description = "To be used: A warning message to show when Audit::WARNING is returned"
 * )
 */
class D8CoreOutdated extends Audit {

  const STRING_BEGIN = 0;

  /**
   * Array filter to drops out dev, beta versions etc.
   *
   * @param string $str
   *
   * @return bool
   */
  private function versionsFilter($str) {
    // Using strpos as probably is cheaper to evaluate than preg_match.
    return strpos($str, 'dev') === FALSE
    && strpos($str, 'beta') === FALSE
    && strpos($str, 'alpha') === FALSE
    && strpos($str, 'ver') === FALSE
    && strpos($str, 'rc') === FALSE
    && strpos($str, ':') === FALSE;
  }

  /**
   * Clean strings from comma chars.
   *
   * @param string $line
   *
   * @return string
   */
  private function cleanVersionLines($line) {
    return preg_replace('/,/', '', $line);
  }

  /**
   * Returns the latest version of a subversion
   *
   * @param array $versions
   *  A sorted array using semantic version sort
   * @param array $subversion
   *  A subversion to compare with
   * @return string
   */
  private function extractLatestVersion($versions, $subversion) {
    $latest_version = NULL;
    foreach ($versions as $version) {
      if (strpos(trim($version), $subversion) === 0) {
        $latest_version = $version;
        break;
      }
    }
    return $latest_version;
  }

  /**
   * Check that target is actually a DrushTarget
   *
   * @param Sandbox $sandbox
   * @return void
   */
  protected function requireDrushTarget(Sandbox $sandbox){
    return $sandbox->getTarget() instanceof DrushTarget;
  }

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {

    $min_support_version = $sandbox->getParameter('minsupportversion');

    $config = $sandbox->drush(['format' => 'json'])->status();

    if ($config === NULL) {
      return AUDIT::ERROR;
    }

    $current_drupal = isset($config['drupal-version']) ? $config['drupal-version'] : NULL;
    if (!$current_drupal) {
      return Audit::ERROR;
    }

    $method = 'rsort';
    if (!method_exists("Composer\Semver\Semver", $method)) {
      throw new \Exception("Comparator method not available: $method");
    }

    $comparator_method = 'lessThan';
    if (!method_exists("Composer\Semver\Comparator", $comparator_method)) {
      throw new \Exception("Comparator method not available: $comparator_method");
    }

    try {
      $output = $sandbox->exec('COMPOSER_MEMORY_LIMIT=-1 composer show "drupal/core" --all --format=json');
    }
    catch (\Exception $e) {
      throw new \Exception("Composer command failed: " . $e);
      return Audit::ERROR;
    }

    if ($output === NULL) {
      return Audit::ERROR;
    }
    $output = json_decode($output, TRUE);

    // Do some filtering and transformation here.
    $versions = $output['versions'];
    $versions = array_filter($versions, [$this, 'versionsFilter']);
    $versions = array_map([$this, 'cleanVersionLines'], $versions);
    // Just in case sort.
    $versions = call_user_func("Composer\Semver\Semver::$method", $versions);

    if (call_user_func("Composer\Semver\Comparator::$comparator_method", $current_drupal, $min_support_version)) {
      $summary = "You are using the $current_drupal version of Drupal" . PHP_EOL;
      $summary .= "Versions of Drupal 8 prior to $min_support_version are end-of-life" . PHP_EOL;
      $summary .= "and do not receive security coverage." . PHP_EOL;
      $sandbox->setParameter('status', trim($summary));
      return Audit::FAILURE;
    }

    $subver = substr($current_drupal, 0, 3);
    $latest_version = $this->extractLatestVersion($versions,$subver);
    if ($latest_version !== $current_drupal) {
      $msg = "You are NOT using the latest version of Drupal core" . PHP_EOL;
    }
    else {
      $msg = "You are using the latest version of Drupal core" . PHP_EOL;
    }

    $summary = $msg;
    $summary .= "Current\t\t" . $current_drupal . PHP_EOL;
    $summary .= "Latest\t\t" . $latest_version . PHP_EOL;
    $other_version = array_shift($versions);
    if ($other_version !== $latest_version) {
      $summary .= "Also available\t" . $other_version . PHP_EOL;
    }
    $sandbox->setParameter('status', trim($summary));

    if ($latest_version !== $current_drupal) {
      return Audit::FAILURE;
    }

    return Audit::SUCCESS;
  }

}
