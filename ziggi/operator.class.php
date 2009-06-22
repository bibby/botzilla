<?php
/**
IRC Operator functions for botzilla
$Id: operator.class.php,v 1.5 2008/06/10 04:05:48 abibby Exp $
*/

class operator extends ziggi
{
	function operator()
	{
		$this->c=false;	//channel
		$this->u=false; //username
		$this->quit=false;
	}
	
	
	function parseBuffer()
	{
		$this->c=false;	//channel
		$this->u=false; //username
		
		// op owners on join (as only owners can access this class)
		if($this->getCommand()=='JOIN' && $this->getUser()!=$this->botName)
		{
			$this->c = $this->getOrigin();
			$this->u = $this->getUser();
			$this->op();
			return;
		}
		
		$e = $this->getArg(0);
		if(substr($e,0,1)==CMD_CHAR)
			$e=substr($e,1);
		else 
			return false;
		
		$this->c=$this->getArg(1);
		$this->u=$this->getArg(2);
		
		//  "kick user" (from "this" channel)
		if(!$this->u && !in_array(strtolower($e),array('join','part')))
		{
			$this->u=$this->c;
			$this->c=$this->getOrigin();
		}
		
		if($e!=false && method_exists($this,$e))
			$this->$e();
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
	remove a name from the known users list.
	@access public
	**/
	function unreg()
	{
		$user = $this->getArg(1);
		if($user)
		{
			$fs = new FileStorage(REG_USERS_FILE);
			$fs->read();
			$new=array();
			foreach($fs->contents as $nick)
			{
				if($nick!=$user)
					$new[]=$nick;
				else
				{
					$found=true;
					$this->pm($nick." removed.");
				}
			}
			
			if(!$found)
				$this->pm($nick." wasnt in the list.");
			else
			{
				$fs = new FileStorage(REG_USERS_FILE,FS_WRITE);
				$fs->write($new);
			}
		}
	}
	
	function pid()
	{
		$this->pm(getmypid());
	}
	
	
	function kick()
	{
		$this->send('KICK ' . $this->c . " " . $this->u  );
	}
	
	function voice()
	{
		$this->send('MODE '.$this->c.' +v '.$this->u );
	}
	
	function devoice()
	{
		$this->send('MODE '.$this->c.' -v '.$this->u );
	}
	
	function op()
	{
		$this->send('MODE '.$this->c.' +o '.$this->u );
	}
	
	function deop()
	{
		$this->send('MODE '.$this->c.' -o '.$this->u );
	}
	
	function moderated()
	{
		$this->send('MODE '.$this->c.' +m');
	}
	
	function unmoderated()
	{
		$this->send('MODE '.$this->c.' -m');
	}
	
	function bz_identify()
	{
		$passwd = $this->c;
		$this->pm("identify $passwd",'nickserv');
	}
	
	function join()
	{
		if($this->u)
			$k = " ".$this->u;
		$this->send('JOIN '.$this->c.$k);
	}
	
	function part()
	{
		$this->send('PART '.$this->c);
	}
	
	function quit()
	{
		global $botzilla;
		$botzilla->reconnect=false;
		$this->send("QUIT ".$this->getArgText());
	}
	
	/**
	starts a new instance of itself and exits the script
	@access public
	@return void
	*/
	function restart()
	{
		global $botzilla;
		
		$this->reconnect = false;
		$this->send("QUIT Restarting.");
		
		// start up another bot
		exec('nohup php -q '.$_SERVER['SCRIPT_FILENAME'].' > /dev/null &');
		exit;
	}
	
	
	function nick()
	{
		$this->send('NICK '.$this->getArg(1));
	}
	
	
	function reload()
	{
		$cls = $this->getArg(1);
		
		$clsFile = ZIGGI_SRC_PATH."$cls.class.php";
		if(!file_exists($clsFile))
		{
			$this->pm("class file $cls.class.php not found.");
			return;
		}
		
		$code = file_get_contents($clsFile);
		
		do
		{
			$tmpName = "z".substr(uniqid(),-8);
		} while( class_exists($tmpName) );
		
		$pat = array
		(
			"/class\s+$cls\s?/",
			"/function\s+$cls\s?\(/"
		);
		
		$rep = array
		(
			"class $tmpName ",
			"function $tmpName("
		);
		
		$newCode = preg_replace($pat,$rep, $code);
		
		$tmp = "/tmp/bz_swap.php";
		$F=fopen($tmp,'w');
		fwrite( $F, $newCode);
		fclose($F);
		
		$check = `php -l $tmp`;
		$ok = 'No syntax errors detected';
		if(substr($check,0,strlen($ok)) != $ok)
		{
			$this->pm("new class file contains parse errors.");
			return;
		}
		
		include($tmp);
		$C = new $tmpName();
		unlink($tmp);
		
		global $botzilla;
		if(is_object( $botzilla->ziggis[$cls] ) )
			$C->setAccessLevel( $botzilla->ziggis[$cls]->user_access );
		
		$botzilla->ziggis[$cls] = $C;
		$this->pm("$cls reloaded");
	}
}
?>
