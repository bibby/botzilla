<?php
/**
* Dialog is a managing class for user interactions with an instance of a set of questions that will ultimately be stored locally (or dealt with as you modify it to do).
* A ziggi class would want to instatiate for itself a new Dialog and add Questions to the question set.
* In your ziggi's parseBuffer(), let Dialog listen to the same buffer that it is, and pass along anything that it might have to say.

Your ziggi's constructor would want something like:

	function MyClass()
	{
		$this->dialog = new Dialog();
		$this->dialog->setCommand('begin','start');
		$this->dialog->setStoragePath(PERM_DATA.'placetostore/');
	}

Commands can be unique per Dialog instance (ie - not in this class, not with this question set), but
you're going to have to override the keyword to 'begin' the dialog.
setCommand() takes either a (key, value) or (array_of_key_values) and overrides the defaults.

DIALOG_CHAR is definition of a command character that can be what you decide. Default is ':'

setStoragePath() tells DialogForms where to save their data. It's ok if the directory doesn't exist, 
so long as you have permission to create it.

setFilePrefix() sets the first half of the resulting data file name, should that be handy.
The tail end of the filename will always be the user's nick that had the dialog.

A typical ziggi's parseBuffer() that includes Dialog listening:

	function parseBuffer()
	{
		if(substr($this->getArg(0)0,1) == CMD_CHAR)
		{
			// do anything I might have done with a command
		}
		
		if(substr($this->getArg(0)0,1) == DIALOG_CHAR)
		{
			// pass the buffer as-is to Dialog, and catch a return
			$r = $this->dialog->parseBuffer($this->bz_buffer);
			
			// strings and arrays should be printed to channel
			if(is_array($r) || is_string($r))
				$this->pm($r);
				
			// a completed form returns the DialogForm itself
			if(is_a($r,'DialogForm'))
			{
				// just tell Dialog to wrap it up
				$this->pm( $this->d->conclude($r->user) );
			}
		}
	}


* Multistep running dialog with botzilla.
*@author bibby <bibby@surfmerchants.com>
*$Id: Dialog.class.php,v 1.5 2009/06/18 00:08:32 abibby Exp $
*/

require('DialogForm.class.php');
require('DialogQuestionSet.class.php');
require('DialogQuestion.class.php');

define('DIALOG_CHAR',':');
define('RESPONSE_DELIMITER','::');
define('DIALOG_BAIL',__LINE__);
define('ERR_INPROGRESS',__LINE__);
define('ERR_NO_QUESTIONS',__LINE__);
define('ERR_NOT_QUESTIONSET',__LINE__);

class Dialog
{
	/*** Dialog ***
	constructor
	*/
	function Dialog()
	{
		$this->dialogs=array(); // currently running dialogs, instances of DialogForm
		$this->qset = null;   // instance of DialogQuestionSet
		$this->storagePath = PERM_DATA;
		$this->filePrefix = 'dialog_';
		$this->commands = array
		(
			'begin'=>null,
			'bail'=>'x',
			'conclude'=>null,
			'help'=>'?',
			'prev'=>'<',
			'next'=>'>'
		); // buzzwords to listen for to control a dialog
		$this->ziggi = new ziggi();  // ziggeh
		$this->concludeMsg = "Thanks, this concludes our dialog.";
	}
	
	/*** setStoragePath ***
	@access public
	@param string file path
	@return bool (true is good)
	*/
	function setStoragePath($to)
	{
		$this->storagePath = $to;
		if(!is_dir($to))
			return mkdir($to);
		else
			return is_writeable($to);
	}
	
	/*** setFilePrefix ***
	@access public
	@param string first part of a two part file name (second half is the user's nick)
	@return void
	*/
	function setFilePrefix($to)
	{
		$this->filePrefix = $to.'_';
	}
	
	/*** begin ***
	@access private
	@param string nick
	@param int
	@return array intro and question one
	*/
	function begin($user)
	{
		if(!is_a($this->qset, 'DialogQuestionSet'))
			return new DialogError(ERR_NO_QUESTIONS);
			
		if($this->hasDialog($user))
			return new DialogError(ERR_INPROGRESS); // nick already has a dialog
			
		$this->dialogs[$user] = new DialogForm($user,$this->qset,$this->commands);
		$this->dialogs[$user]->setDataFile($this->storagePath.$this->filePrefix.$user);
		
		$intro="starting dialog..";
		//var_dump($user);
		return array($intro , $this->dialogs[$user]->next() );
	}
	
	
	/*** setCommand ***
	Override the Dialog's original command characters
	@access public
	@param mixed command array key or array of key=>values
	@param string value to apply to ^key
	@return void
	*/
	function setCommand($cmd, $val)
	{
		if(is_array($cmd))
		{
			foreach($cmd as $c=>$v)
				$this->setCommand($c,$v);
			return;
		}
		
		$cmd = strtolower($cmd);
		if(array_key_exists($cmd,$this->commands))
			$this->commands[$cmd]=$val;
	}
	
	/*** hasDialog ***
	Tell is a user already has an active instance of this dialog
	@access public
	@param string user's nick
	@return bool has a instance
	*/
	function hasDialog($user)
	{
		return is_object($this->dialogs[$user]);
	}
	
	
	/*** conclude ***
	Finishes a Dialog, destroying its DialogForm instance, and saving to file as requested
	@access public
	@param string user's nick
	@param bool Write to file? or just ditch
	@return string "buh-bye"
	*/
	function conclude($user, $store=false)
	{
		if($this->dialogs[$user])
		{
			if($store)
			{
				$fs = new FileStorage($this->dialogs[$user]->dataFile,FS_WRITE);
				$fs->write($this->dialogs[$user]->getResponses());
			}
			
			unset($this->dialogs[$user]);
			return $this->concludeMsg;
		}
		else
			return false;
	}
	
	
	/*** setQuestions ***
	set the question set that this Dialog should be using
	@access public
	@param object DialogQuestionSet
	@return bool
	*/
	function setQuestions($qset)
	{
		
		if(!is_a($qset, 'DialogQuestionSet'))
			return new DialogError(ERR_NOT_QUESTIONSET);
		$this->qset = $qset;
		return true;
	}
	
	
	
	/*** parseBuffer ***
	A ziggi-ism. Dialog made itself a ziggi to exploit getters with.
	This gets us our user nick and listens for key words like a ziggi would.
	If interesting, it passes the buffer on to handle()
	@access private
	@param array ziggi buffer
	@return mixed private messages
	*/
	function parseBuffer($buffer)
	{
		$ret=false;
		$this->ziggi->listen($buffer);
		$txt = trim($this->ziggi->getInput());
		if(substr($txt,0,1) == DIALOG_CHAR)
			$ret = $this->handleInput($this->ziggi->getUser(), substr($txt,1));
			
		if($ret == DIALOG_BAIL)
			$ret = $this->conclude($this->ziggi->getUser());
		
		return $ret; // give any response back to ziggi
	}
	
	/*** handle ***
	This gets called if parseBuffer thought handle wanted to hear something (used the command char).
	Here, we translate the user's input into either a command or a question response.
	@access private
	@param string user's nick
	@param string user's input
	@return string response
	*/
	function handleInput($user,$txt)
	{
		$words = explode(' ',$txt);
		array_walk($words,'trim');
		if(!is_null($this->commands['begin']) && !$this->hasDialog($user) && $words[0] == $this->commands['begin'] )
			return $this->begin($user);
		
		if(in_array($words[0], $this->commands))
		{
			$cmd = array_search($words[0], $this->commands);
			switch($cmd)
			{
				case 'help':
					return 
					array (
						"To communicate to me, with regards to this dialog, prefix your input with a ".DIALOG_CHAR." character.",
						DIALOG_CHAR.$this->commands['next']." will skip a question (maybe), and ".DIALOG_CHAR.$this->commands['prev']." goes back.",
						"example: <<\"How old are you?\"   >>\":29\"",
						"This dialog is started with '".DIALOG_CHAR.$this->commands['begin']."'"
					);
					break;
				case 'prev':
				case 'next':
				case 'bail':
					if($this->hasDialog($user))
						return $this->dialogs[$user]->{$cmd}();
			}
		}
		
		if($this->hasDialog($user))
			return $this->dialogs[$user]->response($txt);
	}
}


/**
This class is something of a sham. I had an idea for a neat Error class, but I got sidetracked while building the rest of this.
Strings and Arrays would have produced output, so being able to catch is_a($r,'DialogError') still comes in handy.
.. I'm not breaking it into it's own file though.
*/
class DialogError
{
	function DialogError($code)
	{
		$this->code = $code;
	}
}
?>
