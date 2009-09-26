<?php
/**
* Class to calc ints into chunks, usually time
* Any plugin class can free call this statically
*@author bibby <bibby@surfmerchants.com>
$Id$
*
*	Duration::calc( 3723 )
*		== "1 hour, 2 minutes, 3 seconds"
*	Duration::calc( 3723 , array("hundred"=>100, "ten"=>10, "one"=>1) )  
*		== "37 hundreds, 2 tens, 3 ones"
** //*/

class Duration
{
	static $units = array
	(
		'year'=>31536000,
		'month'=>2592000,
		'week'=>604800,
		'day'=>86400,
		'hour'=>3600,
		'minute'=>60,
		'second'=>1
	);
	
	
	function Duration(){}
	
	/*** calc ***
	@access	public
	@param	int
	@param	array	alternative units, ordered high to low
	@return	string	"1 hour, 2 minutes, 3 seconds"
	*/
	function calc($int, $units=false)
	{
		$chunks = array();
		$units = is_array($units)
		?	$units
		:	self::$units;
		
		foreach($units as $label=>$value)
		{
			if($value > $int)
				continue;
			
			$chunks[$label] = floor( $int / $value);
			$int -= $chunks[$label] * $value;
			
			$chunks[$label] = $chunks[$label] 
			. " "
			. $label
			. ( $chunks[$label] > 1 ?	's'	:	'' );
		}
		
		return join(', ',$chunks);
	}
}
?>
