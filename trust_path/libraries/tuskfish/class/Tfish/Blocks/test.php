<?php

declare(strict_types=1);

namespace Tfish\Blocks;

class TestBlock
{
    use \Tfish\Traits\ValidateString;

    // Declare properties.
    public $title = '';
    public $description = '';


    public function __construct() {}

    // Getters and setters.

    public function title() {
        return $this->title;
    }

    public function setTitle(string $title) {
        $this->title = $this->trimString($title);
    }

    public function description() {
        return $this->description;
    }

    public function setDescription(string $description) {
        $this->description = $this->trimString($description);
    }

    // Other functions.

}
