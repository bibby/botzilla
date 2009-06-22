<?php
define('BZPARSER_DAY_POS',1);
define('BZPARSER_TIME_POS',2);
define('BZPARSER_USER_POS',5);
define('BZPARSER_KEYWORD_POS',6);
define('BZPARSER_CHANNEL_POS',7);
define('BZPARSER_MSG_POS',8);
define('BZPARSER_BOT_NAME','botzilla'); // the name of the bot

class botzillaLogParser
{
	var $keywords = array('PRIVMSG'=>1,'USER'=>1,'NICK'=>1,'NOTICE'=>1,'PONG'=>1,'JOIN'=>1,'PART'=>1,'QUIT'=>1,'MODE'=>1,'ERROR'=>1);
	var $keywordCounts = array();
	var $msgs = array();
	
	function botzillaLogParser($filename)
	{
		$fh = fopen($filename,'r');
		while (!feof($fh)) 
		{
			$rawLine = fgets($fh,4096);
			if($trimmedLine = trim($rawLine))
			{
				$resultLine = $this->parseLine($rawLine);
				$this->populateData($resultLine);
			}
		}
		fclose($fh);
	
	}
	
	function parseLine($line)
	{
		$bool = preg_match('/^\[(.*) @ (.*)\]([-<][->]) (:(\S*)!\S* )?(\S*) (#\S* )?(.*)$/',$line,$result);
		return $result;
	}
	
	function populateData($parsedLine)
	{
		if(is_numeric($parsedLine[BZPARSER_KEYWORD_POS]) OR (strpos($parsedLine[BZPARSER_KEYWORD_POS],'freenode.net') !== FALSE))
		{ // this is some weird freenode thing 
		}
		else // good line
		{
			if(!$this->keywords[$parsedLine[BZPARSER_KEYWORD_POS]])
			{
				//print_r($parsedLine); // for debugging
			}
			else
			{
				$parsedLine[BZPARSER_CHANNEL_POS] = rtrim($parsedLine[BZPARSER_CHANNEL_POS]);
				$this->keywordCounts[$parsedLine[BZPARSER_KEYWORD_POS]]++;
				switch($parsedLine[BZPARSER_KEYWORD_POS])
				{
					case "PRIVMSG":
						$this->msgs[$parsedLine[BZPARSER_DAY_POS]][$parsedLine[BZPARSER_TIME_POS]][($parsedLine[BZPARSER_CHANNEL_POS]?$parsedLine[BZPARSER_CHANNEL_POS]:'pm')][] = array(($parsedLine[BZPARSER_USER_POS]?$parsedLine[BZPARSER_USER_POS]:BZPARSER_BOT_NAME),ltrim($parsedLine[BZPARSER_MSG_POS],":"));
						break;
				}
			}
		}
	}
	
	function flipdate($d)
	{
		$x=explode('/',$d);
		return array($x[1]."/".$x[0], $x[1]);
	}
	
	function WhoWhat($ww,$t)
	{
		if(is_array($ww))
		{
			return $t . " $ww[0] : ".str_replace("\n",'',$ww[1])."\n";
		}
	}
	
}
?>
