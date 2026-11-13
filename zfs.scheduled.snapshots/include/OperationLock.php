<?php

/**
 * Holds a process-wide advisory lock for one scheduler operation.
 */
final class OperationLock
{
    /** @var resource|null */
    private $handle;

    /**
     * @param resource $handle
     */
    private function __construct($handle)
    {
        $this->handle = $handle;
    }

    /**
     * Acquire an exclusive lock without waiting.
     *
     * Returns null when another process holds the lock and throws when the
     * lock file cannot be opened or the lock operation itself fails.
     */
    public static function acquire(string $path): ?self
    {
        $handle = @fopen($path, 'c');
        if ($handle === false) {
            throw new RuntimeException("Unable to open operation lock file: {$path}");
        }

        $wouldBlock = 0;
        if (!@flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
            fclose($handle);
            if ($wouldBlock === 1) {
                return null;
            }

            throw new RuntimeException("Unable to acquire operation lock: {$path}");
        }

        return new self($handle);
    }

    /**
     * Release the advisory lock while leaving its file in place.
     */
    public function release(): void
    {
        if (!is_resource($this->handle)) {
            $this->handle = null;
            return;
        }

        $handle = $this->handle;
        $this->handle = null;
        @flock($handle, LOCK_UN);
        fclose($handle);
    }

    public function __destruct()
    {
        $this->release();
    }
}
