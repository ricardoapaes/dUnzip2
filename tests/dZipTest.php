<?php

namespace AlexandreTedeschi\dUnzip2\Tests;

use PHPUnit\Framework\TestCase;
use AlexandreTedeschi\dUnzip2\dZip;

class dZipTest extends TestCase {
	public function testInstance() {
		$this->assertInstanceOf(dZip::class, new dZip());
	}
}
