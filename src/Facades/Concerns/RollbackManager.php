<?php

namespace Kjos\Orchestra\Facades\Concerns;

use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class RollbackManager
{
    /** @var array<Closure> $callbacks */
    protected array $callbacks = [];

    /**
     * Registers a new rollback callback.
     * The given callback will be added to the beginning of the list of callbacks to be executed.
     * This is a LIFO (Last In First Out) implementation, meaning the most recently added callbacks will be executed first.
     * The callback should take no arguments and may throw exceptions, which will be caught and logged.
     * If an exception is thrown, the error will be logged and the exception will be rethrown.
     *
     * @param  Closure  $callback  The rollback callback to register
     * @return void
     */
    public function add(Closure $callback): void
    {
        \array_unshift($this->callbacks, $callback); // LIFO (Last In First Out)
    }

    /**
     * Executes all registered rollback callbacks in a Last In First Out (LIFO) order.
     * Logs the success or failure of each rollback step with a timestamp.
     * If a callback throws an exception, logs the error and rethrows it.
     *
     * @throws \Exception if a rollback step fails
     */
    public function run(): void
    {
        foreach ($this->callbacks as $callback) {
            $date = Carbon::now();
            try {
                $callback();
                logger()->info("Rollback step executed successfully [$date]");
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }
}
