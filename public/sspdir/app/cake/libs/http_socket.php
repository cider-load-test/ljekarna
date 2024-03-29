<?php
/* SVN FILE: $Id: http_socket.php 5146 2007-05-21 05:19:21Z phpnut $ */
/**
 * HTTP Socket connection class.
 *
 * PHP versions 4 and 5
 *
 * CakePHP(tm) :  Rapid Development Framework <http://www.cakephp.org/>
 * Copyright 2005-2007, Cake Software Foundation, Inc.
 *								1785 E. Sahara Avenue, Suite 490-204
 *								Las Vegas, Nevada 89104
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright		Copyright 2005-2007, Cake Software Foundation, Inc.
 * @link				http://www.cakefoundation.org/projects/info/cakephp CakePHP(tm) Project
 * @package			cake
 * @subpackage		cake.cake.libs
 * @since			CakePHP(tm) v 1.2.0
 * @version			$Revision: 5146 $
 * @modifiedby		$LastChangedBy: phpnut $
 * @lastmodified	$Date: 2007-05-21 00:19:21 -0500 (Mon, 21 May 2007) $
 * @license			http://www.opensource.org/licenses/mit-license.php The MIT License
 */
uses('socket', 'set');

/**
 * Cake network socket connection class.
 *
 * Core base class for network communication.
 *
 * @package		cake
 * @subpackage	cake.cake.libs
 */
class HttpSocket extends CakeSocket {
/**
 * Object description
 *
 * @var string
 * @access protected
 */
	var $description = 'HTTP-based DataSource Interface';

/**
 * When one activates the $quirksMode by setting it to true, all checks meant to enforce RFC 2616 (HTTP/1.1 specs)
 * will be disabled and additional measures to deal with non-standard responses will be enabled.
 *
 * @var boolean
 * @access protected
 */
	var $quirksMode = false;

/**
 * The default values to use for a request
 *
 * @var array
 * @access protected
 */
	var $request = array(
		'method' => 'GET',
		'uri' => array(
			'scheme' => 'http',
			'host' => null,
			'port' => 80,
			'user' => null,
			'pass' => null,
			'path' => null,
			'query' => null,
			'fragment' => null
		),
		'auth' => array(
			'method' => 'basic',
			'user' => null,
			'pass' => null
		),
		'version' => '1.1',
		'body' => '',
		'line' => null,
		'header' => array(
			'Connection' => 'close',
			'User-Agent' => 'CakePHP'
		),
		'raw' => null
	);
/**
* The default structure for storing the response
*
* @var array
* @access protected
*/
	var $response = array(
		'raw' => array(
			'status-line' => null,
			'header' => null,
			 'body' => null,
			 'response' => null
		),
		'status' => array(
			'http-version' => null,
			'code' => null,
			'reason-phrase' => null
		),
		'header' => array(),
		'body' => ''
	);

/**
 * Default configuration settings for the HttpSocket
 *
 * @var array
 * @access protected
 */
	var $config = array(
		'persistent' => false,
		'host' 		 => 'localhost',
		'protocol'   => 'tcp',
		'port' 		 => 80,
		'timeout' 	 =>	30,
		'request' => array(
			'uri' => array(
				'scheme' => 'http',
				'host' => 'localhost',
				'port' => 80
			),
			'auth' => array(
				'method' => 'basic',
				'user' => null,
				'pass' => null
			)
		)
	);

/**
 * String that represents a line break.
 *
 * @var string
 * @access protected
 */
	var $lineBreak = "\r\n";

/**
 * Build an HTTP Socket using the specified configuration.
 *
 * @param array $config	Configuration
 */
	function __construct($config = array()) {
		if (is_string($config)) {
			$this->configUri($config);
		} elseif (is_array($config)) {
			$this->config = Set::merge($this->config, $config);
		}
		parent::__construct($this->config);
	}

/**
 * Issue the specified request.
 *
 * @param mixed $request Either an URI string, or an array defining host/uri
 * @return mixed false on error, request body on success
 * @access public
 */
	function request($request = array()) {
		$this->reset(false);

		if (is_string($request)) {
			$request = array('uri' => $request);
		} elseif (!is_array($request)) {
			return false;
		}

		if (isset($request['host'])) {
			$host = $request['host'];
			unset($request['host']);
		}

		if (is_string($request['uri']) && !preg_match('/^.+:\/\/|\*|^\//', $request['uri'])) {
			$request['uri'] = 'http://'.$request['uri'];
		}

		$request['uri'] = $this->parseUri($request['uri']);
		$this->request = Set::merge($this->request, $this->config['request'], $request);
		$this->configUri($this->request['uri']);

		if (isset($host)) {
			$this->config['host'] = $host;
		}

		if (is_array($this->request['header'])) {
			$this->request['header'] = $this->parseHeader($this->request['header']);
			$this->request['header'] = am(array('Host' => $this->request['uri']['host']), $this->request['header']);
		}

		if (is_array($this->request['body'])) {
			$this->request['body'] = $this->httpSerialize($this->request['body']);
		}

		if (!empty($this->request['body']) && !isset($this->request['header']['Content-Type'])) {
			$this->request['header']['Content-Type'] = 'application/x-www-form-urlencoded';
		}

		if (!empty($this->request['body']) && !isset($this->request['header']['Content-Length'])) {
			$this->request['header']['Content-Length'] = strlen($this->request['body']);
		}

		$this->request['header'] = $this->buildHeader($this->request['header']);

		if (empty($this->request['line'])) {
			$this->request['line'] = $this->buildRequestLine($this->request);
		}

		if ($this->quirksMode === false && $this->request['line'] === false) {
			return $this->response = false;
		}

		if ($this->request['line'] !== false) {
			$this->request['raw'] = $this->request['line'];
		}

		if ($this->request['header'] !== false) {
			$this->request['raw'] .= $this->request['header'];
		}

		$this->request['raw'] .= "\r\n";
		$this->request['raw'] .= $this->request['body'];
		$this->write($this->request['raw']);

		$response = null;
		while ($data = $this->read()) {
			$response .= $data;
		}

		$this->response = $this->parseResponse($response);
		return $this->response['body'];
	}

/**
 * Issues a GET request to the specified URI, query, and request.
 *
 * @param mixed $uri URI to request (see {@link parseUri()})
 * @param array $query Query to append to URI
 * @param array $request An indexed array with indexes such as 'method' or uri
 * @return mixed Result of request
 * @access public
 */
	function get($uri = null, $query = array(), $request = array()) {
		if (!empty($query)) {
			$uri =$this->parseUri($uri);
			if (isset($uri['query'])) {
				$uri['query'] = am($uri['query'], $query);
			} else {
				$uri['query'] = $query;
			}
			$uri = $this->buildUri($uri);
		}

		$request = Set::merge(array('method' => 'GET', 'uri' => $uri), $request);
		return $this->request($request);
	}

/**
 * Issues a POST request to the specified URI, query, and request.
 *
 * @param mixed $uri URI to request (see {@link parseUri()})
 * @param array $query Query to append to URI
 * @param array $request An indexed array with indexes such as 'method' or uri
 * @return mixed Result of request
 * @access public
 */
	function post($uri = null, $data = array(), $request = array()) {
		$request = Set::merge(array('method' => 'POST', 'uri' => $uri, 'body' => $data), $request);
		return $this->request($request);
	}

/**
 * Issues a PUT request to the specified URI, query, and request.
 *
 * @param mixed $uri URI to request (see {@link parseUri()})
 * @param array $query Query to append to URI
 * @param array $request An indexed array with indexes such as 'method' or uri
 * @return mixed Result of request
 * @access public
 */
	function put($uri = null, $data = array(), $request = array()) {
		$request = Set::merge(array('method' => 'PUT', 'uri' => $uri, 'body' => $data), $request);
		return $this->request($request);
	}

/**
 * Issues a DELETE request to the specified URI, query, and request.
 *
 * @param mixed $uri URI to request (see {@link parseUri()})
 * @param array $query Query to append to URI
 * @param array $request An indexed array with indexes such as 'method' or uri
 * @return mixed Result of request
 * @access public
 */
	function delete($uri = null, $data = array(), $request = array()) {
		$request = Set::merge(array('method' => 'DELETE', 'uri' => $uri, 'body' => $data), $request);
		return $this->request($request);
	}

/**
 * Parses the given message and breaks it down in parts.
 *
 * @param string $message Message to parse
 * @return array Parsed message (with indexed elements such as raw, status, header, body)
 * @access protected
 */
	function parseResponse($message) {
		if (is_array($message)) {
			return $message;
		} elseif (!is_string($message)) {
			return false;
		}

		static $responseTemplate;
		if (empty($responseTemplate)) {
			$classVars = get_class_vars(__CLASS__);
			$responseTemplate = $classVars['response'];
		}

		$response = $responseTemplate;

		if (preg_match("/(.+\r\n)(.*)(?<=\r\n)\r\n(.+)\$/DUs", $message, $match)) {
			list($response['raw']['response'], $response['raw']['status-line'], $response['raw']['header'], $response['raw']['body']) = $match;

			if (preg_match("/(.+) ([0-9]{3}) (.+)\r\n/DU", $response['raw']['status-line'], $match)) {
				$response['status']['http-version'] = $match[1];
				$response['status']['code'] = (int)$match[2];
				$response['status']['reason-phrase'] = $match[3];
			}

			$response['header'] = $this->parseHeader($response['raw']['header']);
			$decoded = $this->decodeBody($response['raw']['body'], @$response['header']['Transfer-Encoding']);
			$response['body'] = $decoded['body'];
			if (!empty($decoded['header'])) {
				$response['header'] = $this->parseHeader($this->buildHeader($response['header']).$this->buildHeader($decoded['header']));
			}

		} else {
			return false;
		}

		foreach ($response['raw'] as $field => $val) {
			if ($val === '') {
				$response['raw'][$field] = null;
			}
		}

		return $response;
	}
/**
 * Generic function to decode a $body with a given $encoding. Returns either an array with the keys
 * 'body' and 'header' or false on failure.
 *
 * @param string $body A string continaing the body to decode
 * @param mixed $encoding Can be false in case no encoding is being used, or a string representing the encoding
 * @return mixed Array or false
 * @access protected
 */
	function decodeBody($body, $encoding = 'chunked') {
		if (!is_string($body)) {
			return false;
		}
		if (empty($encoding)) {
			return array('body' => $body, 'header' => false);
		}
		$decodeMethod = 'decode'.Inflector::camelize(r('-', '_', $encoding)).'Body';

		if (!is_callable(array(&$this, $decodeMethod))) {
			if (!$this->quirksMode) {
				trigger_error(sprintf(__('HttpSocket::decodeBody - Unkown encoding: %s. Activate quirks mode to surpress error.', true), h($encoding)), E_USER_WARNING);
			}
			return array('body' => $body, 'header' => false);
		}
		return $this->{$decodeMethod}($body);
	}
/**
 * Decodes a chunked message $body and returns either an array with the keys 'body' and 'header' or false as
 * a result.
 *
 * @param string $body A string continaing the chunked body to decode
 * @return mixed Array or false
 * @access protected
 */
	function decodeChunkedBody($body) {
		if (!is_string($body)) {
			return false;
		}

		$decodedBody = null;
		$chunkLength = null;

		while ($chunkLength !== 0) {
			if (!preg_match("/^([0-9a-f]+)(?:;(.+)=(.+))?\r\n/iU", $body, $match)) {
				if (!$this->quirksMode) {
					trigger_error(__('HttpSocket::decodeChunkedBody - Could not parse malformed chunk. Activate quirks mode to do this.', true), E_USER_WARNING);
					return false;
				}
				break;
			}

			@list($chunkSize, $hexLength, $chunkExtensionName, $chunkExtensionValue) = $match;
			$body = substr($body, strlen($chunkSize));
			$chunkLength = hexdec($hexLength);
			$chunk = substr($body, 0, $chunkLength);
			if (!empty($chunkExtensionName)) {
				/**
				 * @todo See if there are popular chunk extensions we should implement
				 */
			}
			$decodedBody .= $chunk;
			if ($chunkLength !== 0) {
				$body = substr($body, $chunkLength+strlen("\r\n"));
			}
		}

		$entityHeader = false;
		if (!empty($body)) {
			$entityHeader = $this->parseHeader($body);
		}
		return array('body' => $decodedBody, 'header' => $entityHeader);
	}
/**
 * Parses and sets the specified URI into current request configuration.
 *
 * @param mixed $uri URI (see {@link parseUri()})
 * @return array Current configuration settings
 * @access protected
 */
	function configUri($uri = null) {
		if (empty($uri)) {
			return false;
		}

		if (is_array($uri)) {
			$uri = $this->parseUri($uri);
		} else {
			$uri = $this->parseUri($uri, true);
		}

		if (!isset($uri['host'])) {
			return false;
		}

		$config = array(
			'request' => array(
				'uri' => array_intersect_key($uri, $this->config['request']['uri']),
				'auth' => array_intersect_key($uri, $this->config['request']['auth'])
			)
		);
		$this->config = Set::merge($this->config, $config);
		$this->config = Set::merge($this->config, array_intersect_key($this->config['request']['uri'], $this->config));
		return $this->config;
	}
/**
 * Takes a $uri array and turns it into a fully qualified URL string
 *
 * @param array $uri A $uri array, or uses $this->config if left empty
 * @param string $uriTemplate The Uri template/format to use
 * @return string A fully qualified URL formated according to $uriTemplate
 * @access protected
 */
	function buildUri($uri = array(), $uriTemplate = '%scheme://%user:%pass@%host:%port/%path?%query#%fragment') {
		if (is_string($uri)) {
			$uri = array('host' => $uri);
		}
		$uri = $this->parseUri($uri, true);

		if (!is_array($uri) || empty($uri)) {
			return false;
		}

		$uri['path'] = preg_replace('/^\//', null, $uri['path']);
		$uri['query'] = $this->httpSerialize($uri['query']);
		$stripIfEmpty = array(
			'query' => '?%query',
			'fragment' => '#%fragment',
			'user' => '%user:%pass@'
		);

		foreach ($stripIfEmpty as $key => $strip) {
			if (empty($uri[$key])) {
				$uriTemplate = str_replace($strip, null, $uriTemplate);
			}
		}

		$defaultPorts = array('http' => 80, 'https' => 443);
		if (array_key_exists($uri['scheme'], $defaultPorts) && $defaultPorts[$uri['scheme']] == $uri['port']) {
			$uriTemplate = str_replace(':%port', null, $uriTemplate);
		}

		foreach ($uri as $property => $value) {
			$uriTemplate = str_replace('%'.$property, $value, $uriTemplate);
		}

		if ($uriTemplate === '/*') {
			$uriTemplate = '*';
		}
		return $uriTemplate;
	}
/**
 * Parses the given URI and breaks it down into pieces as an indexed array with elements
 * such as 'scheme', 'port', 'query'.
 *
 * @param string $uri URI to parse
 * @param mixed $base If true use default URI config, otherwise indexed array to set 'scheme', 'host', 'port', etc.
 * @return array Parsed URI
 * @access protected
 */
	function parseUri($uri = null, $base = array()) {
		$uriBase = array(
			'scheme' => array('http', 'https'),
			'host' => null,
			'port' => array(80, 443),
			'user' => null,
			'pass' => null,
			'path' => null,
			'query' => null,
			'fragment' => null
		);

		if (is_string($uri)) {
			$uri = parse_url($uri);
		}
		if (!is_array($uri) || empty($uri)) {
			return false;
		}
		if ($base === true) {
			$base = $uriBase;
		}

		if (isset($base['port'], $base['scheme']) && is_array($base['port']) && is_array($base['scheme'])) {
			if (isset($uri['scheme']) && !isset($uri['port'])) {
				$base['port'] = $base['port'][array_search($uri['scheme'], $base['scheme'])];
			} elseif (isset($uri['port']) && !isset($uri['scheme'])) {
				$base['scheme'] = $base['scheme'][array_search($uri['port'], $base['port'])];
			}
		}

		if (is_array($base) && !empty($base)) {
			$uri = am($base, $uri);
		}

		if (isset($uri['scheme']) && is_array($uri['scheme'])) {
			$uri['scheme'] = array_shift($uri['scheme']);
		}
		if (isset($uri['port']) && is_array($uri['port'])) {
			$uri['port'] = array_shift($uri['port']);
		}

		if (array_key_exists('query', $uri)) {
			$uri['query'] = $this->parseQuery($uri['query']);
		}

		if (!array_intersect_key($uriBase, $uri)) {
			return false;
		}
		return $uri;
	}
/**
 * This function can be thought of as a reverse to PHP5's http_build_query(). It takes a given query string and turns it into an array and
 * supports nesting by using the php bracket syntax. So this menas you can parse queries like:
 *
 * - ?key[subKey]=value
 * - ?key[]=value1&key[]=value2
 *
 * A leading '?' mark in $query is optional and does not effect the outcome of this function. For the complete capabilities of this implementation
 * take a look at HttpSocketTest::testParseQuery()
 *
 * @param mixed $query A query string to parse into an array or an array to return directly "as is"
 * @return array The $query parsed into a possibly multi-level array. If an empty $query is given, an empty array is returned.
 * @access protected
 */
	function parseQuery($query) {
		if (is_array($query)) {
			return $query;
		}
		$parsedQuery = array();

		if (is_string($query) && !empty($query)) {
			$query = preg_replace('/^\?/', '', $query);
			$items = explode('&', $query);
			foreach ($items as $item) {
				if (strpos($item, '=') !== false) {
					list($key, $value) = explode('=', $item);
				} else {
					$key = $item;
					$value = null;
				}

				$key = urldecode($key);
				$value = urldecode($value);

				if ($value === '') {
					$value = null;
				} elseif (ctype_digit($value) == true) {
					$value = (int)$value;
				}

				if(preg_match_all('/\[([^\[\]]*)\]/iUs', $key, $matches)) {
					$subKeys = $matches[1];
					$rootKey = substr($key, 0, strpos($key, '['));
					if (!empty($rootKey)) {
						array_unshift($subKeys, $rootKey);
					}
					$queryNode =& $parsedQuery;

					foreach ($subKeys as $subKey) {
						if (!is_array($queryNode)) {
							$queryNode = array();
						}

						if ($subKey === '') {
							$queryNode[] = array();
							end($queryNode);
							$subKey = key($queryNode);
						}
						$queryNode =& $queryNode[$subKey];
					}
					$queryNode = $value;
				} else {
					$parsedQuery[$key] = $value;
				}
			}
		}
		return $parsedQuery;
	}
/**
 * Builds a request line according to HTTP/1.1 specs. Activate quirks mode to work outside specs.
 *
 * @param array $request Needs to contain a 'uri' key. Should also contain a 'method' key, otherwise defaults to GET.
 * @param string $versionToken The version token to use, defaults to HTTP/1.1
 * @return string Request line
 * @access protected
 */
	function buildRequestLine($request = array(), $versionToken = 'HTTP/1.1') {
		$asteriskMethods = array('OPTIONS');

		if (is_string($request)) {
			$isValid = preg_match("/(.+) (.+) (.+)\r\n/U", $request, $match);
			if (!$this->quirksMode && (!$isValid || ($match[2] == '*' && !in_array($match[3], $asteriskMethods)))) {
				trigger_error(__('HttpSocket::buildRequestLine - Passed an invalid request line string. Activate quirks mode to do this.', true), E_USER_WARNING);
				return false;
			}
			return $request;
		} elseif (!is_array($request)) {
			return false;
		} elseif (!array_key_exists('uri', $request)) {
			return false;
		}

		$request['uri']	= $this->parseUri($request['uri']);
		$request = am(array('method' => 'GET'), $request);
		$request['uri'] = $this->buildUri($request['uri'], '/%path?%query');

		if (!$this->quirksMode && $request['uri'] === '*' && !in_array($request['method'], $asteriskMethods)) {
			trigger_error(sprintf(__('HttpSocket::buildRequestLine - The "*" asterisk character is only allowed for the following methods: %s. Activate quirks mode to work outside of HTTP/1.1 specs.', true), join(',', $asteriskMethods)), E_USER_WARNING);
			return false;
		}
		return $request['method'].' '.$request['uri'].' '.$versionToken.$this->lineBreak;
	}

/**
 * Serializes an array for transport.
 *
 * @param array $data Data to serialize
 * @return string Serialized variable
 * @access protected
 */
	function httpSerialize($data = array()) {
		if (is_string($data)) {
			return $data;
		}
		if (empty($data) || !is_array($data)) {
			return false;
		}
		return substr(Router::queryString($data), 1);
	}
/**
 * Builds the header.
 *
 * @param array $header Header to build
 * @return string Header built from array
 * @access protected
 */
	function buildHeader($header) {
		if (is_string($header)) {
			return $header;
		} elseif (!is_array($header)) {
			return false;
		}

		$returnHeader = '';
		foreach ($header as $field => $contents) {
			if (is_array($contents)) {
				$contents = join(',', $contents);
			}
			$contents = preg_replace("/\r\n(?![\t ])/", "\r\n ", $contents);
			$field = $this->escapeToken($field);

			$returnHeader .= $field.': '.$contents.$this->lineBreak;
		}
		return $returnHeader;
	}

/**
 * Parses an array based header.
 *
 * @param array $header Header as an indexed array (field => value)
 * @return array Parsed header
 * @access protected
 */
	function parseHeader($header) {
		if (is_array($header)) {
			foreach ($header as $field => $value) {
				unset($header[$field]);
				$field = low($field);
				preg_match_all('/(?:^|(?<=-))[a-z]/U', $field, $offsets, PREG_OFFSET_CAPTURE);

				foreach ($offsets[0] as $offset) {
					$field = substr_replace($field, up($offset[0]), $offset[1], 1);
				}
				$header[$field] = $value;
			}
			return $header;
		} elseif (!is_string($header)) {
			return false;
		}

		preg_match_all("/(.+):(.+)(?:(?<![\t ])".$this->lineBreak."|\$)/Uis", $header, $matches, PREG_SET_ORDER);

		$header = array();
		foreach ($matches as $match) {
			list(, $field, $value) = $match;

			$value = trim($value);
			$value = preg_replace("/[\t ]\r\n/", "\r\n", $value);

			$field = $this->unescapeToken($field);

			$field = low($field);
			preg_match_all('/(?:^|(?<=-))[a-z]/U', $field, $offsets, PREG_OFFSET_CAPTURE);
			foreach ($offsets[0] as $offset) {
				$field = substr_replace($field, up($offset[0]), $offset[1], 1);
			}

			if (!isset($header[$field])) {
				$header[$field] = $value;
			} else {
				$header[$field] .= ','.$value;
			}
		}
		return $header;
	}

/**
 * Unescapes a given $token according to RFC 2616 (HTTP 1.1 specs)
 *
 * @param string $token Token to unescape
 * @return string Unescaped token
 * @access protected
 */
	function unescapeToken($token) {
		$regex = '/"(['.join('', $this->__tokenEscapeChars()).'])"/';
		$token = preg_replace($regex, '\\1', $token);
		return $token;
	}
/**
 * Escapes a given $token according to RFC 2616 (HTTP 1.1 specs)
 *
 * @param string $token Token to escape
 * @return string Escaped token
 * @access protected
 */
	function escapeToken($token) {
		$regex = '/(['.join('', $this->__tokenEscapeChars()).'])/';
		$token = preg_replace($regex, '"\\1"', $token);
		return $token;
	}

/**
 * Gets escape chars according to RFC 2616 (HTTP 1.1 specs).
 *
 * @param boolean $hex true to get them as HEX values, false otherwise
 * @return array Escape chars
 * @access private
 */
	function __tokenEscapeChars($hex = true) {
		$escape = array('"', "(", ")", "<", ">", "@", ",", ";", ":", "\\", "/", "[", "]", "?", "=", "{", "}", " ");
		for ($i = 0; $i <= 31; $i++) {
			$escape[] = chr($i);
		}
		$escape[] = chr(127);

		if ($hex == false) {
			return $escape;
		}
		$regexChars = '';
		foreach ($escape as $key => $char) {
			$escape[$key] = '\\x'.str_pad(dechex(ord($char)), 2, '0', STR_PAD_LEFT);
		}
		return $escape;
	}
/**
 * Resets the state of this HttpSocket instance to it's initial state (before Object::__construct got executed) or does
 * the same thing partially for the request and the response property only.
 *
 * @param boolean $full If set to false only HttpSocket::response and HttpSocket::request are reseted
 * @return boolean True on success
 * @access public
 */
	function reset($full = true) {
		static $initalState = array()	;
		if (empty($initalState)) {
			$initalState = get_class_vars(__CLASS__);
		}

		if ($full == false) {
			$this->request = $initalState['request'];
			$this->response = $initalState['response'];
			return true;
		}

		foreach ($initalState as $property => $value) {
			$this->{$property} = $value;
		}
		return true;
	}
}

?>