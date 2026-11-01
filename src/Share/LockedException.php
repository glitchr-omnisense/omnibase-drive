<?php

namespace Base\Drive\Share;

/**
 * What the retention lock refuses (drive.retention): deleting, overwriting, renaming or moving
 * something in a locked folder - whoever asks, the team included.
 */
final class LockedException extends \RuntimeException
{
    public function __construct(public readonly string $path)
    {
        parent::__construct(sprintf('"%s" is kept by the retention lock: it is neither deleted, overwritten, renamed nor moved.', $path));
    }
}
