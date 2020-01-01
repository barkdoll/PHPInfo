<?php

/**
 * The PHPInfo class file.
 *
 * @author     outcompute
 * @license    https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt GPL v2
 * @version    1.0.0
 * @since      File available since Release 1.0.0
 */

namespace OutCompute\PHPInfo;

class PHPInfo
{
	private $_phpinfo;

	public function __construct()
	{
		$this->_phpinfo = NULL;
	}

	public function set_info($phpinfo, string $mode = 'text')
	{
		$this->_phpinfo = [
			'mode' => $mode,
			'data' => $phpinfo
		];

		return $this;
	}

	public function get()
	{
		if ( is_array($this->_phpinfo) &&
			array_key_exists('mode', $this->_phpinfo) 
		) {
			switch ( $this->_phpinfo['mode'] ) 
			{
				case 'text':
					return $this->_parse_text($this->_phpinfo['data']);
					break;
				// case 'html':
					// return $this->_parse_html($this->_phpinfo['data']);
					// break;
			}
		}

		return FALSE;
	}

	private function _parse_text($phpinfo)
	{
		[$header, $body] = explode(
			' _______________________________________________________________________',
			$phpinfo);

		if ( strlen($header) == 0 || strlen($body) == 0 ) 
		{
			throw new \Exception(__CLASS__ . " : Supplied PHPInfo doesn't contain separator ' _______________________________________________________________________'");
		}

		$header = explode(PHP_EOL, trim($header));
		$body = explode(PHP_EOL, trim($body));

		$array = $this->_parse_single_text_block($header);

		$processedBody = $this->_parse_text_blocks($body, ['Configuration', 'Environment', 'PHP Variables', 'PHP License']);
		
		foreach ( $processedBody as $k => $v ) 
		{
			switch ( $k ) 
			{
				case 'Configuration':
					$modules = $this->_parse_text_blocks($v, get_loaded_extensions());
					foreach ($modules as $moduleName => $moduleSettings) {
						$array['Configuration'][$moduleName] = $this->_parse_single_text_block($moduleSettings);
					}
					break;
				case 'PHP License':
					array_shift($v);
					$array[$k] = implode(' ', $v);
					break;
				default:
					$array[$k] = $this->_parse_single_text_block($v);
					break;
			}
		}
		return $array;
	}

	private function _parse_text_blocks($blocks, $block_keys)
	{
		$settings = [];
		$current_key = NULL;
		$current_block = [];
		foreach ( $blocks as $line ) 
		{
			$line = trim($line);

			if ( in_array($line, $block_keys) )
			{
				# Each extension block starts with the name of the extension. And if the current line is such a line, then we
				#   need to start a new block, but before that, we need to process the current_block and assign its results
				#   to the current_key
				if ($current_key != NULL) {
					$settings[$current_key] = $current_block; #$this->_parse_single_text_block($current_block);
				}
				$current_key = $line;
				$current_block = [];
			}

			# If the current_key is not NULL, then we are in an extension block, and so this line gets added to the current_block
			#   current_key would be NULL when this foreach loop starts, and until the first extension block is encountered
			if ( $current_key !== NULL ) 
			{
				$current_block[] = $line;
			}
		}
		if ($current_key != NULL) 
		{
			$settings[$current_key] = $current_block;
		} #$this->_parse_single_text_block($current_block);
		return $settings;
	}

	private function _parse_single_text_block($block)
	{
		$settings = [];
		$current_key = NULL;

		foreach ($block as $line) 
		{
			$line = trim($line);
			if (strlen($line) > 0) 
			{
				if (strpos($line, '=>') !== false) 
				{
					$parts = explode('=>', $line);
					$parts[0] = trim($parts[0]);
					$parts[1] = trim($parts[1]);
					switch (count($parts)) {
						case 2:
							if (
								$parts[0] !== 'Variable' &&
								$parts[1] !== 'Value'
							) {
								$current_key = $parts[0];
								$settings[$current_key] = $parts[1];
							}
							break;
						case 3:
							$parts[2] = trim($parts[2]);
							if (
								$parts[0] !== 'Directive' &&
								$parts[1] !== 'Local Value' &&
								$parts[2] !== 'Master Value'
							) {
								$current_key = $parts[0];
								$settings[$current_key] = [
									'Local Value' => $parts[1],
									'Master Value' => $parts[2]
								];
							}
							break;
					}
				} 
				elseif ($current_key != NULL) 
				{
					$settings[$current_key] .= $line;
				}        
			} 
			else 
			{
				$current_key = NULL;
			}
		}
		return $settings;
	}
}
