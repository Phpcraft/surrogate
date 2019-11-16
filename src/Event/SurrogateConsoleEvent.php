<?php
namespace Phpcraft\Event;
use Phpcraft\Server;
/** The event emitted by surrogate when the console has proposed a broadcast. Cancellable. */
class SurrogateConsoleEvent extends SurrogateEvent
{
	/**
	 * The message that the console has proposed.
	 *
	 * @var string $message
	 */
	public $message;

	/**
	 * @param Server $server Surrogate's server instance.
	 * @param string $message The message that the console has proposed.
	 */
	function __construct(Server $server, string $message)
	{
		parent::__construct($server);
		$this->message = $message;
	}
}