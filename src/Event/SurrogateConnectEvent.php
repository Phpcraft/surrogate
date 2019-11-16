<?php
namespace Phpcraft\Event;
use Phpcraft\ClientConnection;
use Phpcraft\Server;
/** Fired when a client has successfully connected to a sub-server. */
class SurrogateConnectEvent extends SurrogateClientEvent
{
	/**
	 * The name of the sub-server.
	 *
	 * @var string $subserver_name
	 */
	public $subserver_name;

	function __construct(Server $server, ClientConnection $client, string $subserver_name)
	{
		parent::__construct($server, $client);
		$this->subserver_name = $subserver_name;
	}
}
