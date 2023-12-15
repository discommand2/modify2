<?php

namespace RPurinton\modify2;

class Locales
{
    public static function get(): array
    {
        $path = __DIR__ . "/../locales/";
        if (!is_dir($path)) throw new Error("commands folder not found");
        $files = glob($path . '*.json');
        if (!$files) throw new Error("no locales found");
        $commands = [];
        foreach ($files as $file) {
            $command = json_decode(file_get_contents($file), true);
            if (!$command) throw new Error("failed to load locale file: $file");
            $commands[] = $command;
        }
        return $commands;
    }
}
