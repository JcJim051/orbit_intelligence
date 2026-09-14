<?php

namespace App\Console\Commands;

use App\Services\Investments\InvestmentEntityClassifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('investments:classify-entities')]
#[Description('Clasifica los proyectos del Meta entre sus entidades descentralizadas')]
class ClassifyInvestmentEntitiesCommand extends Command
{
    public function handle(InvestmentEntityClassifier $classifier): int
    {
        $count = $classifier->classifyAll();
        $this->info("Clasificación terminada: {$count} proyectos con coincidencias.");

        return self::SUCCESS;
    }
}
