#!/bin/bash

if test -z "$1"
then
	echo "need a name to give the raw log: ./newlog.sh feb08_02"	
else
	php -q  logparse.php
	mv logs/log logs/raw/$1
	touch logs/log
	echo "saved logs/raw/$1"
fi


