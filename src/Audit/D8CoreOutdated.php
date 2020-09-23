<?php

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Token;

/**
 * Simple Drush Status test.
 *
 * @Param(
 *  name = "minsupportversion",
 *  description = "The versions bellow that are not supported",
 *  type = "string"
 * )
 *
 * @Token(
 *  name = "status",
 *  type = "string",
 *  description = "Compares the installed version of drush with the latest available version"
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
      $output = json_decode($sandbox->exec('COMPOSER_MEMORY_LIMIT=-1 composer show "drupal/core" --all --format=json'), TRUE);
    }
    catch (\Exception $e) {
      throw new \Exception("Composer command failed: " . $e);
      return Audit::ERROR;
    }

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

    $ver = substr($current_drupal, 0, 3);
    foreach ($versions as $version) {
      if (strpos(trim($version), $ver) === 0) {
        $latest_version = $version;
        break;
      }
    }
    reset($versions);

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
