<?
declare(encoding='UTF-8');
namespace Yasca\Core;

/**
 * Wraps and extends functionality around character encodings,
 * such as the multibyte string extension.
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
final class Encoding {
	private function __construct(){}

	private static $detectOrder = ['UTF-8', 'windows-1251', 'ISO-8859-1',];

	/**
	 * Detects the encoding of the provided string.
	 * Extends mb_detect_encoding to detect UTF-16 and UTF-32,
	 * as well as throwing an exception when detection fails.
	 * @param string $str
	 * @throws EncodingException
	 * @return string The encoding of the provided string
	 */
	public static function detect($str){
		$encoding = \mb_detect_encoding(
			$str,
			self::$detectOrder,
			true
		);
		if ($encoding !== false){
			return $encoding;
		}

		//As of PHP 5.4.3, UTF-16 encoding detection fails always
		//http://us.php.net/manual/en/function.mb-detect-encoding.php
		$first2 = \substr($retval, 0, 2);
		$first4 = \substr($retval, 0, 4);

		if 		 ($first4 === "\x00\x00\xFE\xFF"){
			$encoding = 'UTF-32BE';
		} elseif ($first4 === "\xFE\xFF\x00\x00"){
			$encoding = 'UTF-32LE';
		} elseif ($first2 === "\xFE\xFF"){
			$encoding = 'UTF-16BE';
		} elseif ($first2 === "\xFF\xFE"){
			$encoding = 'UTF-16LE';
		} else {
			throw new EncodingException('Unable to detect encoding');
		}
	}

	/**
	 * Converts the string to the target encoding
	 * @param string $str
	 * @param string $targetEncoding
	 * @throws EncodingException
	 * @return string
	 */
	public static function convert($str, $targetEncoding = 'UTF-8'){
		return \mb_convert_encoding(
			$str,
			$targetEncoding,
			self::detect($str)
		);
	}

	/**
	 * Returns file contents, converted to the target encoding.
	 * @param string $filepath
	 * @param string $targetEncoding
	 * @return string
	 */
	public static function getFileContentsAsString($filepath, $targetEncoding = 'UTF-8'){
		return self::convert(
			\file_get_contents($filepath, false),
			$targetEncoding
		);
	}

	/**
	 * Returns array of UTF-8 strings
	 * @param string $filepath
	 * @return array of string
	 */
	public static function getFileContentsAsArray($filepath){
		return \preg_split('`(*ANY)\R`u', self::getFileContentsAsString($filepath));
	}
}