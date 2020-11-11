<?php

//error_reporting(E_ALL & ~E_NOTICE);
require_once "autoload.php";

// get id, date and mapData from single map swf files
(new MapDecompiler("lang/maps/"))->run();

// get subarea from map list file
(new SubAreaDecompiler("lang/map_list/"))->run();

