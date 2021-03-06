<?php

namespace Propel\PropelBundle\Command;

use Propel\PropelBundle\Command\PhingCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * BuildCommand.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author William DURAND <william.durand1@gmail.com>
 */
class BuildModelCommand extends PhingCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDescription('Build the Propel Object Model classes based on XML schemas')
            ->setHelp(<<<EOT
The <info>propel:build-model</info> command builds the Propel runtime model classes (ActiveRecord, Query, Peer, and TableMap classes) based on the XML schemas defined in all Bundles.

  <info>php app/console propel:build-model</info>
EOT
            )
            ->setName('propel:build-model')
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When the target directory does not exist
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->callPhing('om');

        foreach ($this->tempSchemas as $schemaFile => $schemaDetails) {
            $output->writeln(sprintf('Built model classes for bundle "<info>%s</info>"', $schemaDetails['bundle']));
        }
    }
}
