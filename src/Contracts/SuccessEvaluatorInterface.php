<?php

namespace Goldnead\WebhookManager\Contracts;

interface SuccessEvaluatorInterface
{
    public function handle(): string;

    /**
     * @param  array{status:int|null,headers:array,body:string|null}  $response
     */
    public function isSuccess(array $response, array $config = []): bool;
}
