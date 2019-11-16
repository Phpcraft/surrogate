<?php /** @noinspection PhpUndefinedFieldInspection */
echo "Phpcraft Surrogate (Minecraft Reverse Proxy)\n\n";
if(empty($argv))
{
	die("This is for PHP-CLI. Connect to your server via SSH and use `php surrogate.php`.\n");
}
require __DIR__."/vendor/autoload.php";
use Phpcraft\
{Account, ClientConnection, Command\Command, Connection, Enum\Dimension, Enum\Gamemode, Event\ServerJoinEvent, Event\ServerTickEvent, Event\SurrogateConnectEvent, Event\SurrogateConsoleEvent, Event\SurrogateTickEvent, Exception\IOException, Packet\ClientboundPacketId, Packet\EntityPacket, Packet\JoinGamePacket, Packet\KeepAliveRequestPacket, Packet\PluginMessage\ClientboundBrandPluginMessagePacket, Packet\ServerboundPacketId, Phpcraft, PluginManager, Point3D, ServerConnection, Surrogate\Server, Versions};
$server = Server::cliStart("Phpcraft Surrogate", [
	"servers" => [
		"default" => [
			"address" => "phpcraft integrated server",
			"default" => true
		],
		"localhost" => [
			"address" => "localhost:1337",
			"host" => "localhost.phpcraft.de:25565"
		]
	],
	"groups" => [
		"default" => [
			"allow" => [
				"use /join",
				"use /servers"
			]
		],
		"admin" => [
			"allow" => "everything"
		]
	]
]);
$server->fix_duplicate_names = $server->fire_join_event = $server->send_first_packets = $server->provide_player_list = false;
echo "Loading plugins...\n";
PluginManager::$command_prefix = "/surrogate:";
PluginManager::loadPlugins();
echo "Loaded ".count(PluginManager::$loaded_plugins)." plugin(s).\n";
$server->ui->render();
/**
 * @param ClientConnection $con
 * @param string $server_name
 * @return string|null
 * @throws IOException
 */
function connectToServer(ClientConnection $con, string $server_name): ?string
{
	global $server;
	$subserver = @$server->config["servers"][$server_name];
	if($subserver === null)
	{
		return "Unknown server: $server_name";
	}
	$con->chunks = [];
	$con->server_eid = $con->eid;
	if($subserver["address"] == "phpcraft integrated server")
	{
		$con->subserver = null;
		$con->received_imitated_world = null;
		if($con->dimension === null)
		{
			$packet = new JoinGamePacket($con->eid);
			$packet->gamemode = $con->gamemode = Gamemode::CREATIVE;
			$con->dimension = $packet->dimension;
			$packet->render_distance = 32;
			$packet->send($con);
		}
		else
		{
			if($con->dimension == Dimension::OVERWORLD)
			{
				$con->startPacket("respawn");
				$con->writeInt(Dimension::END);
				$con->writeByte(0);
				$con->writeString("");
				$con->send();
			}
			$con->startPacket("respawn");
			$con->writeInt($con->dimension = Dimension::OVERWORLD);
			$con->writeByte(Gamemode::CREATIVE);
			$con->writeString("");
			$con->send();
			$con->teleport(new Point3D(0, 16, 0));
		}
		(new ClientboundBrandPluginMessagePacket("Phpcraft Surrogate"))->send($con);
		$con->startPacket("spawn_position");
		$con->writePosition($con->pos = $server->spawn_position);
		$con->send();
		$con->teleport($con->pos);
		PluginManager::fire(new ServerJoinEvent($server, $con));
		return null;
	}
	$con->received_imitated_world = true;
	$address = Phpcraft::resolve($subserver["address"]);
	$arr = explode(":", $address);
	if(count($arr) != 2)
	{
		return "Server address resolved to $address";
	}
	$arr[1] = intval($arr[1]);
	$stream = @fsockopen($arr[0], $arr[1], $errno, $errstr, 3);
	if(!$stream)
	{
		return $errstr;
	}
	$con->subserver = new ServerConnection($stream, $con->protocol_version);
	$con->subserver->sendHandshake($arr[0], $arr[1], Connection::STATE_STATUS);
	$con->subserver->writeVarInt(0x00); // Status Request
	$con->subserver->send();
	$packet_id = $con->subserver->readPacket(0.3);
	if($packet_id !== 0x00)
	{
		$con->subserver->close();
		return "Server answered status request with packet id ".$packet_id;
	}
	$json = json_decode($con->subserver->readString(), true);
	$con->subserver->close();
	if(empty($json) || empty($json["version"]) || empty($json["version"]["protocol"]))
	{
		return "Invalid status response: ".json_encode($json);
	}
	if($con->transform_packets = ($json["version"]["protocol"] != $con->protocol_version))
	{
		if(!Versions::protocolSupported($json["version"]["protocol"]))
		{
			return "$server_name doesn't support ".Versions::protocolToRange($con->protocol_version).", suggests using ".Versions::protocolToRange($json["version"]["protocol"]).". Phpcraft will probably not be able to transform packets reliably.";
		}
	}
	$stream = @fsockopen($arr[0], $arr[1], $errno, $errstr, 3);
	if(!$stream)
	{
		return $errstr;
	}
	$con->subserver = new ServerConnection($stream, $con->protocol_version);
	$join_specs = [$con->getRemoteAddress()];
	if($server->isOnlineMode())
	{
		array_push($join_specs, $con->uuid);
	}
	$con->subserver->sendHandshake($arr[0], $arr[1], Connection::STATE_LOGIN, $join_specs);
	if($error = $con->subserver->login(new Account($con->username)))
	{
		return $error;
	}
	PluginManager::fire(new SurrogateConnectEvent($server, $con, $server_name));
	return null;
}
$server->join_function = function(ClientConnection $con) use (&$server)
{
	if(!$con->isOpen())
	{
		return;
	}
	$con->dimension = null;
	$host = $con->getHost();
	foreach($server->config["servers"] as $name => $s)
	{
		if(array_key_exists("host", $s) && $s["host"] == $host && (empty($s["restricted"]) || $con->hasPermission("connect to ".$name)) && connectToServer($con, $name) === null)
		{
			return;
		}
	}
	$default_servers = [];
	foreach($server->config["servers"] as $name => $s)
	{
		if(!empty($s["default"]) && (empty($s["restricted"]) || $con->hasPermission("connect to ".$name)))
		{
			array_push($default_servers, $name);
		}
	}
	shuffle($default_servers);
	$name = $error = "???";
	foreach($default_servers as $name)
	{
		if(($error = connectToServer($con, $name)) === null)
		{
			return;
		}
	}
	$con->disconnect(count($default_servers) == 0 ? "[Surrogate] There is no default server. You're either missing a permission, are supposed to connect differently, or Surrogate was misconfigured." : "[Surrogate] Failed to connect to any default server. Most recent error in connecting to $name: $error");
};
$integrated_packet_function = $server->packet_function;
$server->packet_function = function(ClientConnection $con, ServerboundPacketId $packetId) use (&$integrated_packet_function)
{
	if(@$con->subserver === null)
	{
		$integrated_packet_function($con, $packetId);
		return;
	}
	if($packetId->name == "serverbound_chat_message")
	{
		$msg = $con->readString($con->protocol_version < 314 ? 100 : 256);
		if(!Command::handleMessage($con, $msg))
		{
			$con->subserver->startPacket("serverbound_chat_message");
			$con->subserver->writeString($msg);
			$con->subserver->send();
		}
	}
	else if($packetId->name == "entity_action")
	{
		$con->subserver->startPacket("entity_action");
		$eid = $con->readVarInt();
		$con->subserver->writeVarInt(gmp_cmp($eid, $con->eid) == 0 ? $con->server_eid : $eid);
		$con->subserver->write_buffer .= $con->getRemainingData();
		$con->subserver->send();
	}
	else if($con->transform_packets && ($packet = $packetId->getInstance($con)))
	{
		$packet->send($con->subserver);
	}
	else if($packetId->since_protocol_version <= $con->subserver->protocol_version)
	{
		$con->subserver->write_buffer = $con->read_buffer;
		$con->subserver->send();
	}
};
$integrated_disconnect_function = $server->disconnect_function;
$server->disconnect_function = function(ClientConnection $con) use (&$integrated_disconnect_function)
{
	if(@$con->subserver !== null)
	{
		$con->subserver->close();
	}
	else
	{
		$integrated_disconnect_function($con);
	}
};
$next_tick = microtime(true) + 0.05;
do
{
	$start = microtime(true);
	$server->accept();
	$server->handle();
	foreach($server->clients as $con)
	{
		try
		{
			if(@$con->subserver !== null && $packet_id = $con->subserver->readPacket(0))
			{
				$packetId = ClientboundPacketId::getById($packet_id, $con->subserver->protocol_version);
				if(in_array($packetId->name, [
					"entity_animation",
					"entity_effect",
					"entity_metadata",
					"entity_velocity"
				]))
				{
					$packet = $packetId->getInstance($con->subserver);
					assert($packet instanceof EntityPacket);
					$packet->replaceEntity($con->server_eid, $con->eid);
					$packet->send($con);
				}
				else if($packetId->name == "keep_alive_request")
				{
					KeepAliveRequestPacket::read($con->subserver)
										  ->getResponse()
										  ->send($con->subserver);
				}
				else if($packetId->name == "disconnect")
				{
					$con->startPacket("clientbound_chat_message");
					$con->writeString($con->subserver->readString());
					$con->writeByte(1);
					$con->send();
					$con->subserver->close();
					$con->subserver = null;
					break;
				}
				else if($packetId->name == "join_game")
				{
					$packet = JoinGamePacket::read($con->subserver);
					$con->server_eid = $packet->eid;
					if($con->dimension === null)
					{
						$packet->eid = $con->eid;
						$con->dimension = $packet->dimension;
						$packet->send($con);
					}
					else
					{
						if($packet->dimension == $con->dimension)
						{
							$con->startPacket("respawn");
							$con->writeInt($packet->dimension == Dimension::OVERWORLD ? Dimension::END : Dimension::OVERWORLD);
							$con->writeByte(0);
							$con->writeString("");
							$con->send();
						}
						$con->startPacket("respawn");
						$con->writeInt($con->dimension = $packet->dimension);
						$con->writeByte($packet->gamemode);
						$con->writeString("");
						$con->send();
					}
				}
				else if($packetId->name == "teleport")
				{
					$con->subserver->startPacket("teleport_confirm");
					$con->subserver->writeVarInt($con->subserver->ignoreBytes(33)->readVarInt());
					$con->subserver->send();
					$con->write_buffer = $con->subserver->read_buffer;
					$con->send();
				}
				else if($con->transform_packets && ($packet = $packetId->getInstance($con)))
				{
					$packet->send($con);
				}
				else if($packetId->since_protocol_version <= $con->protocol_version)
				{
					$con->write_buffer = $con->subserver->read_buffer;
					$con->send();
				}
			}
		}
		catch(Exception $e)
		{
			$con->disconnect("[Surrogate] ".$e->getMessage());
		}
	}
	while($msg = $server->ui->render(true))
	{
		if(Command::handleMessage($server, $msg) || PluginManager::fire(new SurrogateConsoleEvent($server, $msg)))
		{
			continue;
		}
		$msg = [
			"translate" => "chat.type.announcement",
			"with" => [
				[
					"text" => "Surrogate"
				],
				[
					"text" => $msg
				]
			]
		];
		$server->broadcast($msg);
	}
	if($next_tick < microtime(true))
	{
		$next_tick += 0.05;
		$lagging = $next_tick < microtime(true);
		PluginManager::fire(new SurrogateTickEvent($server, $lagging));
		PluginManager::fire(new ServerTickEvent($server, $lagging));
	}
	if(($remaining = (0.001 - (microtime(true) - $start))) > 0)
	{
		time_nanosleep(0, $remaining * 1000000000);
	}
}
while($server->isOpen());
$server->ui->add("Surrogate is not listening on any ports and has no clients, so it's shutting down.");
$server->ui->render();
