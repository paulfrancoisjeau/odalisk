<?php

namespace Odalisk\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;


use Odalisk\Entity\Dataset;
use Odalisk\Entity\Statistics;
use Odalisk\Entity\DatasetCriteria;

/**
 * Generates statistics
 */
class GenerateStatisticsCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('odalisk:statistics:generate')
            ->setDescription('generate statistics from datasets')
            ->addArgument('platform', InputArgument::OPTIONAL,
                'Which platform do you want to analyse?'
            )
            ->addOption('list', null, InputOption::VALUE_NONE,
                'If set, the task will display available platforms names rather than analyse them'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->em = $this->getEntityManager();
        $datasetRepo  = $this->getEntityRepository('Odalisk\Entity\Dataset');
        $criteriaRepo = $this->getEntityRepository('Odalisk\Entity\DatasetCriteria');

        $container = $this->getContainer();
        $platformServices = $container->getParameter('config.enabled_portals');

        // Initialization
        $criteriaRepo->init();
        // This make us capable to iterate on all datasets without load them in one
        // shot.
        $sql = 'SELECT d.id FROM Odalisk\Entity\Dataset d';
        $datasetsIds = $this->em->createQuery($sql)->iterate();
        $datasetsCount = count($datasetsIds);

        $this->writeBlock($output, "[Statistics] Beginning of generation");
        foreach($datasetsIds as $i => $datasetId) {
            $datasetId = $datasetId[$i]['id'];
            $dataset = $datasetRepo->find($datasetId);
            $criteria = $criteriaRepo->getCriteria($dataset);

            $datasetCriteria = new DatasetCriteria();
            foreach($criteria as $name => $value) {
                call_user_func(array($datasetCriteria ,$name), $value);
            }

            $dataset->setCriteria($datasetCriteria);
            $this->em->persist($datasetCriteria);

            $i++;
            if($i % 100 == 0) {
                $this->em->flush();
                error_log("[Statistics] flush $i datasets' criteria on $datasetsCount");
            }
        }
        $this->em->flush();
        error_log("[Statistics] flush $i datasets' criteria on $datasetsCount");

        $this->writeBlock($output, "[Statistics] The end !");
    }
}
