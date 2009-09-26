<?php
/**
* Most ziggis require that the user is known,
* .. except for this one, that allows one to register.
*@author bibby
*$Id: reg.class.php,v 1.2 2008/05/25 16:57:30 abibby Exp $
** //*/


class reg extends ziggi
{
	function reg()
	{
	}
	
	function parseBuffer()
	{
		$e = $this->getArg(0);
		if(substr($e,0,1)==CMD_CHAR)
			$e=substr($e,1);
		else
			return false;
		
		if($e!=false && method_exists($this,$e))
			$this->$e();
	}
	
	
	/***
	add a username to the recognized user's list
	*/
	function register()
	{
		$users = $this->getArgs();
		if(count($users) == 1)
			$users = array($this->getUser());
		else
			array_shift($users);
		
		foreach($users as $u)
		{
			if(!$this->registered($u))
			{
				$fs = new FileStorage(REG_USERS_FILE,FS_APPEND);
				$fs->write($u);
				$this->pm("I'll start to remember you now, $u.");
			}
			else
				$this->pm("I know you already, $u.");
		}
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
	
	/***
	With some clients, it's not obvious what your nick is (you might see a local alias).
	Returns nick
	@access public
	@return string user's nick 
	*/
	function whoami()
	{
		$this->pm($this->getUser());
	}
}
?>
