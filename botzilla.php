<?php
/**
BOTZILLA!! PHP IRC bot
@author bibby <bibby@surfmerchants.com>
Configuration file and executable
This software is provided 'as-is', without any express or implied warranty.
In no event will the authors be held liable for any damages arising from the
use of this software. It may be freely modified and redistributed, even in commercial applications.
*/

set_time_limit(0);
error_reporting(E_ALL ^ E_NOTICE);

require_once "cls/botzilla.class.php";

// server info
$bot_config = array(
	// irc server
	'server' => "irc.freenode.net",
	'port' => "6667",
	
	// the name for your bot. ("botzilla", unfortunately is registered to me on freenode)
	'nick' => "mybot",
	'realname' => "mybot",
	
	// local server names
	'hostname' => 'yourhost',
	'servername' => 'yourserver',
	
	// remark when users are kicked.
	'diss'=>true
);


// channels to join after it connects.
$channels = array('#botzilla');

// if your nick is registered, give the passwd here.
//$nick_passwd = 'irc_passwd';

// instantiate bot class
$botzilla = new botzilla($bot_config);

// turn logging on (off by default)
$botzilla->logging(true);

// nicks that have a higher level of access (to functions like QUIT)
$owners=array('yournick','anothernick');  // your nick(s) here

// set owners
$botzilla->setOwner($owners);


/** "ziggis", ( the command plugins in ziggi/ ) get added here.
You can add singulars with addZiggi(), but addZiggis() is basically the same thing in bulk.
Items added with no additional params, or are added as strings instead of arrays, assume that the user permission
set as BZUSER_DEFAULT, which I keep at BZUSER_REGISTERED.

# no params
addZiggi('somefile.class.php');

To set a repeating event at a regular interval, add a 'cron' item to the parameters array:

# your_command each hour
addZiggi('your.class.php' , array
(
	'access'=>BZUSER_REGISTERED,
	'cron'=>array
	(
		'cmd'=>'your_command',
		'interval'=>3600,
		'channel'=>'#channel_name'
	)
));

This would send 'your_command' through your class every hour from start time.
Because the default is to send return messages back to "where the requeswt came from", we declare a channel/nick here.
The alarm plugin is better if you want repeating events at an absolute time.
*/

// load ziggi plugins
$botzilla->addZiggis(array
(
	array ( 'reg.class.php' ,  array ( 'access'=>BZUSER_ANYONE ) ),
	array ( 'operator.class.php', array ( 'access'=>BZUSER_OWNER ) )
	#, 'example.class.php'
	
));


// start the bot!
while($botzilla->reconnect)
{
	// connect to network
	if(!$botzilla->connect())
		exit; // a message should have already printed
	
	// join specified channels
	if(is_array($channels))
		foreach ($channels as $c)
			$botzilla->join($c,TRUE);
	
	// identify with nickserv (change name as needed).
	if($nick_passwd)
		$botzilla->pm("identify $nick_passwd", 'nickserv');
		
	// this loop continues as long as the bot is connected.
	$botzilla->listen();
	
	// being here means that we've lost our connection
	
	// did we say QUIT ?
	if(!$botzilla->reconnect)
	{
		echo "Exiting\n";
		exit;
	}
	
	// the bot will reconnect if we didn't.
	echo "brb, restarting..\n";
	unset($botzilla->connection);
	sleep(10);
}
?>
