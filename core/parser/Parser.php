<?php

use Medoo\Medoo;

abstract class Parser extends ASSourceCodeDumper
{
    protected $logger;
    protected $database;

    public function __construct()
    {
        $this->logger = new Logger();
        $this->database = new Medoo([
            'database_type' => 'mysql',
            'database_name' => 'static_maps',
            'server' => 'localhost',
            'username' => 'root',
            'password' => ''
        ]);
    }

    public function get($swfFile) {
        $this->frameIndex = 1;
        $this->symbolName = "_root";
        $this->symbolCount = 0;
        $this->symbolNames = array( 0 => $this->symbolName);
        $this->instanceCount = 0;
        foreach($swfFile->tags as &$tag) {
            $this->processTagForGet($tag);
            $tag = null;
        }
    }

    protected function processTagForGet($tag) {
        if($tag instanceof SWFDoActionTag || $tag instanceof SWFDoInitActionTag) {
            // an empty tag would still contain the zero terminator
            if (strlen($tag->actions) > 1) {
                if (!$this->decoderAVM1) {
                    $this->decoderAVM1 = new AVM1Decoder;
                }
                if (!$this->decompilerAS2) {
                    $this->decompilerAS2 = new AS2Decompiler;
                }
                $operations = $this->decoderAVM1->decode($tag->actions);
                $tag->actions = null;
                $this->parseOperations($this->decompilerAS2->decompile($operations));
                $operations = null;
                $statements = null;
            }
        }
    }

    protected function parseOperations($statements)
    {
        foreach ($statements as $statement)
        {
            if ($statement instanceof AS2BasicStatement && $statement->expression instanceof AS2Assignment)
            {
                $this->parseOperation($statement->expression);
            }
        }
        $this->saveAndReset();
    }

    /**
     * @param $path
     * @return false|resource
     * @throws Exception
     */
    public function getFile(string $path)
    {
        if (file_exists($path))
        {
            $file = fopen($path, "rb");
            $this->onGetFile($path, $file);
            return $file;
        }
        throw new Exception("file does not exist");
    }

    protected abstract function onGetFile(string $path, $file);
    protected abstract function parseOperation(AS2Assignment $statement);
    protected abstract function saveAndReset();
}