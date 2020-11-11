<?php

class MapDecompiler extends Decompiler
{
    protected function getParser(): Parser
    {
        return new MapParser();
    }
}