# Phpcraft Surrogate

A Minecraft: Java Edition reverse proxy based on Phpcraft.

## Prerequisites

You'll need PHP, Composer, and Git.

### Instructions

- **Debian**: `apt-get -y install php-cli composer git`
- **Windows**:
  1. Install [Cone](https://getcone.org), which will install the latest PHP with it.
  2. Run `cone get composer` as administrator.
  3. Install [Git for Windows](https://git-scm.com/download/win).

## Setup

First, we'll clone the repository and generate the autoload script:

```Bash
git clone https://github.com/Phpcraft/surrogate
cd surrogate
composer install --no-dev --no-suggest --ignore-platform-reqs
```

Next, we'll run a self check:

```Bash
php vendor/craft/core/selfcheck.php
```

If any dependencies are missing, follow the instructions, and then run the self check again.

### That's it!

Now that you've got Surrogate all set up, you can start it using:

```Bash
php surrogate.php
```

## Updating Surrogate

To update Surrogate and its dependencies:

``` Bash
git stash
git pull
composer update --no-dev --no-suggest --ignore-platform-reqs
git stash pop
``` 

If you have made local changes, they will be saved and re-applied after the update.

## Configuration

After having started Surrogate at least once, you will find a "config" folder containing the "Phpcraft Surrogate.json" configuration file.

### Sub-servers

- "address" is the address Surrogate will connect clients to. The value of this may also be "phpcraft integrated server" so Surrogate will utilize Phpcraft's integrated server to act as a server itself. What you use this for is up to you.
- "default" indicates that this server is for new clients. If you have multiple default servers, one will be chosen at random. If the chosen server is offline, Surrogate will keep trying a random server until the list is exhausted and the client is disconnected.
- "host" indicates that if a client connected to Surrogate using that particular address, they should be put on this sub-server. Note that SRV records are pre-connection redirects, so if _minecraft._tcp.example.com points to mc.example.com which is an A or AAAA record, the host will be mc.example.com:25565.
- "restricted" indicates that the client needs the `connect to <server name>` permission to connect to this sub-server.

## Configuring sub-servers

Make sure your sub-servers are in offline mode, as Surrogate will not be able to authenticate on behalf of players.

On non-vanilla servers, you may an option to enable reverse proxy compatibility, so plugins on your sub-servers can still use the UUID and IP address of players. On Spigot, this option is called "bungeecord," just like the [bad](https://twitter.com/timmyRSde/status/1163853751033225216) reverse proxy made by the same person.

Make sure that you have a firewall set up to disallow any external connections to sub-servers, as otherwise anyone could imitate anyone, including OPs. ***You probably don't want that.***
