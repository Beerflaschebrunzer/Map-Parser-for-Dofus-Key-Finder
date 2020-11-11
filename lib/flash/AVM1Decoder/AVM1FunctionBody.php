<?php

class AVM1FunctionBody {
    protected $decoder;
    protected $byteCodes;
    protected $position;
    protected $count;
    protected $constantPool;
    protected $registers;

    public function __construct($decoder, $byteCodes, $position, $count, $constantPool, $registers) {
        $this->decoder = $decoder;
        $this->byteCodes = $byteCodes;
        $this->position = $position;
        $this->count = $count;
        $this->constantPool = $constantPool;
        $this->registers = $registers;
    }

    public function __get($name) {
        if($name == 'operations') {
            return $this->decoder->decodeInstructions($this->byteCodes, $this->position, $this->count, $this->constantPool, $this->registers);
        }
    }
}