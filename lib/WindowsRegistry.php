<?php

namespace Amp\WindowsRegistry;

use Amp\ByteStream\Message;
use Amp\Process\Process;
use Amp\Process\ProcessException;
use Amp\Promise;

class WindowsRegistry
{
    /**
     * @param string $key
     *
     * @return null|string
     *
     * @throws MissingKeyException
     * @throws QueryException
     */
    public function read(string $key): ?string
    {
        $key = \strtr($key, '/', "\\");
        $parts = \explode("\\", $key);

        $value = \array_pop($parts);
        $key = \implode("\\", $parts);

        $lines = $this->query($key);

        $lines = array_filter($lines, function ($line) {
            return '' !== $line && $line[0] === ' ';
        });

        $values = \array_map(function ($line) {
            return \preg_split("(\\s+)", \ltrim($line), 3);
        }, $lines);

        foreach ($values as $v) {
            if ($v[0] === $value) {
                return $v[2];
            }
        }

        throw new MissingKeyException("Windows registry key '{$key}\\{$value}' not found.");
    }

    /**
     * @param string $key
     *
     * @return array
     *
     * @throws MissingKeyException
     * @throws QueryException
     */
    public function listKeys(string $key): array
    {
        $lines = $this->query($key);

        $lines = \array_filter($lines, function ($line) {
            return '' !== $line && $line[0] !== ' ';
        });

        return $lines;
    }

    /**
     * @param string $key
     *
     * @return array
     *
     * @throws MissingKeyException
     * @throws QueryException
     * @throws \Error
     */
    private function query(string $key): array
    {
        if (0 !== stripos(\PHP_OS, 'WIN')) {
            throw new \Error('Not running on Windows.');
        }

        $key = \strtr($key, '/', "\\");

        $cmd = \sprintf('reg query %s', \escapeshellarg($key));
        $process = new Process($cmd);

        try {
            $process->start();
        } catch (ProcessException $e) {
            throw new QueryException("Executing '{$cmd}' failed", 0, $e);
        }

        $stdout = (new Message($process->getStdout()))->buffer();
        $code = $process->join();

        if ($code !== 0) {
            throw new MissingKeyException("Windows registry key '{$key}' not found.");
        }

        return \explode("\n", \str_replace("\r", '', $stdout));
    }
}
