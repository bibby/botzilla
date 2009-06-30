<?php
/**
* IRC Color Util
*@author bibby <bibby@surfmerchants.com>
*$Id$

* $c = new Color();
* return $c->red("foo","black");

* obj->color( txt, bg=false );
*
* change to use __callStatic on php 5.3+ to do
* Color::red(txt,black)
*/

define('CC',chr(3)); // ^C
class Color
{
	var $colors=array( 
		"black"=>1,
		"blue"=>2,
		"green"=>3,
		"red"=>4,
		"brown"=>5,
		"purple"=>6,
		"orange"=>7,
		"yellow"=>8,
		"lime"=>9,
		"teal"=>10, // doesn't work in pidgin?
		"aqua"=>11,
		"sky"=>12,
		"pink"=>13,
		"gray"=>14, "grey"=>14,
		"lightgray"=>15, "lightgrey"=>15,
		"white"=>16
	);
	
	public function __call($name, $arguments)
	{
		list($txt,$bg) = $arguments;
		$col = $this->colors[$name];
		if(!$col)
			return $txt;
		
		if($bg && !$this->colors[$bg])
			unset($bg);
		else
			$bg = $this->colors[$bg];
		
		return CC.$col.($bg?",$bg":'').$txt.CC;
	}
}
?>
