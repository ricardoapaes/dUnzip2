<?php

namespace AlexandreTedeschi\dUnzip2\Tests;

use PHPUnit\Framework\TestCase;
use AlexandreTedeschi\dUnzip2\dUnzip2;

class dUnzip2Test extends TestCase {
	public function testInstance() {
		$this->assertInstanceOf(dUnzip2::class, new dUnzip2());
	}
}
