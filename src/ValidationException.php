<?php

namespace Elrayn\CoretaxFaktur;

use RuntimeException;

class ValidationException extends RuntimeException
{
    /** @var Issue[] */
    protected array $issues;

    /** @param Issue[] $issues */
    public function __construct(array $issues)
    {
        $this->issues = $issues;
        parent::__construct("Coretax XML has errors:\n" . implode("\n", $issues));
    }

    /** @return Issue[] */
    public function issues(): array
    {
        return $this->issues;
    }
}
