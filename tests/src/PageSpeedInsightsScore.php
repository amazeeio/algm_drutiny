<?php

namespace DrutinyTests\Audit;

use Drutiny\algm\Audit\ClamAVScan;
use Drutiny\Container;
use Drutiny\Policy;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Target\Registry as TargetRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PageSpeedInsightsScore extends TestCase {

  protected $target;

  public function __construct()
  {
    Container::setLogger(new NullLogger());
    $this->target = TargetRegistry::getTarget('none', '');
    parent::__construct();
  }

  /** @test */
  public function it_should_run_a_psi_scan_against_a_site() {
    $policy = Policy::load('ALGMPerformance:PSI');
    $sandbox = new Sandbox($this->target, $policy);

    $response = $sandbox->run();
    var_dump($response->isSuccessful());
    var_dump($response->getFailure());
    $this->assertTrue($response->isSuccessful());
  }

}
