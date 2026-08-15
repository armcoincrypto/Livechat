<?php
declare(strict_types=1);
namespace iEXPackages\Order\Invoices\Concerns;

trait FormatsRequisites
{
    protected function formatRequisite($requisite): array
    {
        return [
            'account' => $requisite->account ?? '',
            'tag'     => $requisite->tag ?? '',
            'label'   => $requisite->label ?? '',
        ];
    }
}
