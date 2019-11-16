<?php
namespace Phpcraft\Event;
use Phpcraft\Server;
abstract class SurrogateEvent extends Event
{
	/**
	 * Surrogate's server instance.
	 *
	 * @var Server $server
	 */
	public $server;

	/**
	 * @param Server $server Surrogate's server instance.
	 */
	function __construct(Server $server)
	{
		$this->server = $server;
	}
}
