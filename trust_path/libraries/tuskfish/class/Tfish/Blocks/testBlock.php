<?php

declare(strict_types=1);

namespace Tfish\Blocks;

class TestBlock
{
    use \Tfish\Traits\ValidateString;

    // Declare properties.
    public $id = 0;
    public $title = '';
    public $description = '';
    public $language = '';
    public $onlineStatus = 0;


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

    public function render(): string {
        $html = '<p>This is a test block</p>';
        return $html;
    }

}
