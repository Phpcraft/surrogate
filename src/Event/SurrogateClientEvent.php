<?php
namespace Phpcraft\Event;
use Phpcraft\
{ClientConnection, Server};
abstract class SurrogateClientEvent extends SurrogateEvent
{
	/**
	 * @var ClientConnection $client
	 */
	public $client;

	/**
	 * @param Server $server Surrogate's server instance.
	 * @param ClientConnection $client
	 */
	function __construct(Server $server, ClientConnection $client)
	{
		parent::__construct($server);
		$this->client = $client;
	}
}
