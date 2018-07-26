<?php

use Amp\Loop;
use Amp\WindowsRegistry\MissingKeyException;
use Amp\WindowsRegistry\WindowsRegistry;

require __DIR__ . "/../vendor/autoload.php";

# Read Windows DNS configuration

Loop::run(function () {
    $keys = [
        "HKEY_LOCAL_MACHINE\\SYSTEM\\CurrentControlSet\\Services\\Tcpip\\Parameters\\NameServer",
        "HKEY_LOCAL_MACHINE\\SYSTEM\\CurrentControlSet\\Services\\Tcpip\\Parameters\\DhcpNameServer",
    ];

    $reader = new WindowsRegistry;
    $nameserver = "";

    while ($nameserver === "" && ($key = \array_shift($keys))) {
        try {
            $nameserver = yield $reader->read($key);
        } catch (MissingKeyException $e) {
        }
    }

    if ($nameserver === "") {
        $subKeys = yield $reader->listKeys("HKEY_LOCAL_MACHINE\\SYSTEM\\CurrentControlSet\\Services\\Tcpip\\Parameters\\Interfaces");

        foreach ($subKeys as $key) {
            foreach (["NameServer", "DhcpNameServer"] as $property) {
                try {
                    $nameserver = (yield $reader->read("{$key}\\{$property}"));

                    if ($nameserver !== "") {
                        break 2;
                    }
                } catch (MissingKeyException $e) {
                }
            }
        }
    }

    if ($nameserver !== "") {
        // Microsoft documents space as delimiter, AppVeyor uses comma.
        $nameservers = \array_map(function ($ns) {
            return \trim($ns) . ":53";
        }, \explode(" ", \strtr($nameserver, ",", " ")));

        print "Found nameservers: " . \implode(", ", $nameservers) . PHP_EOL;
    } else {
        print "No nameservers found." . PHP_EOL;
    }
});
