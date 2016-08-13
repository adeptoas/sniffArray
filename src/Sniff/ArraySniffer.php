<?php
	namespace Adepto\SniffArray\Sniff;

	use Adepto\SniffArray\Exception\InvalidArrayFormatException;

	/**
	 * Class ArraySniffer
	 */
	class ArraySniffer {
		private $spec;
		private $throw;

		public function __construct(array $spec, bool $throw = false) {
			$this->spec = $spec;
			$this->throw = $throw;
		}

		public function setThrow(bool $throw = true) {
			$this->throw = $throw;
		}

		public function sniff(array $array): bool {
			foreach ($this->spec as $key => $type) {
				$baseKey = preg_replace('/(.*)[\+\*]$/', '$1', $key); //Remove eventual * or +
				$element = $array[$baseKey] ?? null;

				if ($baseKey != $key) { //RegExp key used //TODO suushie drop key completely on star (*) usage
					$subSpec = [$baseKey => $type];
					$subSniffer = $this->subSniffer($subSpec);

					$conforms = StringSniffer::strEndsWith($key, '*') || count($element);

					if (!is_array($element) || (is_array($type) && array_keys($element) == array_keys($type)))
						$element = [$element];

					foreach ($element as $subElement) {
						$subElement = [$baseKey => $subElement];
						$conforms &= $subSniffer->sniff($subElement);
					}

					if (!$this->handle(!$conforms, 'Key ' . $key . ' of type ' . json_encode($type) . ' does not conform!'))
						return false;
				} else if (is_array($type)) {
					if (!$this->handle(!array_key_exists($key, $array), 'Missing key: ' . $key . ' of type ' . json_encode($type)))
						return false;

					if (!$this->handle(!is_array($element), $key . ' must be a complex array'))
						return false;

					$conforms = $this->subSniffer($type)->sniff($element);

					if (!$this->handle(!$conforms, 'Complex array ' . $key . ' does not conform!'))
						return false;
				} else {
					$type = preg_replace('/(.*)\?$/', '$1|null', $type);
					$expectedTypes = explode('|', $type);

					$conforms = false;

					if (!$this->handle(!array_key_exists($key, $array) && !in_array('null', $expectedTypes), 'Missing key: ' . $key . ' of type ' . $type))
						return false;

					foreach ($expectedTypes as $t) {
						$baseType = preg_replace('/(.*)\!$/', '$1', $t);
						$isStrict = strlen($t) != strlen($baseType);

						if (!$this->handle(!SplSniffer::isValidType($baseType), 'Type ' . $baseType . ' not valid'))
							return false;

						$conforms |= SplSniffer::forType($baseType)->sniff($element, $isStrict);
					}

					if (!$this->handle(!$conforms, $key . ' with value ' . var_export($element, true) . ' does not match type definition ' . $type))
						return false;
				}
			}

			return true;
		}

		private function subSniffer(array $spec): ArraySniffer {
			return new self($spec, $this->throw);
		}

		private function handle(bool $condition, string $errMessage = ''): bool {
			if ($condition) {
				if ($this->throw) {
					throw new InvalidArrayFormatException($errMessage);
				}

				return false;
			} else {
				return true;
			}
		}

		public static function arrayConformsTo(array $spec, array $array, bool $throw = false): bool {
			return (new ArraySniffer($spec, $throw))->sniff($array);
		}
	}