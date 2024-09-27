<?php

namespace AlexandreTedeschi\dUnzip2\Tests;

use AlexandreTedeschi\dUnzip2\dUnzip2;
use PHPUnit\Framework\TestCase;
use AlexandreTedeschi\dUnzip2\dZip;

class dZipTest extends TestCase {
	private $srcFile = __DIR__ . '/../README.md';
	private $srcZip = __DIR__ . '/./unzip/example.zip';

	public function testInstance() {
		$this->assertInstanceOf(dZip::class, new dZip(''));
	}

	public function testZip() {
		$zip = new dZip($this->srcZip);
		$zip->addFile($this->srcFile, 'README.md', 'This is a test file');
		$zip->save();

		$this->assertFileExists($this->srcZip);

		$zip = new dUnzip2($this->srcZip);
		$list = $zip->getList();

		$this->assertCount(1, $list);
		$this->assertArrayHasKey('README.md', $list);
		$this->assertEquals('README.md', $list['README.md']['file_name']);

		unlink($this->srcZip);
	}
}