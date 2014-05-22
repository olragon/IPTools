<?php
namespace IPTools;
require_once __DIR__ . '/polyfill.php';

/**
 * @author Safarov Alisher <alisher.safarov@outlook.com>
 * @link https://github.com/S1lentium/IPTools
 * @version 1.0
 */
class Network implements \Iterator, \Countable
{
	/**
	 * @var IP
	 */
	private $ip;
	/**
     * @var IP
     */
	private $netmask;
	/**
	 * @var int
	 */
	private $position = 0;

	/**
	 * @param IP $ip
	 * @param IP $netmask
	 */
	public function __construct(IP $ip, IP $netmask)
	{
		$this->setIP($ip);
		$this->setNetmask($netmask);
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name)
	{
		$method = 'get'. ucfirst($name);
		if (!method_exists($this, $method)) {
			trigger_error('Undefined property');
			return null;
		}

		return $this->$method();
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value)
	{
		$method = 'set'. ucfirst($name);
		if (!method_exists($this, $method)) {
			trigger_error('Undefined property');
			return;
		}

		$this->$method($value);
	}

	/**
	 * 
	 * @return string
	 */
	public function __toString()
	{
		return $this->getCIDR();
	}

	/**
	 * @param string $data
	 * @return Network
	 */
	public static function parse($data)
	{
		if (strpos($data,'/')) {
			list($ip, $prefixLength) = explode('/', $data, 2);
			$ip      = IP::parse($ip);
			$netmask = self::prefix2netmask($prefixLength, $ip->getVersion());
		} elseif (strpos($data,' ')) {
			list($ip, $netmask) = explode(' ', $data, 2);
			$ip      = IP::parse($ip);
			$netmask = IP::parse($netmask);
		} else {
			$ip      = IP::parse($data);
			$netmask = self::prefix2netmask($ip->getMaxPrefixLength(), $ip->getVersion());
		}

		return new self($ip, $netmask);
	}

	/**
	 * @param int $prefixLength
	 * @param string $version
	 * @return IP
	 * @throws \Exception
	 */
	public static function prefix2netmask($prefixLength, $version)
	{
		if (!in_array($version, array(IP::IP_V4, IP::IP_V6))) {
			throw new \Exception("Wrong IP version");	
		}

		$maxPrefixLength = $version === IP::IP_V4
			? IP::IP_V4_MAX_PREFIX_LENGTH 
			: IP::IP_V6_MAX_PREFIX_LENGTH; 

		if (!is_numeric($prefixLength)
			&& !($prefixLength >= 0 && $prefixLength <= $maxPrefixLength)
		) {
			throw new \Exception('Invalid prefix length');
		}

		$binIP = str_pad(str_pad('', $prefixLength, '1'), $maxPrefixLength, '0');

		return IP::parseBin($binIP);
	}

	/**
	 * @param IP ip
	 * @return int
	 */
	public static function netmask2prefix(IP $ip) 
	{
		return strlen(rtrim($ip->toBin(), 0));
	}

	/**
	 * @param IP ip
	 * @throws \Exception
	 */
	public function setIP(IP $ip)
	{
		if (isset($this->netmask) && $this->netmask->getVersion() !== $ip->getVersion()) {
			throw new \Exception('IP version is not same as Netmask version');
		}

		$this->ip = $ip;
	}

	/**
	 * @param IP ip
	 * @throws \Exception
	 */
	public function setNetmask(IP $ip)
	{
		if (!preg_match('/^1*0*$/',$ip->toBin())) {
			throw new \Exception('Invalid Netmask address format');
		}

		if(isset($this->ip) && $ip->getVersion() !== $this->ip->getVersion()) {
			throw new \Exception('Netmask version is not same as IP version');
		}

		$this->netmask = $ip;
	}

	/**
	 * @param int $prefixLength
	 */
	public function setPrefixLength($prefixLength)
	{
		$this->setNetmask(self::prefix2netmask($prefixLength, $this->ip->getVersion()));
	}

	/**
	 * @return IP
	 */
	public function getIP()
	{
		return $this->ip;
	}	

	/**
	 * @return IP
	 */
	public function getNetmask()
	{
		return $this->netmask;
	}

	/**
	 * @return IP
	 */
	public function getNetwork()
	{
		return new IP(inet_ntop($this->getIP()->inAddr() & $this->getNetmask()->inAddr()));
	}

	/**
	 * @return int
	 */
	public function getPrefixLength()
	{
		return self::netmask2prefix($this->getNetmask());
	}

	/**
	 * @return string
	 */
	public function getCIDR()
	{
		return sprintf('%s/%s', $this->getNetwork(), $this->getPrefixLength());
	}

	/**
	 * @return IP
	 */
	public function getWildcard()
	{
		return new IP(inet_ntop(~$this->getNetmask()->inAddr()));
	}

	/**
	 * @return IP
	 */
	public function getBroadcast()
	{
		return new IP(inet_ntop($this->getNetwork()->inAddr() | ~$this->getNetmask()->inAddr()));
	}

	/**
	 * @return string
	 */
	public function getClass()
	{
	}

	/**
	 * @return IP
	 */
	public function getFirstIP()
	{
		return $this->getNetwork();
	}

	/**
     * @return IP
     */
	public function getLastIP()
	{
		return $this->getBroadcast();
	}

	/**
	 * @param bool $largeNumber
	 * @return number|string
	 */
	public function getBlockSize($largeNumber=false)
	{
		$maxPrefixLength = $this->ip->getMaxPrefixLength();
		$prefixLength = $this->getPrefixLength();

		if ($largeNumber) {			
			return bcpow('2', (string)($maxPrefixLength - $prefixLength));
		}

		return pow(2, $maxPrefixLength - $prefixLength);
	}

	/**
	 * @return IP
	 */
	public function getFirstHost()
	{
		$network = $this->getNetwork();

		if ($network->getVersion() === IP::IP_V4) {
			if ($this->getBlockSize() > 2) {
				return IP::parseBin(substr($network->toBin(), 0, $network->getMaxPrefixLength() - 1) . '1');
			}
		}

		return $network;
		
	}

	/**
	 * @return IP
	 */
	public function getLastHost()
	{
		$broadcast = $this->getBroadcast();

		if ($broadcast->getVersion() === IP::IP_V4) {
			if ($this->getBlockSize() > 2) {
				return IP::parseBin(substr($broadcast->toBin(), 0, $broadcast->getMaxPrefixLength() - 1) . '0');
			}
		}

		return $broadcast;
		
	}

	/**
	 * @return number|string
	 */
	public function getHostsCount()
	{
		$blockSize = $this->getBlockSize();

		if ($this->ip->getVersion() === IP::IP_V4) {
			return $blockSize > 2 ? $blockSize - 2 : $blockSize;
		}

		return $blockSize;
	}

	/**
	 * @param $exclude
	 * @return array
	 * @throws \Exception
	 */
	public function exclude($exclude)
	{
		$exclude = self::parse($exclude);

		if($exclude->getFirstIP()->inAddr() > $this->getLastIP()->inAddr()
			|| $exclude->getLastIP()->inAddr() < $this->getFirstIP()->inAddr()
		) {
			throw new \Exception('Exclude subnet not within target network');
		}

		$networks = array();

		$newPrefixLength = $this->getPrefixLength() + 1;

		$lower = clone $this;
		$lower->setPrefixLength($newPrefixLength);

		$upper = clone $lower;
		$upper->setIP($lower->getLastIP()->next());

		while ($newPrefixLength <= $exclude->getPrefixLength()) {
			$range = new Range($lower->getFirstIP(), $lower->getLastIP());
			if($range->contains($exclude)) {
				$matched   = $lower;
				$unmatched = $upper;
			} else {
				$matched   = $upper;
				$unmatched = $lower;
			}

			$networks[] = clone $unmatched;

			if(++$newPrefixLength > $this->getNetwork()->getMaxPrefixLength()) break;

			$matched->setPrefixLength($newPrefixLength);
			$unmatched->setPrefixLength($newPrefixLength);
			$unmatched->setIP($matched->getLastIP()->next());
		}

		sort($networks);

		return $networks;
	}

	/**
	 * @return array
	 */
	public function getInfo() 
	{
		$info = array();

		$reflect = new \ReflectionClass($this);

		foreach ($reflect->getMethods() as $method) {
			if(strpos($method->name, 'get') === 0 && $method->name !== __FUNCTION__) {
				$property = substr($method->name, 3);
				
				if($property !== 'IP' && $property !== 'CIDR') {
					$property = lcfirst($property);
				}

				$info[$property] = is_object($this->{$method->name}())
					? (string)$this->{$method->name}()
					: $this->{$method->name}();
			}
		}

		return $info;
	}

	/**
	* @return IP
	*/
	public function current()
	{
		return $this->getFirstHost()->next($this->position);
	}

	/**
	* @return int
	*/
	public function key()
	{
		return $this->position;
	}

	public function next()
	{
		++$this->position;
	}

	public function rewind()
	{
		$this->position = 0;
	}

	/**
	* @return bool
	*/
	public function valid()
	{
		return $this->getFirstHost()->next($this->position)->inAddr() <= $this->getLastHost()->inAddr();
	}

	/**
	* @return int
	*/
	public function count()
	{
		return $this->getHostsCount();
	}

}
