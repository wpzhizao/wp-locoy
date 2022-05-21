<?php

namespace WPLocoy;

final class AutoLoader {
    private $namespace;
    private $filepath;

    public function __construct($namespace, $filepath) {
        $this->namespace = $namespace;
        $this->filepath = $filepath;

        spl_autoload_register(array($this, 'callback'));
    }

    public function callback($class) {
        $namespace = $this->namespace;

        if (strpos($class, $namespace) !== 0) {
            return;
        }

        $class = str_replace($namespace, '', $class);
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);

        $file = untrailingslashit($this->filepath) . DIRECTORY_SEPARATOR .  $class . '.php';

        if (file_exists($file)) {
            require_once($file);
        }
    }
}
