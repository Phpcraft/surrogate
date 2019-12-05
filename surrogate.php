<?php /** @noinspection PhpUndefinedFieldInspection */
echo "Phpcraft Surrogate (Minecraft Reverse Proxy)\n\n";
if(empty($argv))
{
	die("This is for PHP-CLI. Connect to your server via SSH and use `php surrogate.php`.\n");
}
require __DIR__."/vendor/autoload.php";
use pas\pas;
use Phpcraft\
{ClientConnection, Command\Command, Event\ProxyConsoleEvent, Event\ProxyJoinEvent, Exception\IOException, PluginManager, ProxyServer};
/**
 * @var ProxyServer $server
 */
$server = ProxyServer::cliStart("Phpcraft Surrogate", [
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
	/**
	 * @var ProxyServer $server
	 */ global $server;
	$subserver = @$server->config["servers"][$server_name];
	if($subserver === null)
	{
		return "Unknown server: $server_name";
	}
	if($subserver["address"] == "phpcraft integrated server")
	{
		$server->connectToIntegratedServer($con);
		return null;
	}
	else
	{
		return $server->connectDownstream($con, $subserver["address"]);
	}
}

$server->join_function = function(ClientConnection $con) use (&$server)
{
	if(PluginManager::fire(new ProxyJoinEvent($server, $con)))
	{
		$con->close();
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
pas::on("stdin_line", function(string $msg) use (&$server)
{
	if(!Command::handleMessage($server, $msg) && !PluginManager::fire(new ProxyConsoleEvent($server, $msg)))
	{
		$server->broadcast([
			"translate" => "chat.type.announcement",
			"with" => [
				[
					"text" => "Surrogate"
				],
				[
					"text" => $msg
				]
			]
		]);
	}
});
pas::loop();
$server->ui->add("Surrogate is not listening on any ports and has no clients, so it's shutting down.");
$server->ui->render();
