<?php

// 22/03/2013 (v2.67)
// - New method: ->each(function($fileName, $fileInfo) use ($zip)), works as jQuery.
//   Example: $z->each(function($filename) use ($z){ $z->unzip($filename, "unc/".basename($filename)); });
// 25/07/2012 (v2.664)
// - unzip was NOT respecting chmod parameters, and always setting to 0777. (thanks to Stef Dawson, http://stefdawson.com)
// 19/08/2011 (v2.663)
// - unzipAll was using double slashes (path//filename) to save files. (thanks to Karen Peyton).
// 09/08/2010 (v2.662)
// - unzipAll parameters fully reviewed and fixed. Thanks Ronny Dreschler and Conor Mac Aoidh.
// 12/05/2010 (v2.661)
// - Fixed E_STRICT notice: "Only variables should be passed by reference". Thanks Erik W.
// 24/03/2010 (v2.66)
// - Fixed bug inside unzipAll when dirname is "." (thanks to Thorsten Groth)
// - Added character "¥" to the string conversion table (ex: caixa d¥·gua)
// 27/02/2010
// - Removed PHP4 support (file_put_contents redeclaration).
// 04/12/2009 (v2.65)
// * Added character translation to decode accents and/or special characters.
// 10/11/2009
// * Some security added to avoid malicious ZIP files (relative dirs)
// * unzipAll() will output by default to same folder of the caller script
// 25/09/2009
// - Code optimization to reduce memory usage (uncompress(&$contents))
// 12/07/2009 (2.62)
// - Debug messages are shown only when explicit.
// - New method: getLastError()

##############################################################
# Class dUnzip2 v2.67
#
#  Author: Alexandre Tedeschi (d)
#  E-Mail: alexandrebr at gmail dot com
#  Londrina - PR / Brazil
#
#  Objective:
#    This class allows programmer to easily unzip files on the fly.
#
#  Requirements:
#    This class requires extension ZLib Enabled. It is default
#    for most site hosts around the world, and for the PHP Win32 dist.
#
#  To do:
#   * Error handling
#   * Write a PHP-Side gzinflate, to completely avoid any external extensions
#   * Write other decompress algorithms
#
#  Methods:
#  * dUnzip2($filename)         - Constructor - Opens $filename
#  * each($cbEach)              - Calls $cbEach($filename, $fileinfo) on each compressed file
#  * getList([$stopOnFile])     - Retrieve the file list
#  * getExtraInfo($zipfilename) - Retrieve more information about compressed file
#  * getZipInfo([$entry])       - Retrieve ZIP file details.
#  * unzip($zipfilename, [$outfilename, [$applyChmod]]) - Unzip file
#  * unzipAll([$outDir, [$zipDir, [$maintainStructure, [$applyChmod]]]])
#  * close()                    - Close file handler, but keep the list
#  * __destroy()                - Close file handler and release memory
#
#  If you modify this class, or have any ideas to improve it, please contact me!
#  You are allowed to redistribute this class, if you keep my name and contact e-mail on it.
#
#  PLEASE! IF YOU USE THIS CLASS IN ANY OF YOUR PROJECTS, PLEASE LET ME KNOW!
#  If you have problems using it, don't think twice before contacting me!
#
##############################################################

class dUnzip2 {
	public function getVersion() {
		return '2.67';
	}

	// Public
	public $fileName;

	public $lastError;

	public $compressedList = [];
	// You will problably use only this one!
	public $centralDirList = [];
	// Central dir list... It's a kind of 'extra attributes' for a set of files
	public $endOfCentral = [];
	// End of central dir, contains ZIP Comments
	public $debug;

	// Private
	private $fh;

	private $zipSignature = "\x50\x4b\x03\x04";
	// local file header signature
	private $dirSignature = "\x50\x4b\x01\x02";
	// central dir header signature
	private $dirSignatureE = "\x50\x4b\x05\x06"; // end of central dir signature

	public function __construct($fileName) {
		$this->fileName       = $fileName;
	}

	public function getList($stopOnFile = false) {
		if(count($this->compressedList) !== 0) {
			$this->debugMsg(1, 'Returning already loaded file list.');
			return $this->compressedList;
		}

		// Open file, and set file handler
		$fh = fopen($this->fileName, 'r');
		$this->fh = &$fh;
		if(! $fh) {
			$this->debugMsg(2, 'Failed to load file.');
			return false;
		}

		$this->debugMsg(1, "Loading list from 'End of Central Dir' index list...");
		if(! $this->_loadFileListByEOF($fh, $stopOnFile)) {
			$this->debugMsg(1, 'Failed! Trying to load list looking for signatures...');
			if(! $this->_loadFileListBySignatures($fh, $stopOnFile)) {
				$this->debugMsg(1, 'Failed! Could not find any valid header.');
				$this->debugMsg(2, 'ZIP File is corrupted or empty');
				return false;
			}
		}

		if($this->debug) {
			#------- Debug compressedList
			$isHeader = true;
			echo "<table border='0' style='font: 11px Verdana; border: 1px solid #000'>";
			foreach($this->compressedList as $item) {
				if($isHeader) {
					echo "<tr style='background: #ADA'>";
					foreach($item as $fieldName => $value) {
						echo sprintf('<td>%s</td>', $fieldName);
					}

					echo '</tr>';

					$isHeader = false;
				}

				echo "<tr style='background: #CFC'>";
				foreach($item as $fieldName => $value) {
					if($fieldName == 'lastmod_datetime') {
						echo sprintf('<td title=\'%s\' nowrap=\'nowrap\'>', $fieldName).date('d/m/Y H:i:s', $value).'</td>';
					} else {
						echo sprintf('<td title=\'%s\' nowrap=\'nowrap\'>%s</td>', $fieldName, $value);
					}
				}

				echo '</tr>';
			}

			echo '</table>';

			#------- Debug centralDirList
			$isHeader = true;
			if(count($this->centralDirList) !== 0) {
				echo "<table border='0' style='font: 11px Verdana; border: 1px solid #000'>";
				foreach($this->centralDirList as $item) {
					if($isHeader) {
						echo "<tr style='background: #AAD'>";
						foreach($item as $fieldName => $value) {
							echo sprintf('<td>%s</td>', $fieldName);
						}

						echo '</tr>';

						$isHeader = false;
					}

					echo "<tr style='background: #CCF'>";
					foreach($item as $fieldName => $value) {
						if($fieldName == 'lastmod_datetime') {
							echo sprintf('<td title=\'%s\' nowrap=\'nowrap\'>', $fieldName).date('d/m/Y H:i:s', $value).'</td>';
						} else {
							echo sprintf('<td title=\'%s\' nowrap=\'nowrap\'>%s</td>', $fieldName, $value);
						}
					}

					echo '</tr>';
				}

				echo '</table>';
			}

			#------- Debug endOfCentral
			if(count($this->endOfCentral) !== 0) {
				echo "<table border='0' style='font: 11px Verdana' style='border: 1px solid #000'>";
				echo "<tr style='background: #DAA'><td colspan='2'>dUnzip - End of file</td></tr>";
				foreach($this->endOfCentral as $field => $value) {
					echo '<tr>';
					echo sprintf('<td style=\'background: #FCC\'>%s</td>', $field);
					echo sprintf('<td style=\'background: #FDD\'>%s</td>', $value);
					echo '</tr>';
				}

				echo '</table>';
			}
		}

		return $this->compressedList;
	}

	public function getExtraInfo($compressedFileName) {
		return
			isset($this->centralDirList[$compressedFileName]) ?
			$this->centralDirList[$compressedFileName] :
			false;
	}

	public function getZipInfo($detail = false) {
		return $detail ?
			$this->endOfCentral[$detail] :
			$this->endOfCentral;
	}

	public function each($cbEachCompreesedFile) {
		// cbEachCompreesedFile(filename, fileinfo);
		if(! is_callable($cbEachCompreesedFile)) {
			die("dUnzip2: You called 'each' method, but failed to provide an Callback as argument. Usage: \$zip->each(function(\$filename, \$fileinfo) use (\$zip){ ... \$zip->unzip(\$filename, 'uncompress/\$filename'); }).");
		}

		$lista = $this->getList();
		if(count($lista) !== 0) {
			foreach($lista as $fileName => $fileInfo) {
				if(false === call_user_func($cbEachCompreesedFile, $fileName, $fileInfo)) {
					return false;
				}
			}
		}

		return true;
	}

	public function unzip($compressedFileName, $targetFileName = false, $applyChmod = 0777) {
		if(count($this->compressedList) === 0) {
			$this->debugMsg(1, 'Trying to unzip before loading file list... Loading it!');
			$this->getList(false);
		}

		$fdetails = &$this->compressedList[$compressedFileName];
		if(! isset($this->compressedList[$compressedFileName])) {
			$this->debugMsg(2, sprintf('File \'<b>%s</b>\' is not compressed in the zip.', $compressedFileName));
			return false;
		}

		if(substr($compressedFileName, -1) === '/') {
			$this->debugMsg(2, sprintf('Trying to unzip a folder name \'<b>%s</b>\'.', $compressedFileName));
			return false;
		}

		if(! $fdetails['uncompressed_size']) {
			$this->debugMsg(1, sprintf('File \'<b>%s</b>\' is empty.', $compressedFileName));
			return $targetFileName ?
				$this->saveFile($targetFileName, '', $applyChmod) :
				'';
		}

		fseek($this->fh, $fdetails['contents-startOffset']);
		$toUncompress = fread($this->fh, $fdetails['compressed_size']);
		$ret = $this->uncompress(
			$toUncompress,
			$fdetails['compression_method'],
			$fdetails['uncompressed_size'],
			$targetFileName,
			$applyChmod
		);

		unset($toUncompress);

		return $ret;
	}

	public function unzipAll($targetDir = false, $baseDir = '', $maintainStructure = true, $applyChmod = 0777) {
		if($targetDir === false) {
			$targetDir = dirname($_SERVER['SCRIPT_FILENAME']).'/';
		}

		if(substr($targetDir, -1) === '/') {
			$targetDir = substr($targetDir, 0, -1);
		}

		$lista = $this->getList();
		if(count($lista) !== 0) {
			foreach($lista as $fileName => $trash) {
				$dirname  = dirname($fileName);
				$outDN    = sprintf('%s/%s', $targetDir, $dirname);

				if(substr($dirname, 0, strlen($baseDir)) != $baseDir) {
					continue;
				}

				if(! is_dir($outDN) && $maintainStructure) {
					$str = '';
					$folders = explode('/', $dirname);
					foreach($folders as $folder) {
						$str = $str !== '' && $str !== '0' ? sprintf('%s/%s', $str, $folder) : $folder;
						if(! is_dir(sprintf('%s/%s', $targetDir, $str))) {
							$this->debugMsg(1, sprintf('Creating folder: %s/%s', $targetDir, $str));
							mkdir(sprintf('%s/%s', $targetDir, $str), $applyChmod, true);
						}
					}
				}

				if(substr($fileName, -1, 1) === '/') {
					continue;
				}

				$maintainStructure ?
					$this->unzip($fileName, sprintf('%s/%s', $targetDir, $fileName), $applyChmod) :
					$this->unzip($fileName, $targetDir . '/'.basename($fileName), $applyChmod);
			}
		}
	}

	public function close() {     // Free the file resource
		if($this->fh) {
			fclose($this->fh);
		}
	}

	public function __destroy() {
		$this->close();
	}

	private function saveFile($filename, $content, $applyChmod = 0777) {
		$ok = @touch($filename);
		if(! $ok) {
			throw new Exception(sprintf('Failed to create file: %s', $filename));
		}

		$ok = @chmod($filename, $applyChmod);
		if(! $ok) {
			throw new Exception(sprintf('Failed to chmod file: %s with permission %s', $filename, $applyChmod));
		}

		$bytesWritten = @file_put_contents($filename, $content);
		if($bytesWritten === false) {
			throw new Exception(sprintf('Failed to write file: %s', $filename));
		}

		$this->debugMsg(2, sprintf('File <b>%s</b> saved with %d bytes.', $filename, $bytesWritten));
	}

	private function uncompress(&$content, $mode, $uncompressedSize, $targetFileName = false, $applyChmod = 0777) {
		switch($mode) {
			case 0:
				// Not compressed
				return $targetFileName ?
					$this->saveFile($targetFileName, $content, $applyChmod) :
					$content;
			case 1:
				$this->debugMsg(2, 'Shrunk mode is not supported... yet?');
				return false;
			case 2:
			case 3:
			case 4:
			case 5:
				$this->debugMsg(2, 'Compression factor '.($mode - 1).' is not supported... yet?');
				return false;
			case 6:
				$this->debugMsg(2, 'Implode is not supported... yet?');
				return false;
			case 7:
				$this->debugMsg(2, 'Tokenizing compression algorithm is not supported... yet?');
				return false;
			case 8:
				// Deflate
				return $targetFileName ?
					$this->saveFile($targetFileName, gzinflate($content, $uncompressedSize), $applyChmod) :
					gzinflate($content, $uncompressedSize);
			case 9:
				$this->debugMsg(2, 'Enhanced Deflating is not supported... yet?');
				return false;
			case 10:
				$this->debugMsg(2, 'PKWARE Date Compression Library Impoloding is not supported... yet?');
				return false;
			case 12:
				// Bzip2
				return $targetFileName ?
				   $this->saveFile($targetFileName, bzdecompress($content), $applyChmod) :
				   bzdecompress($content);
			case 18:
				$this->debugMsg(2, 'IBM TERSE is not supported... yet?');
				return false;
			default:
				$this->debugMsg(2, 'Unknown uncompress method: ' . $mode);
				return false;
		}
	}

	public function debugMsg($level, $string) {
		if($this->debug) {
			if($level == 1) {
				echo sprintf('<b style=\'color: #777\'>dUnzip2:</b> %s<br>', $string);
			}

			if($level == 2) {
				echo sprintf('<b style=\'color: #F00\'>dUnzip2:</b> %s<br>', $string);
			}
		}

		$this->lastError = $string;
	}

	public function getLastError() {
		return $this->lastError;
	}

	public function _loadFileListByEOF(&$fh, $stopOnFile = false) {
		// Check if there's a valid Central Dir signature.
		// Let's consider a file comment smaller than 1024 characters...
		// Actually, it length can be 65536.. But we're not going to support it.

		for($x = 0; $x < 1024; $x++) {
			fseek($fh, -22 - $x, SEEK_END);

			$signature = fread($fh, 4);
			if($signature == $this->dirSignatureE) {
				// If found EOF Central Dir
				$eodir['disk_number_this']   = unpack('v', fread($fh, 2)); // number of this disk
				$eodir['disk_number']        = unpack('v', fread($fh, 2)); // number of the disk with the start of the central directory
				$eodir['total_entries_this'] = unpack('v', fread($fh, 2)); // total number of entries in the central dir on this disk
				$eodir['total_entries']      = unpack('v', fread($fh, 2)); // total number of entries in
				$eodir['size_of_cd']         = unpack('V', fread($fh, 4)); // size of the central directory
				$eodir['offset_start_cd']    = unpack('V', fread($fh, 4)); // offset of start of central directory with respect to the starting disk number
				$zipFileCommentLenght        = unpack('v', fread($fh, 2)); // zipfile comment length
				$eodir['zipfile_comment']    = $zipFileCommentLenght[1] ? fread($fh, $zipFileCommentLenght[1]) : ''; // zipfile comment
				$this->endOfCentral = [
					'disk_number_this' => $eodir['disk_number_this'][1],
					'disk_number' => $eodir['disk_number'][1],
					'total_entries_this' => $eodir['total_entries_this'][1],
					'total_entries' => $eodir['total_entries'][1],
					'size_of_cd' => $eodir['size_of_cd'][1],
					'offset_start_cd' => $eodir['offset_start_cd'][1],
					'zipfile_comment' => $eodir['zipfile_comment'],
				];

				// Then, load file list
				fseek($fh, $this->endOfCentral['offset_start_cd']);
				$signature = fread($fh, 4);

				while($signature == $this->dirSignature) {
					$dir['version_madeby']      = unpack('v', fread($fh, 2)); // version made by
					$dir['version_needed']      = unpack('v', fread($fh, 2)); // version needed to extract
					$dir['general_bit_flag']    = unpack('v', fread($fh, 2)); // general purpose bit flag
					$dir['compression_method']  = unpack('v', fread($fh, 2)); // compression method
					$dir['lastmod_time']        = unpack('v', fread($fh, 2)); // last mod file time
					$dir['lastmod_date']        = unpack('v', fread($fh, 2)); // last mod file date
					$dir['crc-32']              = fread($fh, 4);              // crc-32
					$dir['compressed_size']     = unpack('V', fread($fh, 4)); // compressed size
					$dir['uncompressed_size']   = unpack('V', fread($fh, 4)); // uncompressed size
					$fileNameLength             = unpack('v', fread($fh, 2)); // filename length
					$extraFieldLength           = unpack('v', fread($fh, 2)); // extra field length
					$fileCommentLength          = unpack('v', fread($fh, 2)); // file comment length
					$dir['disk_number_start']   = unpack('v', fread($fh, 2)); // disk number start
					$dir['internal_attributes'] = unpack('v', fread($fh, 2)); // internal file attributes-byte1
					$dir['external_attributes1'] = unpack('v', fread($fh, 2)); // external file attributes-byte2
					$dir['external_attributes2'] = unpack('v', fread($fh, 2)); // external file attributes
					$dir['relative_offset']     = unpack('V', fread($fh, 4)); // relative offset of local header
					$dir['file_name']           = fread($fh, $fileNameLength[1]);                             // filename
					$dir['extra_field']         = $extraFieldLength[1] ? fread($fh, $extraFieldLength[1]) : ''; // extra field
					$dir['file_comment']        = $fileCommentLength[1] ? fread($fh, $fileCommentLength[1]) : ''; // file comment

					// Convert the date and time, from MS-DOS format to UNIX Timestamp
					$BINlastmod_date = str_pad(decbin($dir['lastmod_date'][1]), 16, '0', STR_PAD_LEFT);
					$BINlastmod_time = str_pad(decbin($dir['lastmod_time'][1]), 16, '0', STR_PAD_LEFT);
					$lastmod_dateY = bindec(substr($BINlastmod_date, 0, 7)) + 1980;
					$lastmod_dateM = bindec(substr($BINlastmod_date, 7, 4));
					$lastmod_dateD = bindec(substr($BINlastmod_date, 11, 5));
					$lastmod_timeH = bindec(substr($BINlastmod_time, 0, 5));
					$lastmod_timeM = bindec(substr($BINlastmod_time, 5, 6));
					$lastmod_timeS = bindec(substr($BINlastmod_time, 11, 5));

					// Some protection agains attacks...
					$dir['file_name']     = $this->_decodeFilename($dir['file_name']);
					if(! $dir['file_name'] = $this->_protect($dir['file_name'])) {
						continue;
					}

					$this->centralDirList[$dir['file_name']] = [
						'version_madeby' => $dir['version_madeby'][1],
						'version_needed' => $dir['version_needed'][1],
						'general_bit_flag' => str_pad(decbin($dir['general_bit_flag'][1]), 8, '0', STR_PAD_LEFT),
						'compression_method' => $dir['compression_method'][1],
						'lastmod_datetime'  => mktime($lastmod_timeH, $lastmod_timeM, $lastmod_timeS, $lastmod_dateM, $lastmod_dateD, $lastmod_dateY),
						'crc-32'            => str_pad(dechex(ord($dir['crc-32'][3])), 2, '0', STR_PAD_LEFT).
											  str_pad(dechex(ord($dir['crc-32'][2])), 2, '0', STR_PAD_LEFT).
											  str_pad(dechex(ord($dir['crc-32'][1])), 2, '0', STR_PAD_LEFT).
											  str_pad(dechex(ord($dir['crc-32'][0])), 2, '0', STR_PAD_LEFT),
						'compressed_size' => $dir['compressed_size'][1],
						'uncompressed_size' => $dir['uncompressed_size'][1],
						'disk_number_start' => $dir['disk_number_start'][1],
						'internal_attributes' => $dir['internal_attributes'][1],
						'external_attributes1' => $dir['external_attributes1'][1],
						'external_attributes2' => $dir['external_attributes2'][1],
						'relative_offset' => $dir['relative_offset'][1],
						'file_name' => $dir['file_name'],
						'extra_field' => $dir['extra_field'],
						'file_comment' => $dir['file_comment'],
					];
					$signature = fread($fh, 4);
				}

				// If loaded centralDirs, then try to identify the offsetPosition of the compressed data.
				if($this->centralDirList) {
					foreach($this->centralDirList as $filename => $details) {
						$i = $this->_getFileHeaderInformation($fh, $details['relative_offset']);
						$this->compressedList[$filename]['file_name']          = $filename;
						$this->compressedList[$filename]['compression_method'] = $details['compression_method'];
						$this->compressedList[$filename]['version_needed']     = $details['version_needed'];
						$this->compressedList[$filename]['lastmod_datetime']   = $details['lastmod_datetime'];
						$this->compressedList[$filename]['crc-32']             = $details['crc-32'];
						$this->compressedList[$filename]['compressed_size']    = $details['compressed_size'];
						$this->compressedList[$filename]['uncompressed_size']  = $details['uncompressed_size'];
						$this->compressedList[$filename]['lastmod_datetime']   = $details['lastmod_datetime'];
						$this->compressedList[$filename]['extra_field']        = $i['extra_field'];
						$this->compressedList[$filename]['contents-startOffset'] = $i['contents-startOffset'];
						if(strtolower($stopOnFile) === strtolower($filename)) {
							break;
						}
					}
				}

				return true;
			}
		}

		return false;
	}

	public function _loadFileListBySignatures(&$fh, $stopOnFile = false) {
		fseek($fh, 0);

		$return = false;
		for(;;) {
			$details = $this->_getFileHeaderInformation($fh);
			if(! $details) {
				$this->debugMsg(1, 'Invalid signature. Trying to verify if is old style Data Descriptor...');
				fseek($fh, 12 - 4, SEEK_CUR); // 12: Data descriptor - 4: Signature (that will be read again)
				$details = $this->_getFileHeaderInformation($fh);
			}

			if(! $details) {
				$this->debugMsg(1, 'Still invalid signature. Probably reached the end of the file.');
				break;
			}

			$filename = $details['file_name'];
			$this->compressedList[$filename] = $details;
			$return = true;
			if(strtolower($stopOnFile) === strtolower($filename)) {
				break;
			}
		}

		return $return;
	}

	public function _getFileHeaderInformation(&$fh, $startOffset = false) {
		if($startOffset !== false) {
			fseek($fh, $startOffset);
		}

		$signature = fread($fh, 4);
		if($signature == $this->zipSignature) {
			# $this->debugMsg(1, "Zip Signature!");

			// Get information about the zipped file
			$file['version_needed']     = unpack('v', fread($fh, 2)); // version needed to extract
			$file['general_bit_flag']   = unpack('v', fread($fh, 2)); // general purpose bit flag
			$file['compression_method'] = unpack('v', fread($fh, 2)); // compression method
			$file['lastmod_time']       = unpack('v', fread($fh, 2)); // last mod file time
			$file['lastmod_date']       = unpack('v', fread($fh, 2)); // last mod file date
			$file['crc-32']             = fread($fh, 4);              // crc-32
			$file['compressed_size']    = unpack('V', fread($fh, 4)); // compressed size
			$file['uncompressed_size']  = unpack('V', fread($fh, 4)); // uncompressed size
			$fileNameLength             = unpack('v', fread($fh, 2)); // filename length
			$extraFieldLength           = unpack('v', fread($fh, 2)); // extra field length
			$file['file_name']          = fread($fh, $fileNameLength[1]); // filename
			$file['extra_field']        = $extraFieldLength[1] ? fread($fh, $extraFieldLength[1]) : ''; // extra field
			$file['contents-startOffset'] = ftell($fh);

			// Bypass the whole compressed contents, and look for the next file
			fseek($fh, $file['compressed_size'][1], SEEK_CUR);

			// Convert the date and time, from MS-DOS format to UNIX Timestamp
			$BINlastmod_date = str_pad(decbin($file['lastmod_date'][1]), 16, '0', STR_PAD_LEFT);
			$BINlastmod_time = str_pad(decbin($file['lastmod_time'][1]), 16, '0', STR_PAD_LEFT);
			$lastmod_dateY = bindec(substr($BINlastmod_date, 0, 7)) + 1980;
			$lastmod_dateM = bindec(substr($BINlastmod_date, 7, 4));
			$lastmod_dateD = bindec(substr($BINlastmod_date, 11, 5));
			$lastmod_timeH = bindec(substr($BINlastmod_time, 0, 5));
			$lastmod_timeM = bindec(substr($BINlastmod_time, 5, 6));
			$lastmod_timeS = bindec(substr($BINlastmod_time, 11, 5));

			// Some protection agains attacks...
			$file['file_name']     = $this->_decodeFilename($file['file_name']);
			if(! $file['file_name'] = $this->_protect($file['file_name'])) {
				return false;
			}

			// Mount file table
			$i = [
				'file_name'         => $file['file_name'],
				'compression_method' => $file['compression_method'][1],
				'version_needed'    => $file['version_needed'][1],
				'lastmod_datetime'  => mktime($lastmod_timeH, $lastmod_timeM, $lastmod_timeS, $lastmod_dateM, $lastmod_dateD, $lastmod_dateY),
				'crc-32'            => str_pad(dechex(ord($file['crc-32'][3])), 2, '0', STR_PAD_LEFT).
									  str_pad(dechex(ord($file['crc-32'][2])), 2, '0', STR_PAD_LEFT).
									  str_pad(dechex(ord($file['crc-32'][1])), 2, '0', STR_PAD_LEFT).
									  str_pad(dechex(ord($file['crc-32'][0])), 2, '0', STR_PAD_LEFT),
				'compressed_size'   => $file['compressed_size'][1],
				'uncompressed_size' => $file['uncompressed_size'][1],
				'extra_field'       => $file['extra_field'],
				'general_bit_flag'  => str_pad(decbin($file['general_bit_flag'][1]), 8, '0', STR_PAD_LEFT),
				'contents-startOffset' => $file['contents-startOffset'],
			];
			return $i;
		}

		return false;
	}

	public function _decodeFilename($filename) {
		$from = "\xb7\xb5\xb6\xc7\x8e\x8f\x92\x80\xd4\x90\xd2\xd3\xde\xd6\xd7\xd8\xd1\xa5\xe3\xe0".
				"\xe2\xe5\x99\x9d\xeb\xe9\xea\x9a\xed\xe8\xe1\x85\xa0\x83\xc6\x84\x86\x91\x87\x8a".
				"\x82\x88\x89\x8d\xa1\x8c\x8b\xd0\xa4\x95\xa2\x93\xe4\x94\x9b\x97\xa3\x96\xec\xe7".
				"\x98Ô";
		$to   = '¿¡¬√ƒ≈∆«»… ÀÃÕŒœ–—“”‘’÷ÿŸ⁄€‹›ﬁﬂ‡·‚„‰ÂÊÁËÈÍÎÏÌÓÔÒÚÛÙıˆ¯˘˙˚˝˛ˇ¥';

		return strtr($filename, $from, $to);
	}

	public function _protect($fullPath) {
		// Known hack-attacks (filename like):
		//   /home/usr
		//   ../../home/usr
		//   folder/../../../home/usr
		//   sample/(x0)../home/usr

		$fullPath = strtr($fullPath, ":*<>|\"\x0\\", '......./');
		while($fullPath[0] === '/') {
			$fullPath = substr($fullPath, 1);
		}

		if(substr($fullPath, -1) === '/') {
			$base     = '';
			$fullPath = substr($fullPath, 0, -1);
		} else {
			$base     = basename($fullPath);
			$fullPath = dirname($fullPath);
		}

		$parts   = explode('/', $fullPath);
		$lastIdx = false;
		foreach($parts as $idx => $part) {
			if($part === '.') {
				unset($parts[$idx]);
			} elseif($part === '..') {
				unset($parts[$idx]);
				if($lastIdx !== false) {
					unset($parts[$lastIdx]);
				}
			} elseif($part === '') {
				unset($parts[$idx]);
			} else {
				$lastIdx = $idx;
			}
		}

		$fullPath = $parts !== [] ? implode('/', $parts).'/' : '';
		return $fullPath.$base;
	}
}
