<?php
/**
@author bibby <bibby@surfmerchants.com>
ziggi : botzilla generic interface
$Id: ziggi.class.php,v 1.4 2008/06/13 16:09:18 abibby Exp $

Write a replacement for parseBuffer() to do stuff.
Use pm(), addTimedEvent(), clearTimedEvent() to make botzilla do that stuff.
Various "get" functions should take care of all your getting needs

This software is provided 'as-is', without any express or implied warranty.
In no event will the authors be held liable for any damages arising from the
use of this software. It may be freely modified and redistributed, even in commercial applications.
*/

class ziggi
{
	function ziggi()
	{
	}
	
	/**
	Use this method to do your stuff.
	@param void (but use the get functions, as this function will run when the bot hears something.
	*/
	function parseBuffer()
	{
		return true;
	}
	
	/**
	botzilla calls this each incoming line
	you should probably leave it alone,
	@access private
	@return array of good stuff botzilla likes
	*/
	function listen($to_buffer = false,$access=true)
	{
		$this->bz_buffer = $to_buffer;
		$this->bz_ret = false;
		$e = $this->checkTimedEvents();
		if($access || $e)
			$this->parseBuffer();
		
		return $this->z_finish();
	}
	
	/**
	sets what kind of user may call this plugin.
	BZUSER_ANYONE
	BZUSER_REGISTERED
	BZUSER_OWNER
	@param int constantminimum access level
	@return void
	*/
	function setAccessLevel($to=false)
	{
		$this->user_access = ($to ? $to : BZUSER_DEFAULT);
	}
	
	/**
	let's the plugin now the bot's name
	@param string bot name
	@return void
	*/
	function setBotName($to)
	{
		$this->botName = $to;
	}
	
	function isEmpty()
	{
		return (trim($this->getText())=='' && $this->getEvent()==false);
	}

//###  USE THESE to return to botzilla
	
	/** use me to send msgs
	@param string,array what to say
	@param string a nick or channel. When false, responds to where to request came from
	//*/
	function pm($what,$to = false)
	{
		if(!$to)
			$to = $this->getOrigin();
		
		if(is_array($what))
		{
			foreach($what as $msg)
			{
				if(is_array($msg))
					$this->pm($msg[0],$msg[1]);
				else
					$this->addReturn('pm',$msg);
			}
		}
		elseif($what!=false && gettype($what)!='object')
		{
			$this->addReturn('pm',array($what,$to));
		}
	}
	
	/**
	send something to the server rather than the channel
	@param string Something to send
	*/
	function send($asd)
	{
		$this->addReturn('bz_send',$asd);
	}
	
//###   USE THESE in your methods to get stuff from the buffer
	
	/**
	@return string Nick of the user that said something
	*/
	function getUser()
	{
		return $this->bz_buffer['username'];
	}
	
	/**
	@return string User&Hostname of the user
	*/
	function getUserHost()
	{
		return $this->bz_buffer['user_host'];
	}
	
	/**
	@return string Hostname of the user 
	*/
	function getHostName()
	{
		return $this->bz_buffer['hostname'];
	}
	
	/**
	@return string User Indent
	*/
	function getIdent()
	{
		return $this->bz_buffer['ident'];
	}
	
	/**
	@return string Channel name (or nick if a private msg)
	*/
	function getOrigin()
	{
		return $this->bz_buffer['channel'];
	}
	
	/**
	@return string The $i-nth argument, separated by spaces
	*/
	function getArg($i)
	{
		$a = ( $this->bz_buffer['text'] ? $this->bz_buffer['text'] : '' );
		$a = ( $this->bz_buffer['event'] ? $this->bz_buffer['event'] : $a );
		
		$a = explode(" ",$a);
		if(is_array($a))
		{
			return $a[$i];
		}
		return $this->bz_buffer[$i];
	}
	
	/**
	@return array All of the user's input split on spaces
	*/
	function getArgs()
	{
		$t = $this->getInput();
		return explode(" ",trim($t));
	}
	
	
	/**
	@return string IRC Command , PRIVMSG, MODE, JOIN, et al.
	*/
	function getCommand()
	{
		return $this->bz_buffer['command'];
	}
	
	/**
	not to be confused with getCommand, this is a convenience method
	that almost all ziggis do
	@return string
	*/
	function getCmd()
	{
		$cmd = $this->getArg(0);
		if( substr($cmd,0,1) == CMD_CHAR )
			return substr($cmd,1);
	}
	
	/**
	@return string Sent by reoccuring events. Pretty arbitrary
	*/
	function getEvent()
	{
		return $this->bz_buffer['event'];
	}
	
	/**
	@return string The user's whole input , different than event text
	*/
	function getInput()
	{
		return $this->bz_buffer['text'];
	}
	
	/**
	@return string Use instead of getText() if you want your plugin to handle a piped in,
	It's like "get line i care about", even if it's not the user's direct input (though would be if not piped in) 
	*/
	function getText()
	{
		return ($this->bz_buffer['pipe']?$this->bz_buffer['pipe']:$this->bz_buffer['text']);
	}
	
	/**
	@return string the user's input minus the first argument (without the .command)
	*/
	function getArgText()
	{
		$t = $this->getInput();
		$x = explode(" ",trim($t),2);
		return $x[1];
	}
//###


//### Timed/Reoccuring events
	
	function hasTimedEvents()
	{
		return (is_array($this->timedEvents) && count($this->timedEvents)>0);
	}
	
	
	/**
	@param string a function name or string. something to listen back for
	@param int number of seconds from now this event runs. (ie, 60)
	//*/
	function addTimedEvent($what,$timeTilNext,$channel=false)
	{
		if(!$channel)
			$channel = $this->getOrigin();
		
		if(!is_array($this->timedEvents))
			$this->timedEvents=array();
			
		$this->timedEvents[]=array('cmd'=>$what,'time'=>$timeTilNext+time(),'channel'=>$channel);
		
		return max(array_keys($this->timedEvents));
	}
	
	/**
	cancel an unfired on reoccuring event event
	@param index of event
	//*/
	function clearTimedEvent($i)
	{
		unset($this->timedEvents[$i]);
		return true;
	}
	
	function checkTimedEvents()
	{
		if($this->hasTimedEvents())
		{
			$now=time();
			foreach($this->timedEvents as $i=>$event)
			{
				if($event['time'] <= $now)
				{
					$this->bz_buffer['event']=$event['cmd'];
					$this->bz_buffer['channel'] = $event['channel'];
					$this->bz_buffer['username'] = false;
					
					$this->clearTimedEvent($i);
					
					$cron=$this->getCron($event['cmd']);
					if(is_array($cron))
						$this->addTimedEvent($cron['cmd'],$cron['interval'],$cron['channel']);
					
					// allow other events to fire this very second to go on the next.
					return true;
				}
			}
		}
	}
	
	function addCron($cron)
	{
		if(!is_array($this->crons))
			$this->crons=array($cron);
		else
			array_push($this->crons,$cron);
			
		$this->addTimedEvent($cron['cmd'],$cron['interval'],$cron['channel']);
	}
	
	function getCron($what)
	{
		if(!is_array($this->crons))
			return false;
		
		foreach($this->crons as $c)
			if($c['cmd']==$what)
				return $c;
		
		return false;
	}
	
	/**
	return boolean :  has piped text?
	**/
	function piped()
	{
		return ($this->getText() != $this->getInput());
	}

//### Finishers
	/**
	@access private
	@param string A handle botzilla knows
	@param mixed data
	*/
	function addReturn($type,$data)
	{
		if(!is_array($this->bz_ret))
			$this->bz_ret = array();
		if(!is_array($this->bz_ret[$type]))
			$this->bz_ret[$type]=array();
		array_push($this->bz_ret[$type],$data);
	}
	
	/**
	@return array Stuff for botzilla to love on
	*/
	function z_finish()
	{
		return $this->bz_ret;
	}
}
?>
