<?php


class Logger
{
    private $format;
    public function __construct($format = "Y/m/d H:m:s")
    {
        $this->format = $format;
    }

    public function info($content)
    {
        echo date($this->format) . " : " . $content."\n";
    }

    public function error($content)
    {
        echo date($this->format) . " : ERROR : " . $content."\n";
    }

    public function exception(Exception $e)
    {
        echo date($this->format) . " : ERROR : " . $e->getMessage()."\n".$e->getTraceAsString()."\n";
    }
}