<?php


class SubAreaDecompiler extends Decompiler
{
    protected function getParser(): Parser
    {
        return new SubAreaParser();
    }
}