<?php

namespace Kjos\Orchestra\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Shell
{
    private Collection $codes;

    public function __construct()
    {
        $this->codes = collect();
    }

    public function addCode(string $cmd): static
    {
        $this->codes->add($cmd);

        return $this;
    }

    public function getCodes(): string
    {
        $codes = \implode(" && \n", $this->codes->toArray());
        $codes = <<<BASH
                   {$codes}
               BASH;

        return \trim($codes);
    }

    public function execute(): bool
    {
        $code = $this->exec($this->getCodes());
        $this->flush();

        return $code;
    }

    public function flush(): void
    {
        $this->codes = collect();
    }

    private function exec(string $cmd): bool
    {
        $exitCode = 0;
        \exec('bash -c ' . \escapeshellarg($cmd), $output, $exitCode);

        // Log::info(Carbon::now()->toDateTimeString() .  ' Shell execution result: ' . \implode("\n", $output));
        return $exitCode === 0;
    }
}
