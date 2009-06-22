<?php
/**
* Ziggi Test Object
*@author bibby <bibby@surfmerchants.com>
*$Id: bz_test.class.php,v 1.2 2008/05/30 13:13:13 abibby Exp $
This software is provided 'as-is', without any express or implied warranty.
In no event will the authors be held liable for any damages arising from the
use of this software. It may be freely modified and redistributed, even in commercial applications.
*/

require('botzilla.class.php');

class bz_test
{
	function bz_test($clsFile)
	{
		$botzilla = new botzilla(0,0,0,0);
		$botzilla->logging_enable(0);
		$botzilla->addZiggi($clsFile);
		$botzilla->webdebug=true;
		if(!is_object(current($botzilla->ziggis)))
			exit();
			
		$this->z=&current($botzilla->ziggis);
	}
	
	function listen($txt)
	{
		$this->z->listen(array(
			'username'=>'test_user',
			'text'=>$txt,
			'channel'=>"testChannel")
		);
		
		echo "<<< $txt\n";
		echo $this->response();
	}
	
	function response()
	{
		if(!is_array($this->z->bz_ret['pm']))
			return false;
		else
			foreach($this->z->bz_ret['pm'] as $re)
				echo ">>> ".current($re)."\n\n";
				
		$this->z->bz_ret=array();
	}
}
?>
