<?php

namespace AlexandreTedeschi\dUnzip2\Tests;

use dUnzip2;
use PHPUnit\Framework\TestCase;

class dUnzip2Test extends TestCase {
	private $srcFile = __DIR__ . '/./example.zip';

	private $srcUnzip = __DIR__ . '/./unzip/';

	public function testInstance() {
		$this->assertInstanceOf(dUnzip2::class, new dUnzip2(null));
	}

	public function testExtract() {
		$this->assertFileExists($this->srcFile);
		$this->assertDirectoryExists($this->srcUnzip);

		$zip = new dUnzip2($this->srcFile);
		$zip->unzipAll($this->srcUnzip);
		$this->assertFiles(777);
	}

	public function testExtract0755() {
		$this->assertFileExists($this->srcFile);
		$this->assertDirectoryExists($this->srcUnzip);

		$zip = new dUnzip2($this->srcFile);
		$zip->unzipAll($this->srcUnzip, '', true, 0755);
		$this->assertFiles(755);
	}

	public function testExtract0655() {
		$this->assertFileExists($this->srcFile);
		$this->assertDirectoryExists($this->srcUnzip);

		$zip = new dUnzip2($this->srcFile);
		$zip->unzipAll($this->srcUnzip, '', true, 0655);
		$this->assertFiles(655);
	}

	private function assertFiles($expectedPermisson) {
		$file1 = $this->srcUnzip . 'example.txt';
		$this->assertFile($file1, $expectedPermisson);

		$file2 = $this->srcUnzip . 'example2.txt';
		$this->assertFile($file2, $expectedPermisson);

		unlink($file1);
		unlink($file2);
	}

	private function assertFile($file, $expectedPermisson) {
		$this->assertFileExists($file);
		$this->assertFileIsWritable($file);
		$this->assertChmod($file, $expectedPermisson);
	}

	private function assertChmod($fileToCreate, $expectedPermisson) {
		$perms = fileperms($fileToCreate);
		$filePermisson = substr(sprintf('%o', $perms), - 4);
		$this->assertEquals($expectedPermisson, $filePermisson, sprintf('File permission \'%s\' is not \'%d\'.', $perms, $expectedPermisson));
	}

}
