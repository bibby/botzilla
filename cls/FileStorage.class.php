<?php
/**
* Class for reading and writing shit to files
*@author bibby <bibby@surfmerchants.com>
*$Id: FileStorage.class.php,v 1.2 2008/05/30 13:12:25 abibby Exp $
This software is provided 'as-is', without any express or implied warranty.
In no event will the authors be held liable for any damages arising from the
use of this software. It may be freely modified and redistributed, even in commercial applications.
*/

// generates a unique filename in /tmp
define('FS_TEMP',-1);

//open modes
define('FS_READ','r');
define('FS_WRITE','w');
define('FS_APPEND','a');

//type checks
define('NOTHING',0);
define('REGULAR_FILE',1);
define('WRITEABLE_FILE',2);
define('CREATEABLE_FILE',3);
define('WORKABLE_FILE',4);

define('DIRECTORY',10);
define('WRITEABLE_DIRECTORY',11);

define('RESOURCE',20);

class FileStorage
{
	var $filename;
	var $mode;
	var $persist;
	var $handle;
	var $contents;
	
	
	function FileStorage($name=false,$mode=FS_READ)
	{
		if($name == FS_TEMP)
			$name ='/tmp/'.md5(uniqid(rand(), true));
		
		$mode=strtolower($mode);
		if(in_array($mode,array(FS_READ,FS_WRITE,FS_APPEND)))
			$this->mode=$mode;
		
		if(is_string($name))
		{
			$this->filename=$name;
			if(!$this->init($this->filename))
				echo "could not init ".$this->filename."<hr>";
		}
	}
	
	/**
	Initialize
	@access private
	@param string file
	@return bool success
	*/
	function init($file)
	{
		if(!$file)
			return false;
		
		$mode = ($this->mode == FS_READ ? REGULAR_FILE : WORKABLE_FILE);
		if($this->check($file,$mode))
		{
			if($this->isOpen())
				$this->reset();
				
			$this->filename=$file;
			return TRUE;
		}
		else
			return false;
	}
	
	/**
	Test if "this" is "something" ,  like is_a()
	@access private
	@param mixed (that's what we're checking)
	@param int constant for a type of thing
	@return bool
	*/
	function check($what,$is=REGULAR_FILE)
	{
		switch($is)
		{
			case NOTHING:
				return !file_exists($what); // works for dirs too.
			case REGULAR_FILE:
				return (is_file($what) && is_readable($what));
			case WRITEABLE_FILE:
				return ($this->check($what,REGULAR_FILE) && is_writeable($what));
			case CREATEABLE_FILE:
				return ($this->check($what,NOTHING) && $this->check(dirname($what),WRITEABLE_DIRECTORY));
			case WORKABLE_FILE:
				return ($this->check($what,WRITEABLE_FILE) || $this->check($what,CREATEABLE_FILE));
			case DIRECTORY:
				return is_dir($what);
			case WRITEABLE_DIRECTORY:
				return ($this->check($what,DIRECTORY) && is_writeable($what));
			case RESOURCE:
				return is_resource($what);
		}
	}
	
	/**
	Create an appropriate file handle (or not if it is approriate)
	@access private
	@param int constant read/write/append mode
	@return bool success
	*/
	function open($mode=false)
	{
		if($mode==false)
			$mode = $this->mode;
		else
			$this->mode = $mode;
			
		if(in_array($mode,array(FS_WRITE,FS_APPEND)))
		{
			$this->handle = fopen($this->filename,$this->mode);
			return true;
		}
		
		if($mode==FS_READ)
			return $this->check($this->filename);
			
		return false;
	}
	
	/**
	@access public
	@param void
	@return bool , valid handle is open
	*/
	function isOpen()
	{
		return $this->check($this->handle,RESOURCE);
	}
	
	
	/**
	closes the file handle
	@access private (class decides when to close)
	@param void
	@return bool success
	*/
	function close()
	{
		if($this->check($this->handle,RESOURCE))
		{
			fclose($this->handle);
			return true;
		}
		return false;
	}
	
	/**
	"persist close". Perhaps a bad name for it since if persist is on, it WONT CLOSE!
	@access private
	@param void
	@return bool success
	*/
	function pclose()
	{
		if($this->persist==false)
			$this->close();
	}
	
	/**
	destroys this object
	@access public
	@param void
	@return void
	*/
	function destroy()
	{
		$this->reset();
		unset($this);
	}
	
	/**
	clears all the members
	@access private
	@param void
	@return void
	*/
	function reset()
	{
		$this->close();
		$this->filename=
		$this->mode=
		$this->persist=
		$this->handle=
		$this->contents=
			FALSE;
	}
	
	/**
	not for resource based operations
	Stores contents in $this->contents for other operations
	Perhaps not so unfortunate that the user must call this themselves:
		$fs = newFileStorage('/path/to/file');
		$fs->read();
		// do other stuff.
	I didn't want to assume that just because we want to read a file, that you'd be doing so right away.
	@access public
	@param void
	@return bool success
	*/
	function read()
	{
		if($this->check($this->filename))
		{
			$this->contents = file($this->filename);
			$this->contents = array_map('rtrim',$this->contents);
			return true;
		}
		
		return false;
	}
	
	/**
	Writes the contents to the file
	Will recurse on arrays
	@access public
	@param mixed string, array
	@return bool success
	*/
	function write($what)
	{
		//bail if in read mode
		if($this->mode == FS_READ)
			return false;
		
		//bail if we cant get a handle
		if(!( $this->isOpen() || $this->open($this->mode)))
			return false;
		
		if(!$this->check($this->handle,RESOURCE))
			$this->open($this->mode);
		
		if($this->check($this->handle,RESOURCE))
		{
			if(is_array($what))
			{
				$this->persist(TRUE);
				foreach($what as $line)
					$this->write($line);
				$this->persist(FALSE);
				$this->close();
			}
			elseif(is_scalar($what))
				fwrite($this->handle,$what."\n");
			
			$this->pclose();
		}
		
		return false;
	}
	
	/**
	Used to keep the handle open for writing multiple lines
	@access private
	@param bool on/off
	@return bool success
	*/
	function persist($bool)
	{
		if(is_bool($bool))
		{
			$this->persist = $bool;
			return true;
		}
		
		return false;
	}
	
	
	/**
	would be nice to grep for contents..
	@access
	@param
	@return
	*/
	function find()
	{
		
	}
	
	/**
	remove a file (hopefully something you wont miss)
	@access public
	@param void
	@return bool success
	*/
	function delete()
	{
		if($this->check($this->filename,WRITEABLE_FILE))
			return unlink($this->filename);
		return false;
	}
	
	/**
	Used only is in read mode
	Get a line by number
	@access public
	@param int line number
	@return string line from file
	*/
	function line($int)
	{
		if(is_array($this->contents) || $this->read())
			return $this->contents[$int];
		return false;
	}
	
	/**
	Used only is in read mode
	Get a lines by number
	@access public
	@param int line number from
	@param int line number to
	@return
	*/
	function lines($from=0,$to=0)
	{
		if(is_array($this->contents) || $this->read())
			return array_slice($this->contents,$from,$to);
		return false;
	}
	
	/**
	get a member
	@access public
	@param string var name
	@return var
	*/
	function get($var)
	{
		$vars = get_object_vars($this);
		return $vars[$var];
	}
	
	/**
	Used only is in read mode
	@access public
	@param void
	@return string random line from contents
	*/
	function randline()
	{
		if(is_array($this->contents) || $this->read())
			return $this->contents[array_rand($this->contents)];
	}
}
?>
