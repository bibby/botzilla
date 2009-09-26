<?php
/**
* BOTZILLA !!!  PHP IRC BOT with plugin ability. rawr
*@author bibby <bibby@surfmerchants.com>
*$Id: botzilla.class.php,v 1.8 2008/07/10 01:51:59 abibby Exp $

This software is provided 'as-is', without any express or implied warranty.
In no event will the authors be held liable for any damages arising from the
use of this software. It may be freely modified and redistributed, even in commercial applications.
** //*/

// command character for user functions.
// while not necessary, it is recommended
define('CMD_CHAR','.');

// path where botzilla.php exists
define('BZ_PATH',$_SERVER['PWD'].'/');

// where the core classes are found
define('BZ_CLS_PATH',BZ_PATH.'cls/');

// directory where ziggi classes are found
define('ZIGGI_SRC_PATH',BZ_PATH.'ziggi/');

// directory for permanent data
define('PERM_DATA',BZ_PATH.'perm_data/');
define('REG_USERS_FILE',PERM_DATA.'known_users');

// log directory and file
define('LOG_PATH',BZ_PATH.'logs/');
define('LOG_FILE',LOG_PATH.'log');

// user levels
define('BZUSER_ANYONE',1);
define('BZUSER_REGISTERED',2);
define('BZUSER_OWNER',3);

// define a default user level to access ziggis
// using BZUSER_REGISTERED uses the reg class
define('BZUSER_DEFAULT',BZUSER_REGISTERED);

// character definitions
define('PIPE_CHAR', ' | '); // pipe defined as space-pipe-space to be nice to sed/awk plugins
define('CARET_CHAR', '^'); // definition for history plugin. (perhaps should make it's own)


//ZIGGI
require_once(BZ_CLS_PATH.'ziggi.class.php');

//UTILS
require_once(BZ_CLS_PATH."FileStorage.class.php");
require_once(BZ_CLS_PATH."Color.class.php");
require_once(BZ_CLS_PATH."Duration.class.php");

// BZ_CLS_PATH is a good place for other class files
#require_once(BZ_CLS_PATH."xmlparser.class.php");
#require_once(BZ_CLS_PATH.'growl.class.php');

class botzilla
{
	var $cfg; // configuration array
	var $connection; // socket
	var $buffer;
	var $reconnect = true; // reconnection attempts on by default
	var $logging = false; // logging off by default (done manually)
	var $logfile;
	var $owner;
	
	// Constructor
	function botzilla ($bot_config)
	{
		$this->cfg = $bot_config;
		$this->owner=array();
		$this->pwds = array();
		$this->connection = FALSE;
	}
	
	/**
	is this user an owner of the bot?
	*/
	function isOwner($nick=false)
	{
		if(!$nick)
			$nick=$this->username();
		return in_array($nick,$this->owner);
	}
	
	/**
	@param mixed user nick
	*/
	function setOwner($to)
	{
		if(!file_exists(REG_USERS_FILE))
		{
			$fs = new FileStorage(REG_USERS_FILE,FS_WRITE);
			$fs->write($to);
		}
		
		if(is_scalar($to))
			$to=array($to);
		
		$this->owner=$to;
	}
	
	/**
	@param string name. a handle for the pwd key
	@param string the decrypt key
	@param string path/to/passwd , which is encrypted
	*/
	function setKey($name,$key,$file)
	{
		if(is_readable($file))
		{
			$this->pwds[$name] = array($key,trim(file_get_contents($file)));
			return true;
		}
		
		return false;
	}
	
	/**
	@param string a key handle, "dev"
	@return array the requested keys
	*/
	function getKey($name=false)
	{
		if($this->pwds[$name])
			return $this->pwds[$name];
			
		return false;
	}
	
	
	/**
	connect to the server using the internal config
	@param void
	*/
	function connect()
	{
		// make a socket connection
		$this->connection = fsockopen($this->cfg['server'], $this->cfg['port']);
		
		if (!$this->connection)
		{
			$this->output ("A connection with server ". $this->cfg['server'] ." could not be established using port ". $this->cfg['port']);
			return false;
		}
		
		$this->send("USER ". $this->cfg['nick'] ." ".$this->cfg['hostname']." ".$this->cfg['servername']." :". $this->cfg['realname']);
		$this->send("NICK ". $this->cfg['nick'] ." ".$this->cfg['hostname']);
		
		while (!feof($this->connection))
		{
			$this->buffer['raw'] = trim(fgets($this->connection, 4096));
			
			$this->output (date("[d/m @ H:i]")."<- ".$this->buffer['raw'] ."\n");
			
			/** Wait for the End of MOTD.
			This line, letting you know that you're connected and it's ok to proceed may vary of other networks.
			If the bot looks connected, but "hangs", not joining any channels. Change this to something in their last line.
			*/ 
			if(strpos($this->buffer['raw'],'End of /MOTD command.') !==FALSE)
				return true;
		}
	}
	
	/**
	Join a channel. Other occurances of join should be handled by the operator plugin.
	@param string channel name
	@param bool override ownership (used only at startup)
	@return void
	*/
	function join($channel,$init=false)
	{
		if($init==true || $this->isOwner())
			$this->send("JOIN ". $channel);
	}
	
	/**
	The main business of the bot. While a connection exists it will
	forward all input to the plugin classes and issue any output they have have.
	logging if on.
	@return void
	*/
	function listen()
	{
		socket_set_blocking($this->connection,false);
		while(!feof($this->connection))
		{
			// listen once only every so often. This keeps your load way down.
			usleep(250 * 1000);
			
			$this->buffer['raw'] = trim(fgets($this->connection, 4096));
			
			// If all is quiet, proceed once per second. (letting ziggis do timed events)
			if (strlen($this->buffer['raw']) <= 0)
			{
				if($t==time())
					continue;
			}
			
			$t=time();
			
			// respond to PINGs
			if(substr($this->buffer['raw'], 0, 6) == 'PING :')
			{
				$this->send('PONG :'.substr($this->buffer['raw'], 6));
				continue;
			}
			
			// act on only new text
			if($last != $this->buffer['raw'])
			{
				$this->output (date("[d/m @ H:i]")."<- ".$this->buffer['raw']);
			}
			
			// make sense of the buffer
			$this->parseBuffer();
			// now process any commands issued to the bot
			$this->process();
			
			$last = $this->buffer['raw'];
		}
	}
	
	/**
	Send the formatted buffer text back to
	@return void
	*/
	function process()
	{
		$buff =& $this->buffer;
		
		if($buff['hostname']!='services.') // something to thrwart server MOD
		{
			if(is_array($this->ziggis))
			{
				$pipeParts = explode(PIPE_CHAR,$buff['text']);
				foreach($pipeParts as $pipeIndex=>$in)
				{
					if(!is_array($pipeOut))
						$pipeOut=array($in);
						
					foreach($pipeOut as $pipeIn)
					{
						$buff['pipe']=trim($pipeIn);
						$buff['text']=trim($in);
						
						reset($this->ziggis);
						
						foreach(array_keys($this->ziggis) as $zindex)
						{
							if(!is_object($this->ziggis[$zindex]))
							{
								echo "problem with ziggi $zindex !\n";
								unset($this->ziggis[$zindex]);
								continue;
							}
							
							$got=null;
							$access=$this->getUserAccessLevel($this->username());
							$hasAccess = ($access >= $this->ziggis[$zindex]->user_access);
							
							#\|/
							$got = $this->ziggis[$zindex]->listen($buff,$hasAccess);
							#/|\
							
							// ziggi get anything?
							if(is_array($got))
							{
								// send any server commands (quit, kick, etc)
								if(is_array($got['bz_send']))
								{
									foreach($got['bz_send'] as $e)
										$this->send($e);
								}
								
								
								if(is_array($got['pm']))
								{
									// final output
									if($pipeIndex==max(array_keys($pipeParts)))
									{
										foreach($got['pm'] as $pm)
										{
											if(is_array($pm))
											{
												$to = ($pm[1] == false ? $channel : $pm[1]);
												$this->pm($pm[0],$to);
											}
											else
												$this->pm($pm,$got['channel']);
										}
									}
									else
									{
										$pipeOut = array(); // this what I want to do here??
										foreach($got['pm'] as $pm)
										{
											if(is_array($pm))
												array_push($pipeOut,current($pm));
											else
												array_push($pipeOut,$pm);
										}
									}
								}
								
							}// has return
								
								
						}// each ziggi
					}// each line of out
				}// piped parts
			}// are ziggis
		}
	}


	
	/**
	Splits the raw buffer into usuable pieces
	Borrowed from another bot ( molobot? )
	@return void
	*/
	function parseBuffer()
	{
		$buffer = explode(" ", $this->buffer['raw'] , 4);
		$buffer['username'] = substr($buffer[0], 1, strpos($buffer[0], "!")-1);
		$a = strpos($buffer[0], "!");
		$b = strpos($buffer[0], "@");
		$buffer['ident'] = substr($buffer[0], $a+1, $b-$a-1); 
		$buffer['hostname'] = substr($buffer[0], strpos($buffer[0], "@")+1);
		$buffer['user_host'] = substr($buffer[0],1);
		
		switch (strtoupper($buffer[1]))
		{
			case "JOIN":
			   	$buffer['text'] = "*JOINS: ". $buffer['username']." ( ".$buffer['user_host']." )";
				$buffer['command'] = "JOIN";
				$buffer['channel'] = $buffer[2];
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
				if($this->cfg['diss'])
				{
					$diss=array('cya','ouch','booyaka','boom','burn','sucker');
					$this->pm($diss[array_rand($diss)]);
				}
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
				if(strpos($buffer['channel'], "#") === false)
				{
					// Looks like it was from another person.
					$buffer['channel'] = $buffer['username'];
				}
				$buffer['text'] = substr($buffer[3], 1);	
			break;	
		}
		
		if(substr($buffer['channel'],0,1)==':')
			$buffer['channel']=substr($buffer['channel'],1);
			
		$this->buffer = $buffer;
	}
	
	
	/**
	private message
	@param string What to say
	@param string To channel/nick
	*/
	function pm($message, $channel = "")
	{
		// If a channel was defined, use it, else use the channel the command came from.
		$channel = ($channel == "") ? $this->buffer['channel'] : $channel;
		
		if(!$this->webdebug)
		{
			if(is_array($message))
			{
				foreach($message as $msg)
				{
					$pm = 'PRIVMSG '. $channel .' :'.$msg;
					$this->send($pm);
					usleep(400 * 1000);
				}
			}
			else
			{
				$pm = 'PRIVMSG '. $channel .' :'.$message;
				$this->send($pm);
				usleep(400 * 1000);
			}
		}
		else
		{
			if(is_array($message))
			{
				foreach($message as $msg)
				{
					$pm = 'PRIVMSG '. $channel .' :'.$msg;
					echo $pm ."<br />";
				}
			}
			else
			{
				$pm = 'PRIVMSG '. $channel .' :'.$message;
				echo $pm ."<br />";
			}
		}
		
	}
	
	/**
	Print output to the terminal and add it to the log file
	(start.sh sends output to /dev/null)
	@param string buffer line
	@return void
	*/
	function output($line)
	{
		echo $line."\n";
		
		//dont log PONGs
		if(strpos($line,'PONG')===FALSE)
			$this->log($line, LOG_FILE);
	}
	
	/**
	Log a line
	@access private
	@param string line to log
	@param string /path/to/logfile
	@return void
	*/
	function log($line, $file)
	{
		if(!$this->logging)
			return;
			
		$fs = new FileStorage($file,FS_APPEND);
		$fs->write($line);
	}
	
	/**
	@access public
	@param boolean logging on or off
	@return void
	*/
	function logging($bool=true)
	{
		if(gettype($bool)!='boolean')
			$bool = !!$bool; // toBool
		$this->logging = $bool;
	}
	
	
	/**
	Send text to the IRC Server.
	Helps to be RFC 1450 Compliant ;) [ http://www.faqs.org/rfcs/rfc1459.html ]
	@access private
	@param string
	@return void
	*/
	function send($command)
	{
		fputs($this->connection, $command."\n\r");
		$this->output (date("[d/m @ H:i]") ."-> ". $command. "\n\r");
	}
	
	/**
	@access private
	@param string nick
	*/
	function registered($u)
	{
		$fs = new FileStorage(REG_USERS_FILE);
		$fs->read();
		return in_array($u,$fs->contents);
	}
	
	/**
	@access private
	@param void
	@return string nick or bot's name if false (used to avoid certain situations)
	*/
	function username()
	{
		return $this->buffer['username'];
	}
	
	/***
	load several plugin class files
	@access public
	@param array of miexed vars , ziggi filename or array(filename,params)
	@return void
	//*/
	function addZiggis($A)
	{
		if(!is_array($A))
			return;
		foreach($A as $ziggi)
		{
			if(is_scalar($ziggi))
				$this->addZiggi($ziggi);
			elseif(is_array($ziggi))
				$this->addZiggi($ziggi[0],$ziggi[1]);
		}
	}
	
	/**
	load a single plugin class file
	@access public
	@param string file name
	@param array additional parameters
	@return boolean load success
	*/
	function addZiggi($z,$params = false)
	{
		if(!is_array($this->ziggis))
			$this->ziggis = array();
		
		$f = ZIGGI_SRC_PATH.$z;
		if($cls = $this->getZiggiClassName($f))
		{
			require_once($f);
			eval(" \$a = new $cls();");
			$this->ziggis[$cls]=$a;
			
			// something not in the distributed version
			if($params['key'] && method_exists($this->ziggis[$cls],'setKey'))
				$this->ziggis[$cls]->setKey( $this->getKey($params['key']) );
			
			$this->ziggis[$cls]->setAccessLevel($params['access']);
			$this->ziggis[$cls]->setBotName($this->cfg['nick']);
			
			if($params['cron'])
				$this->ziggis[$cls]->addCron($params['cron']);
			
			return true;
		}
		else
		{
			echo "$cls $f not a class \n<br>\n";
			return false;
		}
	}
	
	/**
	finds the class name from file
	@access private
	@param string filename
	@return string classname
	*/
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
				return $x[1];
		}
		
		return false;
	}
	
	/**
	gets the user's "known" status
	@access public
	@param string nick
	@return int (from constant) user level
	*/
	function getUserAccessLevel($nick)
	{
		if($nick == $this->cfg['nick'])
			return BZUSER_OWNER;
			
		$reg = $this->registered( $nick );
		if(!$reg)
			return BZUSER_ANYONE;
		else
			return ($this->isOwner()? BZUSER_OWNER : BZUSER_REGISTERED);
	}
}
?>
