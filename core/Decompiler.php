<?php

abstract class Decompiler
{
    private $logger;
    private $path;

    public function __construct($path)
    {
        $this->logger = new Logger();
        $this->path = $path;
    }

    public function run()
    {
        $this->fromDirectory($this->path);
    }

    public function fromDirectory($path)
    {
        if ($handle = opendir($path)) {

            while (false !== ($entry = readdir($handle))) {

                if ($entry != "." && $entry != "..") {
                    $this->fromFile($path.$entry);
                }
            }

            closedir($handle);
        }
    }

    protected abstract function getParser() : Parser;

    public function fromFile($path)
    {
        try {
            if($path) {
                $mapParser = $this->getParser();
                $input = $mapParser->getFile($path);
                if($input) {
                    $parser = new SWFParser;
                    try {
                        $swfFile = $parser->parse($input, $mapParser->getRequiredTags(), true);
                    } catch (Exception $e) {
                        $this->logger->exception($e);
                        return;
                    }
                    fclose($input);
                    if($swfFile) {
                        $mapParser->get($swfFile);
                    } else {
                        $this->logger->error("Error parsing $path");
                    }
                } else {
                    $this->logger->error("Error opening $path");
                }
            }
        } catch (Exception $e) {
            $this->logger->exception($e);
        }
    }
}