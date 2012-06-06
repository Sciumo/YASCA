<?
declare(encoding='UTF-8');
namespace Yasca\Core;

/**
 * Provides information about the current environment
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
final class Environment {
	private function __construct(){}

	/**
	 * Returns the java version available on this machine
	 * 1.X, where X is the version returned.
	 * Returns 0 if there is no Java available.
	 * @return string
	 */
	public static function getJavaVersionAvailable(){
		static $javaVersion = null;
		if (isset($javaVersion) !== true){
			if (\preg_match(
				<<<'EOT'
`(?xm)
	# 1.X, where X is the version we're interested in.
	"	\d+ \. (?<version> \d+ )
`u
EOT
,				\shell_exec('java -version 2>&1'),
				$matches
			)){
				$javaVersion = \intval($matches['version']);
			} else {
				$javaVersion = 0;
			}
		}
		return $javaVersion;
	}

	/**
	 * Returns if at least the specified java version is available
	 * @param unknown_type $version 1.X, where X is $version.
	 */
	public static function hasAtLeastJavaVersion($version){
		return static::getJavaVersionAvailable() >= $version;
	}

	/** @return bool */
	public static function isWindows(){
		return \strcasecmp(\substr(PHP_OS, 0, 3), 'win') === 0;
	}

	/** @return bool */
	public static function isLinux(){
		//TODO: Update with more values where != 'Linux', but are Linux systems
	    return PHP_OS === 'Linux';
	}

	/** @return bool */
	public static function isLinuxWithWine(){
		static $result = null;
		if (!isset($result)){
			$result = self::isLinux() && !preg_match('/no wine in/', \shell_exec('which wine'));
		}
		return $result;
	}
}