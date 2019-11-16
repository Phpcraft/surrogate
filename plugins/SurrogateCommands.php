<?php /** @noinspection PhpUndefinedFieldInspection */
/**
 * @var Plugin $this
 */
use Phpcraft\
{ClientConnection, Plugin};
$this->registerCommand( ["join", "goto", "server"], function(ClientConnection $con, string $server_name)
{
	if($error = connectToServer($con, strtolower($server_name)))
	{
		$con->sendMessage([
			"text" => $error,
			"color" => "red"
		]);
	}
}, "use /join");
$this->registerCommand("servers", function(ClientConnection $con)
{
	$targets = [];
	foreach($con->getServer()->config["servers"] as $name => $server)
	{
		if(empty($server["restricted"]) || $con->hasPermission("connect to ".$name))
		{
			array_push($targets, $name);
		}
	}
	$con->sendMessage("You can join ".count($targets)." server".(count($targets) == 1 ? "" : "s").": ".join(", ", $targets));
}, "use /servers");
