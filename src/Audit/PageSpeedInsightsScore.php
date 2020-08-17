<?php

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 *
 */
class PageSpeedInsightsScore extends Audit {

  const THRESHOLD_PERFORMANCE = 0.5;

  const THRESHOLD_ACCESSIBILITY = 0.5;

  const THRESHOLD_SEO = 0.5;

  const PSI_API_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {

    $route = getenv("LAGOON_ROUTE");
    if (empty($route)) {
      return AUDIT::ERROR;
    }

    $queryParams = [
      'fields' => 'lighthouseResult/categories/*/score',
      'strategy' => 'desktop',
      'url' => $route,
    ];

    $categories = '&category=SEO&category=PERFORMANCE&category=ACCESSIBILITY';
    $params = http_build_query($queryParams) . $categories;
    $response = file_get_contents(self::PSI_API_ENDPOINT . '?' . $params);
    $responseObj = json_decode($response, TRUE);

    $performanceScore = $responseObj['lighthouseResult']['categories']['performance']['score'];
    $accessibilityScore = $responseObj['lighthouseResult']['categories']['accessibility']['score'];
    $seoScore = $responseObj['lighthouseResult']['categories']['seo']['score'];

    $sandbox->setParameter('report', [
      'performance' => $performanceScore,
      'seo' => $seoScore,
      'accessibility' => $accessibilityScore,
    ]);

    if ($performanceScore < self::THRESHOLD_PERFORMANCE ||
      $accessibilityScore < self::THRESHOLD_ACCESSIBILITY ||
      $seoScore < self::THRESHOLD_SEO
    ) {
      return Audit::FAIL;
    }

    return Audit::PASS;
  }
}