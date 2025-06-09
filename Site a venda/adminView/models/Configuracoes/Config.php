<?php

class Config
{
    private $jsonPath;

    public function __construct($jsonPath)
    {
        $this->jsonPath = $jsonPath;
    }

    public function getJsonPath()
    {
        return $this->jsonPath;
    }

    public function getJsonData()
    {
        $jsonContent = file_get_contents($this->jsonPath);
        return json_decode($jsonContent);
    }
}
?>