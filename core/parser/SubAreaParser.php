<?php

class SubAreaParser extends Parser
{
    private $values = [];

    protected function onGetFile(string $path, $file)
    {
        // nothing happens here
    }

    protected function parseOperation(AS2Assignment $statement)
    {
        if (!($statement->operand1 instanceof AS2ArrayAccess))
            return;


        $id = $statement->operand1->index;

        if (!($statement->operand1->array instanceof AS2BinaryOperation))
            return;

        if (!($statement->operand1->array->operand1 instanceof AS2Identifier
            && $statement->operand1->array->operand2 instanceof AS2Identifier))
            return;

        if (!($statement->operand1->array->operand1->string === "MA"
            && $statement->operand1->array->operand2->string === "m"))
            return;

        if (!($statement->operand2 instanceof AS2ObjectInitializer))
            return;

        for ($i = 0; $i < count($statement->operand2->items) - 1; $i++)
        {
            if ($statement->operand2->items[$i] === "sa")
            {
                $this->values[$id] = $statement->operand2->items[$i + 1];
            }
        }
    }

    protected function saveAndReset()
    {
        foreach ($this->values as $id => $sa)
        {
            // update
            $this->database->update('static_maps',
                [
                    "sa" => $sa
                ],
                [
                    "id[=]" => $id
                ]
            );

            $this->logger->info("Updated (if exist) into database : [" . $id . "] " . $sa);
        }
    }
}