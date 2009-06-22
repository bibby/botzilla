<?php
/**
*
*@author bibby <bibby@surfmerchants.com>
*$Id$
*/

if(!defined('RESPONSE_DELIMITER'))
	define('RESPONSE_DELIMITER','::');

class ResponseSet
{
	function ResponseSet($file)
	{
		$this->data=array();
		$this->index=array();
		$this->buildFrom($file);
	}
	
	/*** buildFrom ***
	@access
	@param
	@return
	*/
	function buildFrom($file)
	{
		if(!file_exists($file))
			return;
		
		$data = file($file);
		foreach($data as $l)
		{
			list($k,$v) = explode( RESPONSE_DELIMITER , $l);
			if(!is_array(!$this->data[$k]))
				$this->data[$k] = array();
			$v = trim($v);
			array_push($this->data[$k], $v);
			array_push($this->index, $v);
		}
	}
	
	
	/*** get ***
	@access
	@param
	@return
	*/
	function get($prop,$all=false)
	{
		$d = $this->data[strtoupper($prop)];
		if(!is_array($d))
			return false;
		
		return $all 
			?	$d
			:	current($d);
	}
}
?>
