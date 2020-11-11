<?php


class MapParser extends Parser
{
    const FILE_EXTENSION = ".swf";

    const POSSIBLE_INDEXES =
        [
            "id",
            "date",
            "mapData",
            "key",
            "decryptedData",
            "sa"
        ];

    public $values = [];

    /**
     * @param AS2Assignment $statement
     * @throws Exception
     */
    protected function parseOperation(AS2Assignment $statement)
    {
        if ($statement->operand1 instanceof AS2Identifier)
        {
            $this->add($statement->operand1->string, $statement->operand2);
        }
    }

    private function add($index, $value)
    {
        if (in_array($index, self::POSSIBLE_INDEXES) and (!isset($this->value[$index]) || empty ($this->value[$index])))
        {
            $this->values[$index] = $value;
        }
    }

    protected function onGetFile(string $path, $file)
    {
        $splitPath = explode('_', basename($path, self::FILE_EXTENSION));
        $this->add("date", $splitPath[1]);
    }

    protected function saveAndReset()
    {
        if (isset($this->values["id"]))
        {
            $this->database->insert('static_maps',
                [
                    'id' => $this->values["id"],
                    'date' => $this->values["date"] ?? null,
                    'mapData' => $this->values["mapData"] ?? null,
                ]);
            $this->logger->info("Inserted (if not exist) into database : " . $this->values['id']);
        }
    }
}