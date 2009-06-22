<?
class example_class extends ziggi
{
	function example_class()
	{
	}
	
	function parseBuffer()
	{
		if($this->getArg(0) == 'botzilla')
		{
			$this->pm("Yes, ".$this->getUser()."?");
			$this->addTimedEvent('exmp_meh',60);
		}
		
		if($this->getEvent()=='exmp_meh')
			$this->meh();
	}
	
	function meh()
	{
		$this->pm("meh");
	}
}


// this class will respond if the first word it botzilla.
// a minute later, it says something else. That's all there is to it.
?>
