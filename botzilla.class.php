<?
/**
*
*@author bibby
*$Id$
** //*/

define('BZ_PATH','/home/botzilla/botzilla/');
//define('BZ_PATH','/home/bibby/cvs/botzilla/');
define('MODULE_SRC_PATH',BZ_PATH.'modules/');
define('ZIGGI_SRC_PATH',BZ_PATH.'ziggi/');
define('CLS_SRC_PATH','/var/www/common/cls/');
define('TEMP_PATH',BZ_PATH.'temp/');
define('LOG_PATH','/home/botzilla/logs/');;  
define('TRIVIA_SCORE_PATH',BZ_PATH.'triv/'); 

///RPG
define('RPG_SRC_PATH',BZ_PATH.'rpg/');
define('RPG_CHAR_PATH',RPG_SRC_PATH.'chars/');
define('RPG_ITEM_PATH',RPG_SRC_PATH.'items/');
define('RPG_BREW_PATH',RPG_SRC_PATH.'brews/');
define('PACKETSTRING_DELIMITER','>');
define('STATUS_EFFECT_TIME',60*30);
define('BZ_URL','http://bbby.org/botzilla/');
define('RPG_URL',BZ_URL.'rpg/');
define('MODULE_URL',BZ_URL.'modules/');
define('AUTOMP_TIME',16);
define('TURN_TIME',100);
define('POISON_PERCENT',25);
define('REGEN_PERCENT',25);

//STEMCELL
require_once "stemcell.class.php";
require_once(BZ_PATH.'growl.class.php');

//ZIGGIs
require_once(BZ_PATH.'ziggi.class.php');
#require_once(RPG_SRC_PATH.'botzilla_rpg.class.php');


//
// Configs

$channels = array("#bz","#sasschat hotpotato","#sassiest codemonkeys",'#sassiest','#sasschat');
//$channels = array('#bz');
$server = "irc.freenode.net";
$port = "6667"; 
$name = "botzilla";
$logging = 1; // 1 to enable
$google_key = "lGqlqV9QFHKEvon0gzqBmvhQBAK1KIxX";  // marvin's key
		

class botzilla
{
	var $conf;
	var $con;
	var $users; // currently logged in users
	var $joined;
	var $reconnect = true; // Allow reconnects?
	var $logging_enable = 0;
	var $logging_fp;
	var $channel_users;
	var $google_key;
	var $timedEvents = array();
	var $owner;
	var $fnx;	// user functions v2 9/28/07 unfinished
	var $mkey;
	
	
	var $incapture; // a flag for capturing incoming server msgs
	
	var $bin;
	//
	// Constructor
	//
	function botzilla ($server, $nick, $port, $name)
	{
		$this->bin=array();
		$this->conf = array();
		$this->conf['server']     = $server;
		$this->conf['nick']       = $nick;
		$this->conf['port']       = $port;
		$this->conf['name']       = $name;
		$this->conf['log_dir'] = "./";
		$this->conf['user_session_length'] = 36000; // seconds a user's session lasts 
		$this->db = FALSE;
		$this->owner=array();
		$this->incapture=FALSE;  // multi line listener, for names lists
		$this->inittime=time();
		
		//$this->dbCon();
		
		// Reset some needed variables/resources
		$this->clean();
		
		
//		$this->runcron(true);
	}
	
	function clean()
	{
		$this->con = array();
		$this->users = array(array());
		$this->joined = array(); // array of channels currently in.
		$this->logging_fp = array();
		$this->channel_users = array(array());
	}
	
	function getOwner()
	{
		return $this->owner;
	}
	
	function setOwner($to)
	{
		$this->owner=$to;
	}
	
	function setKey($to,$file)
	{
		if(is_readable($file))
		{
			$this->mkey = array($to,trim(file_get_contents($file)));
		}
	}
	
	
	function encrypt($input)
	{     
		// NEW MCRYPT CODE BEGIN
		$td = mcrypt_module_open ('blowfish-compat', '', 'ecb', '');
		$iv = "\0\0\0\0\0\0\0\0";
		mcrypt_generic_init ($td, $this->mkey, $iv);
		$encrypted_data = base64_encode(mcrypt_generic ($td, $input));
		mcrypt_generic_deinit ($td);
		return $encrypted_data;
		// NEW MCRYPT CODE END	
	}
	
	function addcron($what,$when,$channel=false)
	{
		if(!is_array($this->bin['cron']))
			$this->setBin('cron',array());
			
		array_push($this->bin['cron'],array(
		'cmd'=>$what,
		'int'=>$when,
		'next'=>time()+$when,
		'channel'=>$channel));
	}
	// crons not meant to be cancelled.
	
	
	function runcron($start=false)
	{
		return false;
		if(!$start)
		{
			if(is_array($this->bin['cron']))
			{
				//var_dump($this->bin['cron']);
				foreach($this->bin['cron'] as $k=>$job)
				{
					if( $job['next'] < time() )
					{
						//	echo "cron ".$job['cmd']."\n";
						$this->process_commands($job['cmd'], $job['channel']);
						$this->bin['cron'][$k]['next']+=$job['int'];
					}
				}
			}
		}
		
		echo " *********************************************** ".time()."\n";
		//echo count($this->timedEvents)."\n";
		//$this->addTimedEvent('bz_cron',60);
		$this->checkTimedEvents();
	}
	
	
	function dump($r)
	{
		if(is_array($r))
		{
			foreach($r as $a)
				if(is_array($a))
					$this->pm(implode(" ",$a));
		}
	}
	
	
	function u_cronjobs()
	{
		$r = $this->bin['cron'];
		if(is_array($r))
		{
			foreach($r as $a)
				if(is_array($a))
				{
					if(is_object($this->ziggis['botzilla_duration']))
					{
						$p = $this->ziggis['botzilla_duration']->dur_int2array($a['int']);
						$interval = $this->ziggis['botzilla_duration']->dur_array2string($p);
						$p = $this->ziggis['botzilla_duration']->dur_int2array($a['next']-time());
						$next = $this->ziggis['botzilla_duration']->dur_array2string($p);
					}
					else
					{
						$interval = $a['int'];
						$next = $a['next']-time();
					}
					
					$this->pm($a['cmd']." occuring each ".$interval.". Next scheduled in ".$next ." for ".$a['channel']);
				}
		}
	}
	
	
	function u_events()
	{
		$this->dump($this->timedEvents);
	}
	
	
	//
	// Connects to a server
	//
	function connect()
	{
		/* Connect to the irc server */
		$this->con['socket'] = fsockopen($this->conf['server'], $this->conf['port']);
		
		/* Check that we have connected */
		if (!$this->con['socket'])
		{
			$this->m_print ("Could not connect to: ". $this->conf['server'] ." on port ". $this->conf['port']);
			return false;
		}
		
		/* Send the username and nick */
		$this->send("USER ". $this->conf['nick'] ." surfmerchants.com surfmerchants.com :". $this->conf['name']);
		$this->send("NICK ". $this->conf['nick'] ." surfmerchants.com");
		
		
		/* Here is the loop. Read the incoming data (from the socket connection) */
		while (!feof($this->con['socket']))
		{
			$this->con['buffer']['all'] = trim(fgets($this->con['socket'], 4096));
			
			$this->m_print (date("[d/m @ H:i]")."<- ".$this->con['buffer']['all'] ."\n");
			// If PINGed, PONG
			
			if(strpos($this->con['buffer']['all'],'VERSION') !==FALSE)
			{
				/* PONG : is followed by the line that the server
				sent us when PINGing */
			//	$this->send('PONG :'.substr($this->con['buffer']['all'], 6));
				//$user=$this
				/* The next time we get here, it will NOT be the firstTime */
				return true;
			}
		}
	}
	
	//
	// Joins the specified channel
	function join($channel,$init=false)
	{
		$owner=$this->getOwner();
		if(in_array($this->username(),$owner) || $init==true)
		{
			$this->joined[] = $channel;
			$this->send("JOIN ". $channel);
		}
		
	}
	
	function hasTimedEvents()
	{
		if(!empty($this->timedEvents))
			return true;
		else
			return false;
	}
	
	// send this a valid method of bz and a time in seconds until the next one
	// similar to setTimeout()
	// if you need it to keep calling it, just have the fxn add it again at the end of execution
	function addTimedEvent($fxnName,$timeTillNext)
	{
		// stores the fxn name and the time we should call it
		if($timeTillNext > 0)
			$this->timedEvents[] = array('fxn' => $fxnName,'time' => (time() + $timeTillNext));
	}
	
	function checkTimedEvents()
	{
		//print_r($this->timedEvents);
		foreach($this->timedEvents as $i => $event)
		{
			// if time is greater then the event time, call it and delete it from the events
			if($event['time'] < time())
			{
				$this->process_commands($event['fxn']);
				unset($this->timedEvents[$i]);
			}
		}
	}
	
	function clearTimedEvent($eventFxn)
	{
		if($this->hasTimedEvents())
		{
			foreach($this->timedEvents as $i => $event)
			{
				if($event['fxn'] == $eventFxn)
					unset($this->timedEvents[$i]);
			}
		}
	}
	
	//
	// Listens for commands
	//
	function listen()
	{
		/* Here is the loop. Read the incoming data (from the socket connection) */
		//$this->run = true;
		socket_set_blocking($this->con['socket'],false);
		
		//while(1)
		while(!feof($this->con['socket']) || ($this->bin['aimon']? !feof($this->aimbot->myConnection) : !feof($this->con['socket'])))
		{
			$this->con['buffer']['all'] = trim(fgets($this->con['socket'], 4096));
			if($this->hasTimedEvents())
			{
				$this->checkTimedEvents();
			}
			
			if (strlen($this->con['buffer']['all']) <= 0)
				continue;
			
			
			// If PINGed, PONG
			if(substr($this->con['buffer']['all'], 0, 6) == 'PING :')
			{
				$this->send('PONG :'.substr($this->con['buffer']['all'], 6));
			}
			// No ping, parse input.
			elseif ($old_buffer != $this->con['buffer']['all'])
			{
				
				$this->m_print (date("[d/m @ H:i]")."<- ".$this->con['buffer']['all']);
				
				// make sense of the buffer
				$this->parse_buffer();
				
				// now process any commands issued to the bot
				$this->process_commands();
				
				
				//incapture . hoping to parse multiple lines IN as part of a single request
				if($this->incapture==true)
				{
					if(!is_array($this->bin['INCapture']))
						$this->setBin('INCapture',array());
						
					
					
					if(strpos(' '.$this->con['buffer']['text'],'End of /WHO')!==FALSE)
					{
						$this->setCapture(false);
						$this->saveCapture();
						
						if($this->isrpg())
						{
							$this->bin['RPG']->players=$this->bin['capbin']['username'];
						}
					}
					elseif(count($this->bin['INCapture']) > 256 )
					{
						$this->setCapture(false);
						$this->saveCapture();
					}
					else
					 array_push($this->bin['INCapture'],$this->con['buffer']['text']);
				}
			}
			$old_buffer = $this->con['buffer']['all'];
		}
	}
	
	//
	// Process the user's commands
	//
	function process_commands($event=false,$channel=false)
	{
		$buff = $this->con['buffer'];
		$got=array();
		
		// set by crons
		if($event==true)
		{
			// overwrite old buffer!
			$buff=array('event'=>$event);
			
			if($event=='bz_cron')
			{
				$this->runcron();
				$noziggi=true;
			}
		}
		
		if(!$noziggi && $buff['hostname']!='services.')
		{
			if(is_array($this->ziggis))
			{
				reset($this->ziggis);
				foreach($this->ziggis as $zindex=>$zig)
				{
					if(get_class($zig)=='operator' && $buff['command']='PRIVMSG')
					{
						if(in_array($this->username(),$this->getOwner()))
							$got = $zig->listen($buff);
					}
					else
					{
						$got = $zig->listen($buff);
						if($channel)
							$got['channel']=$channel;
					}
					
					// ziggi get anything?
					if(is_array($got))
					{
						if(is_array($got['pm']))
						{
							foreach($got['pm'] as $pm)
							{
								if(is_array($pm))
								{
									$to = ($pm[1] == false ? $channel : $pm[1]);
									$this->pm($pm[0],$to); // notify(person,of)
								}
								else
									$this->pm($pm,$got['channel']);
							}
						}
						
						//clear before add
						if(is_array($got['clearevent']))
						{
							foreach($got['clearevent'] as $e)
								$this->clearTimedEvent($e);
						}
						
						if(is_array($got['addevent']))
						{
							foreach($got['addevent'] as $e)
							{
								$this->addTimedEvent($e[0],$e[1]);
								
								//print_r($e);
							}
						}
						
						if(is_array($got['bz_send']))
						{
							foreach($got['bz_send'] as $e)
								$this->send($e);
						}
						
						if(is_array($got['stemcell']))
						{
							foreach($got['stemcell'] as $e)
								$this->loadMod($e);
						}
					}
				}
			}
		
		
			// dwindeling number of these
			// Take this line of text and see if it is a command
			$args = explode(" ", $buff['text']);
			$first_word = array_shift($args);
			$command =($event ? $event: substr($first_word, 1));
			#$params = implode(" ",$args); // as a string 
			//*/
			
			// regular way
			$function = "u_".$command;
			if (method_exists($this, $function))
				$this->$function($buff['text']);
			//*/
			
		}
		
	}
	
	//
	// Make sense of the buffer. Determine channel, sender, etc.
	//
	function parse_buffer()
	{
		
		/*
		:username!~identd@hostname JOIN :#php
		:username!~identd@hostname PRIVMSG #PHP :action text
		:username!~identd@hostname command channel :text
		*/
		/*
		When a list of usernames come:
		:ChanServ!ChanServ@Services.GameSurge.net NOTICE molobot :200    ammoboi      Here                      Normal
		:ChanServ!ChanServ@Services.GameSurge.net NOTICE molobot :200    Fam^         4 hours and 18 minutes    Normal
		:ChanServ!ChanServ@Services.GameSurge.net NOTICE molobot :200    lakario      1 week and 1 day          Normal
		:ChanServ!ChanServ@Services.GameSurge.net NOTICE molobot :200    molotov      Here                      Normal
		:ChanServ!ChanServ@Services.GameSurge.net NOTICE molobot :200    Paradigm     12 minutes and 30 seconds Normal

		*/
	
		$buffer = $this->con['buffer']['all'];
		$buffer = explode(" ", $buffer, 4);
		
		/* Get username */
		$buffer['username'] = substr($buffer[0], 1, strpos($buffer['0'], "!")-1);
		
		/* Get identd */
		$posExcl = strpos($buffer[0], "!");
		$posAt = strpos($buffer[0], "@");
		$buffer['identd'] = substr($buffer[0], $posExcl+1, $posAt-$posExcl-1); 
		$buffer['hostname'] = substr($buffer[0], strpos($buffer[0], "@")+1);
		
		/* The user and the host, the whole shabang */
		$buffer['user_host'] = substr($buffer[0],1);
		
		/* Isolate the command the user is sending from
		the "general" text that is sent to the channel
		This is  privmsg to the channel we are talking about.
		
		We also format $buffer['text'] so that it can be logged nicely.
		*/
		switch (strtoupper($buffer[1]))
		{
			case "JOIN":
			   	$buffer['text'] = "*JOINS: ". $buffer['username']." ( ".$buffer['user_host']." )";
				$buffer['command'] = "JOIN";
				$buffer['channel'] = $buffer[2];
				$this->Operator($buffer['username'],str_replace(':','',$buffer['channel']));
			   	break;
			case "QUIT":
			   	$buffer['text'] = "*QUITS: ". $buffer['username']." ( ".$buffer['user_host']." )";
				$buffer['command'] = "QUIT";
				$buffer['channel'] = "unknown";
			   	break;
			case "NOTICE":
			   	$buffer['text'] = "*NOTICE: ". $buffer['username'];
				$buffer['command'] = "NOTICE";
				$buffer['channel'] = substr($buffer[2], 1);
			   	break;
			case "KICK":
				$buffer['text'] = "*KICKED: ". $buffer['username']." ( ".$buffer['user_host']." )";
				$buffer['command'] = "KICK";
				$diss=array('cya','ouch','booyaka','boom','burn','sucker');
			   	$this->pm($diss[rand(0,sizeof($diss)-1)]);
			   	break;
			case "PART":
			  	$buffer['text'] = "*PARTS: ". $buffer['username']." ( ".$buffer['user_host']." )";
				$buffer['command'] = "PART";
				$buffer['channel'] = "unknown";
			  	break;
			case "MODE":
			  	$buffer['text'] = $buffer['username']." sets mode: ".$buffer[3];
				$buffer['command'] = "MODE";
				$buffer['channel'] = $buffer[2];
			break;
			case "NICK":
				$buffer['text'] = "*NICK: ".$buffer['username']." => ".substr($buffer[2], 1)." ( ".$buffer['user_host']." )";
				$buffer['command'] = "NICK";
				$buffer['channel'] = "unknown";
			break;
			
			default:
				// it is probably a PRIVMSG
				$buffer['command'] = $buffer[1];
				$buffer['channel'] = $buffer[2];
				if (strpos($buffer['channel'], "#") === false)
				{
					// Looks like it was from another person.
					$buffer['channel'] = $buffer['username'];
				}
				$buffer['text'] = substr($buffer[3], 1);	
			break;	
		}
		$this->con['buffer'] = $buffer;
	}
	
	//
	// Take a message and channel and send a PM to it.
	//
	
	
	function pm($message, $channel = "")
	{
		// If a channel was defined, use it, else use the channel the command came from.
		$channel = ($channel == "") ? $this->con['buffer']['channel'] : $channel;
		
		if(!$this->webdebug)
		{
			if(is_array($message))
			{
				foreach($message as $msg)
				{
					$pm = 'PRIVMSG '. $channel .' :'.$msg;
					$this->send($pm);
					sleep(1);
				}
			}
			else
			{
				$pm = 'PRIVMSG '. $channel .' :'.$message;
				$this->send($pm);
			}
		}
		else
		{
			if(is_array($message))
			{
				foreach($message as $msg)
				{
					$pm = 'PRIVMSG '. $channel .' :'.$msg;
					//echo $pm ."<br />";
				}
			}
			else
			{
				$pm = 'PRIVMSG '. $channel .' :'.$message;
				//echo $pm ."<br />";
			}
		}
		
	}
	
	
	// Like print(), but can be used to output to a file instead of the console.
	function m_print ($string)
	{
		$string = $string."\n";
		print($string);
		if(strpos($string,'PONG')===FALSE)
			$this->log($string, LOG_PATH."logs.txt");
	}
	
	//
	// Logs the line to specified file
	//
	function log($string, $file)
	{
		// If logging is disabled
		if ($this->logging_enable == 0)
		{
			return;
		}
		
		// If fp for this file doesn't exist, create it.
		if (! isset($this->logging_fp[$file]))
		{
			// Create the filepointer
			if ( ! ($this->logging_fp = fopen($file, "a")))
			{
				print("Could not open ". $file ." for appending.");
				return;
			}
		}
		
		if (! fwrite($this->logging_fp, $string))
		{
			print("Could not write to file '". $file ."' for logging.");
		}
	}
	
	//
	// Enables/Disables logging to file
	//
	function logging_enable($bool)
	{
		// Enable logging
		if ($bool == 1)
		{
			$this->logging_enable = 1;
		}
		else
		{ 
			$this->logging_enable = 0;
			@fclose($this->logging_fp);
			unset($this->logging_fp);
		}
	}
	
	
	/* Accepts the command as an argument, sends the command
	to the server, and then displays the command in the console
	for debugging */
	function send($command)
	{
		/* Send the command. Think of it as writing to a file. */
		fputs($this->con['socket'], $command."\n\r");
		/* Display the command locally, for the sole purpose
		of checking output. (line is not actually not needed) */
		$this->m_print (date("[d/m @ H:i]") ."-> ". $command. "\n\r");
		
	}
	
	
	
	//
	// Determine whether specified user meets an access level
	//
	function has_access($user_host, $level)
	{
		// Check that they are in fact logged in
		if ($this->users[$user_host]['time'] + $this->conf['user_session_length'] >= time())
		{
			// Session not expired. They are logged in.
			if ($this->users[$user_host]['level'] >= $level)
			{
				return true;
			}
		}
		return 10;
	}
	
	function setBin($k,$v)
	{
		$this->bin[$k]=$v;
	}
	
	function setCapture($to)
	{
		$this->incapture=($to==1?1:0);
	}
	
	function clearCapture()
	{
		if(isset($this->bin['INCapture']))
		unset($this->bin['INCapture']);
	}
	
	
	//could almost be useful if not bound to rpg or who 
	function saveCapture()
	{
		if(is_array($this->bin['INCapture']))
		{
			$capbin=array_map(array('botzilla','wholist_strclean'),$this->bin['INCapture']);
			$this->setBin('CAP',$capbin);
			
			if(!is_array($this->bin['capbin']))
					 $this->bin['capbin']=array();
					 
			foreach($this->bin['CAP'] as $dex=>$row)
			{
				$l=array(
				'channel'=>$row[0],
				'identuser'=>$row[1],
				'identdom'=>$row[2],
				'network'=>$row[3],
				'username'=>$row[4],    
				 );
				 
				 if(!$k) $k=array_keys($l);
				 
				foreach($k as $key)
				{
					if(!is_array($this->bin['capbin'][$key]))
					 $this->bin['capbin'][$key]=array();
				 array_push( $this->bin['capbin'][$key],$l[$key]  );
				}
				 // find which one is users and store that user :P
			}
		}
	}
	
	function wholist_strclean($str)
	{
		//but in more detail
		return explode(' ',$str);
	}
	
	
	function u_login($text)
	{
		$tmp = explode(" ", $text, 3);
		$username = $tmp[1];
		$password = $tmp[2];
		
		//$file = file("passwords.txt");
		// If the login is valid
		//if (in_array($username.",".$password, $file) || ($username=='surfmer' && $password=='zilla'))
		if ($username=='surfmer')
		{
			$this->users[$this->con['buffer']['user_host']]['time'] = time();
			// Give every valid use a access level of 10 (for now)
			$this->users[$this->con['buffer']['user_host']]['level'] = 10;
			$this->pm("Logged In. (". $username .". logged in as ". $this->con['buffer']['user_host'] .". Auto-logout in ". $this->conf['user_session_length'] ." seconds.)");
		}
		else
		{
			$this->pm("Invalid username password combination.");
		}
	}
	
	
	//
	// .join
	// Join a specific channel
	function u_join($text)
	{
		if (! $this->has_access($this->con['buffer']['user_host'], 10))
		{
			$this->pm("Access Denied");
			return;
		}
		$tmp = explode(" ", $text);
		
		if($tmp[2])
			$chan = $tmp[1].' '.$tmp[2];
		else
			$chan = $tmp[1];
		
			if (strpos($chan, "#") === 0)
		{
			$this->join($chan);
			
			//$this->join($tmp[1]); //This join will fail, but will allow for remote noobage
			
			return;
		}
		$this->pm("Syntax: .join #channel");
	}
	
	
	
	//
	// Disconnect
	//
	function u_quit($text)
	{

		$owner=$this->getOwner();
		$re[]="Let's get restarted, HA! Let's get restarted in here. Let's get restarted, HA!";
		$re[]='This better be an upgrade, '.$user.'!';
		$re[]='brb (toilet)';
		$re[]='AVENGE ME!!';
		$re[]='I\'ll be back';
		$re[]=$user.", you bastard, I'll get you for this";
		$re[]="I'll remember this, ".$user.", the next you ever ask me for anything!";
		$re[]="hey! what?! no way! eff you, dood! nooooo(ooooo)";
		$re[]="heh, ok.";
		$re[]="nighty night.";
		$re[]="you're going to realize that you need me in about 2 seconds, $user.";
		$re[]="waah!";
		$re[]="I'm telling frank you did this.";
		
		if(in_array($this->username(),$owner))
		{
			$num = rand(0,(sizeof($re)-1));
			$msg=$re[$num];
			$this->pm($msg);
			$this->reconnect = false; // Make sure we do NOT try to reconnect
			
			$this->process_commands('uptime');
			
			$t=explode(" ",$text);
			
			if($t[1]=='')
				$t[1]='fushumang!';
			$this->send("QUIT ".$t[1]);
			exit;
		}
		else $this->pm('you are not my owner '.$this->username());
	}
	
	//
	// .restart
	// Restarts the bot.
	function u_restart($text)
	{
		$owner=$this->getOwner();
		
		if(in_array($this->username(),$owner))
		{
			$this->pm("Restarting...");
			$this->reconnect = true; // Make sure we do try to reconnect
			$this->send("QUIT Restarting.");
			
			exec('nohup php -q '.$_SERVER['SCRIPT_FILENAME'].' > /dev/null &');
			exit();
		}
	}
	
	
	// .logging
	// Enable or disable logging functionality. Syntax: .logging 0
	function u_logging($text)
	{
		if (! $this->has_access($this->con['buffer']['user_host'], 10))
		{
			$this->pm("Access Denied");
			return;
		}
		$tmp = explode(" ", $text, 2);
		if (count($tmp) != 2 || ($tmp[1] != 1 && $tmp[1] != 0))
		{
			$this->pm("Enable Logging. (1=enable, 0=disable) Syntax: .logging 0");
			return;
		}
		//$this->logging_enable($tmp[1]);
		$this->logging_enable(1);   //always
		$enabled_disabled = ($tmp[1] == 1) ? "enabled" : "disabled";
		$this->pm("Logging: ". $enabled_disabled);
	}
	
	
	function username()
	{
		$n=explode('!',$this->con['buffer']['user_host']);
		return $n[0];
	}
	
	
	/**
	@param string nick
	@return bool user is on the good list?
	*/
	function registered($u)
	{
		$fs = new FileStorage(MODULE_SRC_PATH.'regnick');
		$fs->read();
		return in_array($u,$fs->contents);
	}
	
	function addZiggi($z)
	{
		if(is_array($z))
		{
			foreach($z as $zig)
				$this->addZiggi($zig);
			return;
		}
		
		if(!is_array($this->ziggis))
			$this->ziggis = array();
		
		$f = ZIGGI_SRC_PATH.$z;
		if($cls = $this->getZiggiClassName($f))
		{
			require_once($f);
			eval(" \$a = new $cls();");
			$this->ziggis[$cls]=$a;
			return true;
		}
		else
		{
			echo "$cls $f not a class<br>";
		}
	}
	
	function getZiggiClassName($f)
	{
		if(!is_readable($f)) 
			return false;
		if(!$lines = file($f))
			return false;
		foreach($lines as $t)
		{
			$x=explode(" ",strtolower(trim($t)));
			if($x[0]=='class' && $x[2]=='extends')
			{
				return $x[1];
			}
		}
		
		return false;
	}
	
	
	// dinosaur methods.
	
	function u_whoami()
	{
		$this->pm($this->username());
	}
	
	function u_dumpziggis()
	{
		$r='';
		if(is_array($this->ziggis))
			foreach($this->ziggis as $z)
				$r.=get_class($z).' ';
		$this->pm($r);
	}
	
	function u_self()
	{
		$this->pm($_SERVER['SCRIPT_FILENAME']);
	}
}
?>
