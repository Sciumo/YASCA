<?
declare(encoding='UTF-8');
namespace Yasca\Plugins\BuiltIn\Cryptography\Weak;

trait Base {
	use \Yasca\Plugins\BuiltIn\Base;

	protected function newResult(){
		return (new \Yasca\Result)->setOptions([
    		'severity' => 2,
    		'category' => 'Cryptography',
	        'description' => <<<'EOT'
Certain cryptographic algorithms such as MD5 are considered deprecated and
should not be used in any new applications.
Current applications should consider migrating to current algorithms such as
AES and SHA-256.
EOT
,    		'references' => [
				'http://www.owasp.org/index.php?title=Using_a_broken_or_risky_cryptographic_algorithm' =>
					'OWASP: Risky or Broken Cryptography',
            ],
	    ]);
    }
}