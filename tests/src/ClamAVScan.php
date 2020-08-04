<?php

namespace DrutinyTests\Audit;

use Drutiny\algm\Audit\ClamAVScan;
use Drutiny\Container;
use Drutiny\Policy;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Target\Registry as TargetRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ClamAVScanTest extends TestCase {

  protected $target;

  public function __construct()
  {
    Container::setLogger(new NullLogger());
    $this->target = TargetRegistry::getTarget('none', '');
    parent::__construct();
  }

  public function testPass()
  {
    $policy = Policy::load('ClamAV:ClamAVScan');
    $sandbox = new Sandbox($this->target, $policy);

    // Set scan directory.
    $sandbox->setParameter('scan_directory', './src');

    $response = $sandbox->run();
    $success_message = $response->getSuccess();

    $this->assertContains('Success: There have been no infected files found', $success_message);
    $this->assertTrue($response->isSuccessful());
  }

  public function testFailure()
  {
    // A fake malicious file has been placed inside /tests/src/Data to trigger a failure.
    $policy = Policy::load('ClamAV:ClamAVScan');
    $sandbox = new Sandbox($this->target, $policy);

    $sandbox->setParameter('scan_directory', './tests');

    $response = $sandbox->run();
    $failure_message = $response->getFailure();

    $this->assertContains('Warning: Infected files have been found', $failure_message);
    $this->assertFalse($response->isSuccessful());
  }
}
