<?php

namespace Elrayn\CoretaxFaktur;

class Issue
{
    const ERROR = 'error';
    const WARNING = 'warning';

    public string $level;
    public ?int $faktur;
    public ?int $item;
    public string $field;
    public string $message;

    // $faktur and $item are 1-based positions, null for file-level issues.
    public function __construct(string $level, ?int $faktur, ?int $item, string $field, string $message)
    {
        $this->level = $level;
        $this->faktur = $faktur;
        $this->item = $item;
        $this->field = $field;
        $this->message = $message;
    }

    public function isError(): bool
    {
        return $this->level === self::ERROR;
    }

    public function __toString(): string
    {
        $where = $this->faktur === null ? '' : "faktur #{$this->faktur} ";
        $where .= $this->item === null ? '' : "item #{$this->item} ";

        return "[{$this->level}] {$where}{$this->field}: {$this->message}";
    }
}
