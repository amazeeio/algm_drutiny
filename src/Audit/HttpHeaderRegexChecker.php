<?php

namespace Drutiny\algm\Audit;

use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Param;


/**
 *
 * @Param(
 *  name = "header",
 *  description = "The HTTP header to check the value of.",
 *  type = "string"
 * )
 * @Param(
 *  name = "regex",
 *  description = "A regular expressions to validate the header value against.",
 *  type = "string"
 * )
 */
class HttpHeaderRegexChecker extends \Drutiny\Http\Audit\Http {

  /**
   *
   */
  public function audit(Sandbox $sandbox)
  {
    $stats = $sandbox->drush([
      'format' => 'json',
    ])->status();

    $uri = $sandbox->getTarget()->uri();
    if (isset($stats['uri'])) {
      $uri = $stats['uri'];
    }

    $setUrl = $sandbox->getTarget()->setUri($uri);

    $regex = $sandbox->getParameter('regex');
    $regex = "/$regex/";
    $res = $this->getHttpResponse($sandbox);
    $header = $sandbox->getParameter('header');

    if (!$res->hasHeader($header)) {
      return FALSE;
    }
    $headers = $res->getHeader($header);
    return preg_match($regex, $headers[0]);
  }
}

?>
