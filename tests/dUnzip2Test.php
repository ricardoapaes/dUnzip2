<?php

namespace AlexandreTedeschi\dUnzip2\Tests;

use PHPUnit\Framework\TestCase;
use AlexandreTedeschi\dUnzip2\dUnzip2;

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

	private function assertFiles(int $expectedPermisson) {
		$file1 = $this->srcUnzip . 'example.txt';
		$this->assertFile($file1, $expectedPermisson);

		$file2 = $this->srcUnzip . 'example2.txt';
		$this->assertFile($file2, $expectedPermisson);

		unlink($file1);
		unlink($file2);
	}

	private function assertFile(string $file, int $expectedPermisson) {
		$this->assertFileExists($file);
		$this->assertFileIsWritable($file);
		$this->assertChmod($file, $expectedPermisson);
	}

	private function assertChmod(string $fileToCreate, int $expectedPermisson) {
		$perms = fileperms($fileToCreate);
		$filePermisson = substr(sprintf('%o', $perms), - 4 );
		$this->assertEquals($expectedPermisson, $filePermisson, "File permission '$perms' is not '$expectedPermisson'.");
	}

}
