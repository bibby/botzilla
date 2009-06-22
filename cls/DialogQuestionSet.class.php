<?php
/**
* A set of Questions that make up a dialog, by adding DialogQuestions to it.
* This class also holds any validation callbacks that might be desired to check the user's reponse.
* If you want additional validators/cleaners/processors , feel free to extend this class with those methods.
* See the DialogQuestion class for info on declaring a callback.
*@author bibby
*$Id: DialogQuestionSet.class.php,v 1.3 2008/12/30 16:46:08 abibby Exp $
** //*/

define('DIALOG_AFFIRMATIVE','TRUE');
define('DIALOG_NEGATIVE','FALSE');

class DialogQuestionSet
{
	/*** DialogQuestionSet ***
	constructor
	*/
	function DialogQuestionSet()
	{
		$this->questions = array();
	}
	
	/*** addQuestion ***
	Adds a question to the quesiton set.
	@access public
	@param object DialogQuestion
	@return void
	*/
	function addQuestion($q)
	{
		if(is_array($q))
		{
			foreach(array_keys($q) as $qid)
				$this->addQuestion($q[$qid]);
			return;
		}
		
		if(is_a($q,'DialogQuestion'))
		{
			array_push($this->questions,$q);
		}
	}
	
	
	/*** getQuestion ***
	Get a question by index. (Index is determined by the order they are added).
	Many times, this is for internal use
	@access public
	@param int
	@return object DialogQuestion
	*/
	function getQuestion($qindex)
	{
		return $this->questions[$qindex];
	}
	
	/*** getQuestionAttribute ***
	Get an attribute of a question.
	@access public
	@param int question's index
	@param string attribute name
	@return string attribute value
	*/
	function getQuestionAttribute($qindex,$attrib)
	{
		return $this->questions[$qindex]->attribs[$attrib];
	}
	
	// ACCEPT callbacks
	
	//validators
	/*** number ***
	@access public
	@param string response
	@return bool passes test
	*/
	function number($response)
	{
		return !ereg('[^0-9\.]',trim($response));
	}
	
	/*** yesno ***
	decide an answer to a "yes or no" question. Basically anything starting with a 'Y' is yes, and likewise for no,
	'y','Yup','you got it','yeah' : 'n','nope', etc
	@access public
	@param string response
	@return string
	*/
	function yesno($response)
	{
		if(eregi('^y',trim($response)))
			return DIALOG_AFFIRMATIVE;
		if(eregi('^n',trim($response)))
			return DIALOG_NEGATIVE;
		return false;
	}
	
	
	/*** separate ***
	Split responses on spaces and commas, cleaning up parts
	@access public
	@param string response
	@return array
	*/
	function separate($txt)
	{
		return $this->split('/[\s,]/',$txt);
	}
	
	
	// utils
	/*** split ***
	splits strings into arrays by delimiter, cleaning empty values and trimming truthy ones
	@access public
	@param string delimiter pattern -> '//'
	@param string response
	@return array
	*/
	function split($delim,$resp)
	{
		$x = array_unique(array_filter(preg_split($delim,$resp)));
		$x=array_map('trim',$x);
		return $x;
	}
	
	
	
	/*** trim ***
	@access
	@param
	@return
	*/
	function trim($response)
	{
		if(is_string($response))
			return trim($response);
		if(is_array($response))
			return array_map('trim',$response);
	}
	
	
	/*** rev ***
	@access
	@param
	@return
	*/
	function rev($response)
	{
		if(is_string($response))
			return strrev($response);
		if(is_array($response))
			return array_map('strrev',$response);
	}
}

?>
