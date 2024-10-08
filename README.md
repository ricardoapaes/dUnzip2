# dUnzip2

> Originally taken from phpclasses.org, made by Alexandre Tedeschi. I will leave the link below for consultation.<br>[dUnzip2: Pack and unpack files packed in ZIP archives](https://www.phpclasses.org/package/2495-PHP-Pack-and-unpack-files-packed-in-ZIP-archives.html)

Pack and unpack files packed in ZIP archives
This package can be used to pack and unpack files in ZIP archives.

There is a class that can retrieve the list of packed files as well several types of file details, such as the uncompressed size, last modification time, comments, etc..

The class can extract individual files, one at a time, specifying their file names, or extract all at once into a given directory.

There is another class that can pack files into new ZIP archives.

The classes use the usual PHP file access functions and gzip extension functions.

--------------------
  How does it work
--------------------
] The class open the give ZIP File, and move the internal pointer
] to list all the files zipped on it. The returned values are
] stored in the public variable 'compressedList'. These can be
] returned by the public method 'getList()'.
] 
] It also has Central Directory support. Means that the ZIP may contain
] additional information for each file or directory, like external
] attributes and comments. The variables are stored in the public
] variable 'dirSignature'. These should be returned for each file
] by using the public method 'getExtraInfo(fileName)'. If no extra
] information available, the method will return 'false'.
]
] After getting file list, you may want to unzip some of them. There are
] two ways to do this... They are descripted below.
]
] You can also get ZIP file details like file comments by calling the
] method 'getZipInfo([detail])'. If you give 'detail' var, only the
] given information will be returned, else it will return everything.
] 
] Warning: To use the ZIP file by another script, you must first free it
] from the dUnzip2 class. For this, call the method 'close()'... It is
] auto-called on class destroyed (if PHP5), and terminate the file handler.

-------------------
  Unzipping files
-------------------
- Method 'unzip(fileName[, targetFileName])'
] Unzip given filename.
] If 'targetFileName' is given, the class will output extracted file
] to that file. If FALSE, extracted file will be returned to variable.

- Method 'unzipAll([targetDir[, baseDir[, maintainStructure]]])'
] Unzip all the files in the compressed file, to the 'targetDir' folder.
] If not given, actual directory will be used.
] If 'baseDir', only files compressed in the given directory of the ZIP
] file will be extracted. If not, all the files compressed will be unzipped.
] If 'maintainStructure' is FALSE, then the zip structure will be disabled.
] Default is TRUE, class will auto-create subfolders to hold files.

--------------------------------------------------
  Understanding internal and external_attributes
   (Both returned by using method getExtraInfo)
--------------------------------------------------
- Internal attribute
] "The lowest bit of this field indicates, if set, that the file is
] apparently an ASCII or text file.  If not set, that the file apparently
] contains binary data. The remaining bits are unused in version 1.0."
] (source: http://www.pkware.com/download.html)

- External attributes
] First thing, is noting that these are O.S. dependent, so there aren't
] any rules..
]
] By myself, I divided this into two different things:
] * external_attributes1
] * external_attributes2
]
] Actually, I don't have idea on what does the external_attributes2
] represents, so let's talk only about the first one.
]
] I've made some tests on WindowsXP, and here are the results obtained.
] * bit1: Read-Only attribute
] * bit2: Hidden attribute
] * bit3: System attribute
] * bit4: Volume Label (not used)
] * bit5: Directory attribute
] * bit6: Archive attribute
]
] To access the bits, use the following code:
] $d = $zip->getExtraInfo('file.txt');
] echo ($d['external_attributes1']&1 )?"File is read-only.":"File is writeable.";
] echo ($d['external_attributes1']&2 )?"File is hidden.":"File is not hidden.";
] echo ($d['external_attributes1']&32)?"Archive attrib is set.":"Archive attrib not set.";

------------
  Examples
------------
Using this class, you can:
- List all the files compressed in a zip file, without overloading memory.
- Uncompress any files on it.
- Support to subfolders
- Easily unzip a file maintaing all the directory structure

Using this class, you cannot:
- Zip files dinamically
- Modify your zip files
There are SO MANY classes around the internet to do this.
  (also, I've created a class dZip to do this, you can
   use it if you like it)

How to use?
$zip = new dUnzip("file_to_unzip.zip");
$zip->debug = 1; // debug?

Then, choose one way...
# List all the files compressed
# getList(void)
$list = $zip->getList();
foreach($list as $fileName=>$zippedFile)
  echo "$fileName ($zippedFile[uncompressed_size] bytes)<br>";

# Unzip some file:
# unzip($fileName [,$targetFilename])
echo $zip->unzip("test.txt"); // To a variable
$zip->unzip("test.txt", "test.txt"); // To a filename

# Unzip the whole ZIP file
# unzipAll([$targetDir[, $baseDir[, $maintainStructure]]])
$zip->unzipAll('uncompressed');

