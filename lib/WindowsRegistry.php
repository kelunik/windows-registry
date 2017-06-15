<?php

namespace Amp\WindowsRegistry;

use Amp\ByteStream\Message;
use Amp\Process\Process;
use Amp\Promise;
use function Amp\call;

class WindowsRegistry {
    public function read(string $key): Promise {
        return call(function () use ($key) {
            $lines = yield $this->listKeys($key);

            $key = \strtr($key, "/", "\\");
            $parts = \explode("\\", $key);

            $value = \array_pop($parts);
            $key = \implode("\\", $parts);

            $values = \array_map(function ($line) {
                return \preg_split("(\\s+)", \ltrim($line), 3);
            }, $lines);

            foreach ($values as $v) {
                if ($v[0] === $value) {
                    return $v[2];
                }
            }

            throw new KeyNotFoundException("Windows registry key '{$key}\\{$value}' not found.");
        });
    }

    public function listKeys(string $key): Promise {
        return call(function () use ($key) {
            if (\strtoupper(\substr(\PHP_OS, 0, 3)) !== 'WIN') {
                throw new \Error("Not running on Windows.");
            }

            $key = \strtr($key, "/", "\\");

            $cmd = \sprintf("reg query %s", \escapeshellarg($key));
            $process = new Process($cmd);
            $process->start();

            $code = yield $process->join();

            $stdout = yield new Message($process->getStdout());
            $stderr = yield new Message($process->getStderr());

            if ($code !== 0) {
                $debugInfo = "EXIT: {$code}\n\nSTDOUT\n======\n\n{$stdout}\n\nSTDERR\n======\n\n{$stderr}\n";
                throw new \RuntimeException("Unknown error file getting key '{$key}'.\n\n$debugInfo");
            }

            $lines = \explode("\n", \str_replace("\r", "", $stdout));
            $lines = \array_filter($lines, function ($line) {
                return \strlen($line) && $line[0] !== " ";
            });

            return $lines;
        });
    }
}
