<?php
namespace Phpcraft\Surrogate;
use Phpcraft\
{ClientConnection, Connection, IntegratedServer};
class Server extends IntegratedServer
{
	/**
	 * Returns all clients in state 3 (playing) who are not connected to a sub-server.
	 *
	 * @return ClientConnection[]
	 */
	function getPlayers(): array
	{
		$clients = [];
		foreach($this->clients as $client)
		{
			if($client->state == Connection::STATE_PLAY && @$client->subserver === null)
			{
				array_push($clients, $client);
			}
		}
		return $clients;
	}
}
