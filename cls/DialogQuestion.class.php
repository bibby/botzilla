<?php
/**
* A Dialog Question, consisting of an attributes array pretty much only.
* To be using Questions minimally yet correctly, QTEXT and FIELD should be assigned to each question.

Available settings:
QTEXT - string - the question posed to the user
FIELD - string - name to associate with the response to give it meaning
 // these you don't necessarily need, but are nice
ACCEPT - string - name of a method in the DialogQuestionSet that can validate or process the response
UNACCEPTED - string - message to throw if the ACCEPT Method returns false
REQUIRED - bool - * not implemented * question can't be skipped

example question:
new Question(array
(
	'QTEXT'=>'Eggs in a dozen?',
	'FIELD'=>'EGGS',
	'ACCEPT'=>'dozen',
	'UNACCEPTED'=>'Sorry, that\'s wrong'
));

In this example, "dozen" is expected to be a method of the (extended) DialogQuestionSet. 
That method should return false is the input is not acceptable :

qset -> number == function($re)
{
	return (intval($re)==12 || strtolower(trim($re))=='twelve'); // that'll do it.
}

I don't think that rejecting a response would be as common as simply cleaning it up,
so if you return anything other than a bool, the response will be replaced by the return.
This gives us a great opportunity to process the response before storage, like splitting a
response by spaces or commas :

// in question attrbiutes
.. 'ACCEPT'=>'separateValues' ..

// in the questionSet
//     a neat clean return
function($re)
{
	$x = array_unique(array_filter(split('/[\s,]/',$re)));
	array_walk($x,'trim');
	return $x;
}

*@author bibby
*$Id: DialogQuestion.class.php,v 1.2 2008/12/30 16:46:08 abibby Exp $
** //*/

class DialogQuestion
{
	/*** DialogQuestion ***
	constructor
	@param array Attributes to override the regular defaults.
	*/
	function DialogQuestion($attribs)
	{
		$this->attribs = array
		(
			'QTEXT'=>'NO_QTEXT',  // string, question to ask user
			'FIELD'=>'NO_FIELD',  // string, field name to accompany the answer in the concluded output
			'ACCEPT'=>null,       // string,  Question set method name
			'UNACCEPTED'=>'Sorry, your response didn\'t seem to answer the question. Try again?',   // string, message to tell user that they answered wrongly
			'ACCEPTED'=>null,     // string, message to tell user that they answered rightly
			'REQUIRED'=>false	  // bool, the form can complete wihout a response
		);
		
		if(is_array($attribs))
			foreach($attribs as $k=>$v)
				$this->attribs[strtoupper($k)]=$v;
	}
	
	
	/*** attr ***
	A shortcut to $this->attributes .
	If one argument is given, it will return the value of the key supplied
	If two are given, it will set the value of arg2 to the key at arg1.
	Since this gets by reference, it *may* create keys that don't exist.
	@access public
	@param string attribute name
	@param string (option) replacement value for this key
	@return string value
	*/
	function attr($get,$set=false)
	{
		$a=&$this->attribs[$get];
		if($set)
			$a=$set;
		return $a;
	}
}
?>
