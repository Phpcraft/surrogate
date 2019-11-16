<?php
namespace Phpcraft\Event;
use Phpcraft\Server;
class SurrogateTickEvent extends SurrogateEvent
{
	/**
	 * True if this tick event should've been fired much earlier but wasn't because surrogate was busy. If your task is complex and/or doesn't need to be executed every tick, try not doing it if this is true.
	 *
	 * @var bool $lagging
	 */
	public $lagging;

	/**
	 * @param Server $server Surrogate's server instance.
	 * @param bool $lagging
	 */
	function __construct(Server $server, bool $lagging)
	{
		parent::__construct($server);
		$this->lagging = $lagging;
	}
}
