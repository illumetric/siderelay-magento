<?php

declare(strict_types=1);

namespace SideRelay\Tracking\Console;

use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Retry extends Command
{
    public function __construct(private ResourceConnection $resource)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->setName('siderelay:retry')->setDescription('Retry waiting purchases after fixing the SideRelay connection.');
        parent::configure();
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->resource->getConnection()->update($this->resource->getTableName('siderelay_tracking_outbox'), ['status' => 'pending','attempts' => 0,'next_attempt_at' => gmdate('Y-m-d H:i:s')], ['status IN (?)' => ['configuration','attention']]);
        $output->writeln($count . ' purchases queued for another attempt.');
        return 0;
    }
}
