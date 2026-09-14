<?php

function db()
{
    static $connection;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $dataDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dataDirectory)) {
        mkdir($dataDirectory, 0775, true);
    }

    $databaseFile = $dataDirectory . DIRECTORY_SEPARATOR . 'bodycare.sqlite';
    $connection = new PDO('sqlite:' . $databaseFile);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $connection->exec('PRAGMA foreign_keys = ON');

    return $connection;
}
