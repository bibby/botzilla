<?php
/**
* Class to manage an instance of a user answering to a question set.
Responses are kept locally here until Dialog call conclude
The Question pointer, and prev(), next() are also here.
*@author bibby
*$Id: DialogForm.class.php,v 1.3 2008/12/30 16:46:08 abibby Exp $
** //*/

class DialogForm
{
	/*** DialogForm ***
	constructor
	@param string nick
	@param object DialogQuestionSet
	@param array Dialog's command set
	*/
	function DialogForm($user,$qset,$cmds)
	{
		$this->user = $user;
		$this->qset = $qset;
		$this->responses=array();
		$this->satisfied=array();
		$this->curr = false;
		$this->commands=$cmds;
		$this->dataFile=null;
		foreach(array_keys( $qset->questions ) as $k)
			$this->satisfied[$k]=false;
	}
	
	
	/*** setDataFile ***
	This is the filename that we predetermined would be that ultimate storage path for this nick's data.
	If a file exists already, we'll prepopulate this object's response array with those responses.
	@access public
	@param string file path
	@return void
	*/
	function setDataFile($to)
	{
		$this->dataFile=$to;
		if(file_exists($to))
		{
			$fs = new FileStorage($to,FS_READ);
			$fs->read();
			foreach($fs->contents as $line)
			{
				$data = explode('::',$line,2);
				if($data[1])
				{
					foreach(array_keys($this->qset->questions) as $k)
					{
						$field=$this->qset->getQuestionAttribute($k,'FIELD');
						if($data[0]==$field)
						{
							if(!is_array($this->responses[$k]))
								$this->responses[$k]=array();
								
							array_push($this->responses[$k] , $data[1]);
							$this->satisfied[$k]=true;
						}
					}
				}
			}
		}
	}
	
	/*** nextQuestion ***
	Advance to the next question.
	@access private
	@return string question text
	*/
	function next()
	{
		if($this->satisfied[$this->curr]!==true)
		{
			$this->satisfied[$this->curr] = null;
			$this->responses[$this->curr] = array();
		}
		
		$this->curr++;
		$q = $this->qset->getQuestion($this->curr);
		if(is_a($q,'DialogQuestion'))
		{
			$re = $this->getResponse();
			if($re!==false)
				$re = " (You answered '".$re."'. Either change or skip with ".DIALOG_CHAR.$this->commands['next']." )";
			return $this->poseQuestion((int)$this->curr,$re);
		}
		else
			return $this; // end of form
	}
	
	
	/*** prev ***
	Regress to the last question
	@access private
	@return string question text
	*/
	function prev()
	{
		$this->curr--;
		$q = $this->qset->getQuestion($this->curr);
		if(is_a($q,'DialogQuestion'))
		{
			$re = $this->getResponse();
			if($re!==false)
				$re = " (You answered '".$re."'. Either change or skip with ".DIALOG_CHAR.$this->commands['next']." )";
			
			return $this->poseQuestion((int)$this->curr,$re);
		}
		else
		{
			$this->curr=0;
			return "There were no questions before this one.";
		}
	}
	
	
	/*** bail ***
	Quit this dialog without saving
	@access private
	@return int const abort!
	*/
	function bail()
	{
		return DIALOG_BAIL;
	}
	
	
	/*** poseQuestion ***
	Ask the "current" question. If a previous response exists, be it from using prev() or restarting the same dialog, we'll let the user know
	what their previous response was (and let them keep it if they skip)
	@access private
	@param int question index
	@param string (optional) text to append (like "Your previous response was ...")
	@return string question text
	*/
	function poseQuestion($k=FALSE, $append=FALSE)
	{
		if(!$k) $k = min(array_keys($this->qset->questions));
		$this->curr = $k;
		$qtxt = $this->qset->getQuestionAttribute($k,'QTEXT');
		
		if(is_string($qtxt))
		{
			return $qtxt.$append;
		}
	}
	
	
	/*** response ***
	Handle a user's input as a response to the current question. Applies the optional ACCEPT callback and bails if that is false.
	@access private
	@param string user input
	@return mixed (string - next question , object - completed form)
	*/
	function response($txt)
	{
		$q=$this->qset->getQuestion($this->curr);
		if(!is_a($q,'DialogQuestion'))
			return false;
			
		$callbacks = $q->attr('ACCEPT');
		if(is_string($callbacks))
			$callbacks = array($callbacks);
		
		foreach($callbacks as $callback)
		{
			if(method_exists($this->qset,$callback))
			{
				$try= $this->qset->{$callback}($txt,$this->user);
				if($try === false)
					return $q->attr('UNACCEPTED');
				
				if(gettype($try)!='boolean')
					$txt = $try; // response may have mutated to an array
			}
		}
		
		if(!is_array($txt))
			$txt = array($txt);
			
		$this->responses[$this->curr] = $txt;
		$this->satisfied[$this->curr] = true;
		
		return $this->next();
	}
	
	
	/*** getResponses ***
	Finalize the user's responses into an array worth storing.
	Multiple answers to the same key are duplicated, like having several IRC nicknames may produce:
		IRC: bbby
		IRC: bibuntu
	
	@access public
	@return array all user responses
	*/
	function getResponses()
	{
		$r=array();
		foreach($this->responses as $k=>$v)
		{
			$f =& $this->qset->getQuestionAttribute($k,'FIELD');
			if(is_string($v)) // by now, all responses should be arrays..
				$r[]="$f::$v";
			elseif(is_array($v))
				foreach($v as $vre)
					$r[]=join('',array($f,RESPONSE_DELIMITER,$vre));
		}
		return $r;
	}
	
	/*** getResponse ***
	Fetches a response to an earlier question, joining with commas as needed.
	@access public
	@param int question index ( current if false)
	@return string response
	*/
	function getResponse($k=false)
	{
		if(!$k) $k=$this->curr;
		if($this->responses[$k])
			return (empty($this->responses[$k]) ? 'false' :join(', ',$this->responses[$k]) );
		else
			return false;
	}
}
?>
