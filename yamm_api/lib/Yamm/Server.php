<?php
// $Id$

/**
 * Yamm server reprensation.
 */
class Yamm_Server
{
	/**
   * Canonical URL of server Drupal root.
   * 
   * @var string
	 */
	protected $_url;

	/**
	 * Get server canonical URL.
	 * 
	 * @return string
	 */
	public function getUrl() {
		return $this->_url;
	}

	/**
	 * Human name.
	 * 
	 * @var string
	 */
	protected $_name;

	/**
	 * Get server human name.
	 */
	public function getName() {
		return $this->_name;
	}

	/**
	 * Default constructor.
	 * 
   * @param string $url
   *   Server canonical URL.
	 * @param string $name
	 *   Server human name.
	 */
	public function __construct($url, $name) {
		$this->_url = $url;
		$this->_name = $name;
	}
}
